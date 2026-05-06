<?php
// WebSocket Server for Real-Time Signal Delivery
// Run: php websocket_server.php
// This server pushes signals from admin dashboard to mobile apps instantly

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db_connect.php';

class SignalWebSocketServer {
    private $clients = [];
    private $server;
    private $port = 8080;
    
    public function __construct() {
        // Create WebSocket server
        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        
        if (!$this->server) {
            die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
        }
        
        if (!socket_bind($this->server, '0.0.0.0', $this->port)) {
            die("Failed to bind to port {$this->port}: " . socket_strerror(socket_last_error($this->server)) . "\n");
        }
        
        if (!socket_listen($this->server, 5)) {
            die("Failed to listen: " . socket_strerror(socket_last_error($this->server)) . "\n");
        }
        
        echo "🚀 Signal WebSocket Server started on port {$this->port}\n";
        echo "Waiting for mobile app connections...\n\n";
        
        $this->run();
    }
    
    private function run() {
        while (true) {
            $read = [$this->server];
            $write = null;
            $except = null;
            
            // Monitor sockets for changes
            if (socket_select($read, $write, $except, null) < 1) {
                continue;
            }
            
            // New connection
            if (in_array($this->server, $read)) {
                $client = socket_accept($this->server);
                if ($client) {
                    $this->clients[] = $client;
                    socket_set_nonblock($client);
                    echo "📱 Mobile client connected. Total: " . count($this->clients) . "\n";
                    
                    // Send welcome message
                    $welcome = json_encode([
                        'type' => 'welcome',
                        'message' => 'Connected to Smart Trade Signal Server',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    socket_write($client, $welcome . "\n", strlen($welcome) + 1);
                }
                
                // Remove server from read array
                $key = array_search($this->server, $read);
                unset($read[$key]);
            }
            
            // Check existing clients for messages
            foreach ($this->clients as $index => $client) {
                $data = @socket_read($client, 1024, PHP_NORMAL_READ);
                
                if ($data === false) {
                    // Client disconnected
                    unset($this->clients[$index]);
                    socket_close($client);
                    echo "📱 Client disconnected. Total: " . count($this->clients) . "\n";
                    continue;
                }
                
                if ($data) {
                    $this->handleClientMessage($client, trim($data));
                }
            }
            
            // Check for new signals in database
            $this->checkForNewSignals();
            
            // Small sleep to prevent CPU overload
            usleep(100000); // 100ms
        }
    }
    
    private function handleClientMessage($client, $data) {
        $message = json_decode($data, true);
        
        if (!$message || !isset($message['type'])) {
            return;
        }
        
        switch ($message['type']) {
            case 'auth':
                // Client sends license key for authentication
                $licenseKey = $message['licenseKey'] ?? '';
                $mentorId = $this->validateLicense($licenseKey);
                
                if ($mentorId) {
                    // Store mentor ID with this client connection
                    $clientKey = (int)$client;
                    $this->clients[$clientKey] = [
                        'socket' => $client,
                        'mentor_id' => $mentorId,
                        'license_key' => $licenseKey,
                        'connected_at' => time()
                    ];
                    
                    $response = [
                        'type' => 'auth_success',
                        'mentor_id' => $mentorId,
                        'message' => 'Authenticated'
                    ];
                } else {
                    $response = [
                        'type' => 'auth_failed',
                        'message' => 'Invalid license key'
                    ];
                }
                
                socket_write($client, json_encode($response) . "\n");
                break;
                
            case 'ping':
                // Heartbeat from client
                $response = ['type' => 'pong', 'timestamp' => time()];
                socket_write($client, json_encode($response) . "\n");
                break;
        }
    }
    
    private function validateLicense($licenseKey) {
        if (DB_OFFLINE) {
            // Demo mode - accept any valid format key
            if (preg_match('/^EAV-[A-Z0-9]{4}-\d{3}-\d{4}$/', $licenseKey)) {
                return abs(crc32($licenseKey)) % 9000 + 1000;
            }
            return false;
        }
        
        try {
            global $conn;
            $stmt = $conn->prepare("SELECT mentor_id FROM licence_keys WHERE key_value = ? AND status = 'Active'");
            $stmt->execute([$licenseKey]);
            $license = $stmt->fetch();
            
            if ($license) {
                return $license['mentor_id'] ?? $license['id'];
            }
        } catch (Exception $e) {
            error_log("License validation error: " . $e->getMessage());
        }
        
        return false;
    }
    
   private function checkForNewSignals() {
        // First check for pushed signals via signal_dispatcher (real-time)
        $pushFile = __DIR__ . '/websocket_push.json';
        if (file_exists($pushFile)) {
            $pushData = json_decode(file_get_contents($pushFile), true);
            if (is_array($pushData) && count($pushData) > 0) {
                foreach ($pushData as $message) {
                    $this->broadcastToAll($message);
                }
                // Clear the push file after broadcasting
                file_put_contents($pushFile, json_encode([]));
            }
        }
        
        if (DB_OFFLINE) {
            // Also check signals_cache.json as fallback
            $cacheFile = __DIR__ . '/signals_cache.json';
            if (file_exists($cacheFile)) {
                $signals = json_decode(file_get_contents($cacheFile), true);
                $lastCheck = $this->lastCheck ?? 0;
                $this->lastCheck = time();
                
                foreach ($signals as $signal) {
                    if ($signal['timestamp'] > $lastCheck) {
                        $this->broadcastSignal($signal);
                    }
                }
            }
            return;
        }
        
        try {
            global $conn;
            // Database polling for new signals (every 10 seconds)
            $stmt = $conn->prepare("
                SELECT * FROM signals 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
                AND status = 'PENDING'
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $newSignals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($newSignals as $signal) {
                $this->broadcastSignal($signal);
            }
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    private function broadcastSignal($signal) {
        $message = [
            'type' => 'new_signal',
            'signal' => [
                'id' => $signal['id'],
                'pair' => $signal['pair'],
                'direction' => $signal['direction'],
                'tp' => (float)$signal['tp'],
                'sl' => (float)$signal['sl'],
                'lot' => (float)$signal['lot'],
                'timestamp' => $signal['created_at'] ?? $signal['timestamp']
            ]
        ];
        
        $this->broadcastToAll($message);
    }
    
    private function broadcastToAll($message) {
        $data = json_encode($message) . "\n";
        $sentCount = 0;
        
        foreach ($this->clients as $clientData) {
            $socket = is_array($clientData) ? $clientData['socket'] : $clientData;
            if (@socket_write($socket, $data, strlen($data))) {
                $sentCount++;
            }
        }
        
        if ($sentCount > 0) {
            $signalInfo = $message['signal'] ?? $message;
            $pair = $signalInfo['pair'] ?? 'unknown';
            $dir = $signalInfo['direction'] ?? 'unknown';
            echo "📡 Broadcasted: {$pair} {$dir} to {$sentCount} mobile apps\n";
        }
    }
            }
            return;
        }
        
        try {
            global $conn;
            // Get signals created in last 10 seconds that haven't been pushed yet
            $stmt = $conn->prepare("
                SELECT * FROM signals 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
                AND status = 'PENDING'
            ");
            $stmt->execute();
            $newSignals = $stmt->fetchAll();
            
            foreach ($newSignals as $signal) {
                $this->broadcastSignal($signal);
            }
        } catch (Exception $e) {
            // Table might not exist yet
        }
    }
    
    private function broadcastSignal($signal) {
        $message = [
            'type' => 'new_signal',
            'signal' => [
                'id' => $signal['id'],
                'pair' => $signal['pair'],
                'direction' => $signal['direction'],
                'tp' => (float)$signal['tp'],
                'sl' => (float)$signal['sl'],
                'lot' => (float)$signal['lot'],
                'timestamp' => $signal['created_at'] ?? $signal['timestamp']
            ]
        ];
        
        $data = json_encode($message) . "\n";
        $sentCount = 0;
        
        foreach ($this->clients as $clientData) {
            $socket = is_array($clientData) ? $clientData['socket'] : $clientData;
            
            // Check if this client is subscribed to this mentor's signals
            // For now, send to all connected clients (can be filtered)
            if (@socket_write($socket, $data, strlen($data))) {
                $sentCount++;
            }
        }
        
        if ($sentCount > 0) {
            echo "📡 Broadcasted signal {$signal['pair']} {$signal['direction']} to {$sentCount} mobile apps\n";
        }
    }
}

// Start server
$server = new SignalWebSocketServer();
