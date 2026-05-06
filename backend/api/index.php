<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Use existing platform database config
require_once __DIR__ . '/../config/db_connect.php';

// Initialize response array
$response = ['success' => false];

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// ============================================
// API ENDPOINTS
// ============================================

// 1. LICENSE VALIDATION
if ($uri[0] === 'api' && $uri[1] === 'validate-license' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $licenseKey = $data['licenseKey'] ?? '';
    
    if (!$licenseKey) {
        http_response_code(400);
        echo json_encode(['error' => 'License key is required']);
        exit();
    }
    
    // Check license in platform database
    if (DB_OFFLINE) {
        // Demo mode - accept any key format EAV-XXXX-XXX-XXXX
        if (preg_match('/^EAV-[A-Z0-9]{4}-\d{3}-\d{4}$/', $licenseKey)) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => 1,
                    'username' => 'Demo User',
                    'email' => 'demo@example.com',
                    'mentor_id' => getMentorIdFromKey($licenseKey)
                ],
                'license' => [
                    'id' => 1,
                    'expires_at' => date('Y-m-d', strtotime('+1 year'))
                ]
            ]);
            exit();
        }
        http_response_code(401);
        echo json_encode(['error' => 'Invalid license key format']);
        exit();
    }
    
    try {
        // Platform uses 'licence_keys' table with 'key_value' field
        $stmt = $conn->prepare("SELECT * FROM licence_keys WHERE key_value = ? AND status = 'Active'");
        $stmt->execute([$licenseKey]);
        $license = $stmt->fetch();
        
        if (!$license) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or inactive license key']);
            exit();
        }
        
        // Get user info
        $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$license['user_id'] ?? $license['id']]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            // Fallback: use data from licence_keys table
            $user = [
                'id' => $license['user_id'] ?? $license['id'],
                'username' => $license['user_name'] ?? 'User',
                'email' => $license['email'] ?? '',
                'mentor_id' => $license['mentor_id'] ?? $license['id']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'] ?? 0,
                'username' => $user['username'] ?? $user['user_name'] ?? 'User',
                'email' => $user['email'] ?? '',
                'mentor_id' => $user['mentor_id'] ?? $license['mentor_id'] ?? $license['id']
            ],
            'license' => [
                'id' => $license['id'],
                'expires_at' => $license['expires'] ?? $license['expires_at'] ?? date('Y-m-d', strtotime('+1 year'))
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// 2. GET SIGNALS (for mobile app polling)
if ($uri[0] === 'api' && $uri[1] === 'signals' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $licenseKey = $_GET['licenseKey'] ?? '';
    $since = $_GET['since'] ?? 0; // Get signals since timestamp
    
    if (!$licenseKey) {
        http_response_code(400);
        echo json_encode(['error' => 'License key is required']);
        exit();
    }
    
    // Validate license first
    $licenseValid = false;
    $mentorId = null;
    
    if (DB_OFFLINE) {
        $licenseValid = preg_match('/^EAV-[A-Z0-9]{4}-\d{3}-\d{4}$/', $licenseKey);
        $mentorId = getMentorIdFromKey($licenseKey);
    } else {
        try {
            $stmt = $conn->prepare("SELECT mentor_id, id FROM licence_keys WHERE key_value = ? AND status = 'Active'");
            $stmt->execute([$licenseKey]);
            $license = $stmt->fetch();
            if ($license) {
                $licenseValid = true;
                $mentorId = $license['mentor_id'] ?? $license['id'];
            }
        } catch (Exception $e) {
            $licenseValid = false;
        }
    }
    
    if (!$licenseValid) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid license key']);
        exit();
    }
    
    // Get signals for this mentor
    // In production, signals are stored in database when admin sends them
    // For now, read from localStorage via a bridge or use simulated data
    
    if (DB_OFFLINE) {
        // Return demo signals
        $signals = [
            [
                'id' => time(),
                'pair' => 'EURUSD',
                'direction' => 'BUY',
                'tp' => 1.0850,
                'sl' => 1.0800,
                'lot' => 0.5,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'PENDING'
            ]
        ];
    } else {
        // Try to read from signals table (if exists)
        try {
            $stmt = $conn->prepare("SELECT * FROM signals WHERE mentor_id = ? AND created_at > FROM_UNIXTIME(?) ORDER BY created_at DESC");
            $stmt->execute([$mentorId, $since]);
            $signals = $stmt->fetchAll();
        } catch (Exception $e) {
            // Table doesn't exist yet, return empty array
            $signals = [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'signals' => $signals,
        'mentor_id' => $mentorId
    ]);
    exit();
}

// 3. WEBHOOK FOR ADMIN DASHBOARD TO SEND SIGNALS
if ($uri[0] === 'api' && $uri[1] === 'webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $mentorId = $data['mentor_id'] ?? null;
    $pair = $data['pair'] ?? '';
    $direction = $data['direction'] ?? '';
    $tp = $data['tp'] ?? 0;
    $sl = $data['sl'] ?? 0;
    $lot = $data['lot'] ?? 0.01;
    $apiKey = $data['api_key'] ?? '';
    
    // Validate required fields
    if (!$mentorId || !$pair || !$direction) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }
    
    // Verify API key (optional security layer)
    $expectedKey = defined('WEBHOOK_KEY') ? WEBHOOK_KEY : 'ea_smart_trade_secret_key';
    if ($apiKey !== $expectedKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit();
    }
    
    if (DB_OFFLINE) {
        // Store in localStorage via file (demo mode)
        $signalFile = __DIR__ . '/../../signals_cache.json';
        $signals = file_exists($signalFile) ? json_decode(file_get_contents($signalFile), true) : [];
        $newSignal = [
            'id' => time(),
            'mentor_id' => $mentorId,
            'pair' => $pair,
            'direction' => $direction,
            'tp' => (float)$tp,
            'sl' => (float)$sl,
            'lot' => (float)$lot,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'PENDING'
        ];
        array_unshift($signals, $newSignal);
        file_put_contents($signalFile, json_encode($signals));
        
        echo json_encode(['success' => true, 'signal_id' => $newSignal['id']]);
        exit();
    }
    
    try {
        // Create signals table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS signals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mentor_id INT NOT NULL,
            pair VARCHAR(20) NOT NULL,
            direction ENUM('BUY', 'SELL') NOT NULL,
            tp DECIMAL(10,5) NOT NULL,
            sl DECIMAL(10,5) NOT NULL,
            lot DECIMAL(10,3) NOT NULL,
            status ENUM('PENDING', 'FILLED', 'STOPPED', 'CANCELLED') DEFAULT 'PENDING',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mentor (mentor_id),
            INDEX idx_status (status)
        )");
        
        $stmt = $conn->prepare("INSERT INTO signals (mentor_id, pair, direction, tp, sl, lot, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmt->execute([$mentorId, $pair, $direction, $tp, $sl, $lot]);
        $signalId = $conn->lastInsertId();
        
        // Also update the copy_trades table used by dashboard
        // This keeps mobile robot in sync with admin's copy trade panel
        
        echo json_encode(['success' => true, 'signal_id' => $signalId]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to store signal: ' . $e->getMessage()]);
    }
    exit();
}

// Default response
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
exit();

// Helper function to extract mentor ID from license key (demo mode)
function getMentorIdFromKey($key) {
    // Format: EAV-ABCD-123-4567
    // Extract last 4 digits as mentor ID
    if (preg_match('/EAV-[A-Z0-9]{4}-(\d{3})-(\d{4})/', $key, $matches)) {
        return $matches[2] % 9000 + 1000; // Generate consistent ID
    }
    return abs(crc32($key)) % 9000 + 1000;
}
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// API Endpoints
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// License validation endpoint
if ($uri[0] === 'api' && $uri[1] === 'validate-license' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $licenseKey = $data['licenseKey'] ?? '';
    
    if (!$licenseKey) {
        http_response_code(400);
        echo json_encode(['error' => 'License key is required']);
        exit();
    }
    
    // Check license in database
    $stmt = $pdo->prepare("SELECT * FROM licence_keys WHERE key = ? AND is_active = 1");
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch();
    
    if (!$license) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or inactive license key']);
        exit();
    }
    
    // Get user info
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$license['user_id']]);
    $user = $userStmt->fetch();
    
    // Update last used
    $updateStmt = $pdo->prepare("UPDATE licence_keys SET last_used = NOW() WHERE id = ?");
    $updateStmt->execute([$license['id']]);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ],
        'license' => [
            'id' => $license['id'],
            'expires_at' => $license['expires_at']
        ]
    ]);
    exit();
}

