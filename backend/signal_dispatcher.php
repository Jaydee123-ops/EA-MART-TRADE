<?php
// Signal Dispatcher - Runs continuously to push signals to WebSocket server
// This script polls the database for new signals and sends them to WebSocket clients
// Run with: php signal_dispatcher.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db_connect.php';

class SignalDispatcher {
    private $lastCheck;
    private $processedSignals = [];
    private $dispatcherFile;
    
    public function __construct() {
        $this->dispatcherFile = __DIR__ . '/dispatcher_state.json';
        $this->loadState();
        
        echo "🚀 Signal Dispatcher Started\n";
        echo "📡 Monitoring for new signals...\n\n";
        
        $this->run();
    }
    
    private function loadState() {
        if (file_exists($this->dispatcherFile)) {
            $state = json_decode(file_get_contents($this->dispatcherFile), true);
            $this->lastCheck = $state['last_check'] ?? time() - 60;
            $this->processedSignals = $state['processed_signals'] ?? [];
        } else {
            $this->lastCheck = time() - 60; // Start with 1 min ago
            $this->processedSignals = [];
        }
    }
    
    private function saveState() {
        $state = [
            'last_check' => $this->lastCheck,
            'processed_signals' => $this->processedSignals
        ];
        file_put_contents($this->dispatcherFile, json_encode($state));
    }
    
    private function run() {
        while (true) {
            $this->checkForNewSignals();
            $this->cleanupOldSignals();
            $this->saveState();
            
            // Sleep for 1 second (can be adjusted)
            sleep(1);
        }
    }
    
    private function checkForNewSignals() {
        if (DB_OFFLINE) {
            // Demo mode - check signals_cache.json
            $cacheFile = __DIR__ . '/signals_cache.json';
            if (!file_exists($cacheFile)) return;
            
            $allSignals = json_decode(file_get_contents($cacheFile), true) ?: [];
            
            foreach ($allSignals as $signal) {
                $signalId = $signal['id'] ?? $signal['timestamp'];
                if (!in_array($signalId, $this->processedSignals)) {
                    $this->pushSignalToWebSocket($signal);
                    $this->processedSignals[] = $signalId;
                }
            }
            return;
        }
        
        try {
            global $conn;
            
            // Get signals created since last check that are PENDING
            $stmt = $conn->prepare("
                SELECT * FROM signals 
                WHERE created_at > FROM_UNIXTIME(?) 
                AND status = 'PENDING'
                ORDER BY created_at DESC
            ");
            $stmt->execute([$this->lastCheck]);
            $newSignals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($newSignals) {
                echo "📡 Found " . count($newSignals) . " new signal(s)\n";
            }
            
            foreach ($newSignals as $signal) {
                $this->pushSignalToWebSocket($signal);
                $this->processedSignals[] = $signal['id'];
                
                // Keep only last 1000 processed signal IDs
                if (count($this->processedSignals) > 1000) {
                    array_shift($this->processedSignals);
                }
            }
            
            $this->lastCheck = time();
            
        } catch (Exception $e) {
            // Signals table might not exist
            // error_log("Signal dispatcher error: " . $e->getMessage());
        }
    }
    
    private function pushSignalToWebSocket($signal) {
        // Format signal for WebSocket
        $wsMessage = [
            'type' => 'new_signal',
            'signal' => [
                'id' => $signal['id'] ?? $signal['timestamp'],
                'pair' => $signal['pair'],
                'direction' => $signal['direction'],
                'tp' => (float)$signal['tp'],
                'sl' => (float)$signal['sl'],
                'lot' => (float)$signal['lot'],
                'timestamp' => $signal['created_at'] ?? $signal['timestamp']
            ]
        ];
        
        // Write to WebSocket push file (read by websocket_server.php)
        $pushFile = __DIR__ . '/websocket_push.json';
        $pushData = file_exists($pushFile) ? json_decode(file_get_contents($pushFile), true) : [];
        $pushData[] = $wsMessage;
        
        // Keep only last 100 messages
        if (count($pushData) > 100) {
            $pushData = array_slice($pushData, -100);
        }
        
        file_put_contents($pushFile, json_encode($pushData));
        
        // Also write to signals file for MT4/MT5 EA
        $this->writeSignalToFile($signal);
        
        echo "✅ Pushed signal: {$signal['pair']} {$signal['direction']} TP:{$signal['tp']} SL:{$signal['sl']} Lot:{$signal['lot']}\n";
    }
    
    private function writeSignalToFile($signal) {
        // Write to Signals.txt file that MT4/MT5 EA reads
        $filePath = __DIR__ . '/../ea/Signals.txt';
        $timestamp = date('Y.m.d H:i:s');
        
        // EA format: "pair, direction, tp, sl, lot, timestamp"
        $line = "{$signal['pair']},{$signal['direction']},{$signal['tp']},{$signal['sl']},{$signal['lot']},{$timestamp}\n";
        
        // Append to file
        file_put_contents($filePath, $line, FILE_APPEND);
        
        // Also write latest signal to a separate file for quick reading
        $latestFile = __DIR__ . '/../ea/latest_signal.json';
        file_put_contents($latestFile, json_encode([
            'pair' => $signal['pair'],
            'direction' => $signal['direction'],
            'tp' => (float)$signal['tp'],
            'sl' => (float)$signal['sl'],
            'lot' => (float)$signal['lot'],
            'timestamp' => $timestamp
        ]));
    }
    
    private function cleanupOldSignals() {
        // Remove processed signal IDs older than 24 hours
        $cutoff = time() - 86400;
        // This is simplified - in production would track timestamps
    }
}

// Start dispatcher
try {
    $dispatcher = new SignalDispatcher();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