// Get signals endpoint (for polling)
if ($uri[0] === 'api' && $uri[1] === 'signals' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $licenseKey = $_GET['licenseKey'] ?? '';
    
    if (!$licenseKey) {
        http_response_code(400);
        echo json_encode(['error' => 'License key is required']);
        exit();
    }
    
    // Validate license
    $stmt = $pdo->prepare("SELECT user_id FROM licence_keys WHERE key = ? AND is_active = 1");
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch();
    
    if (!$license) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or inactive license key']);
        exit();
    }
    
    // Get mentor ID associated with this license/user
    $mentorStmt = $pdo->prepare("SELECT mentor_id FROM users WHERE id = ?");
    $mentorStmt->execute([$license['user_id']]);
    $mentor = $mentorStmt->fetch();
    
    if (!$mentor) {
        http_response_code(404);
        echo json_encode(['error' => 'Mentor not found']);
        exit();
    }
    
    // Get recent signals for this mentor (simulating localStorage prefixed with mentor ID)
    // In a real implementation, you might have a signals table or read from a different source
    $signalsStmt = $pdo->prepare("
        SELECT * FROM trade_signals 
        WHERE mentor_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY created_at DESC
    ");
    $signalsStmt->execute([$mentor['mentor_id']]);
    $signals = $signalsStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'signals' => $signals
    ]);
    exit();
}

// Default route
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
?>