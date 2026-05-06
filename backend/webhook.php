<?php
// Webhook endpoint - receives signals from admin dashboard
// POST /backend/webhook.php
header('Content-Type: application/json');

require_once __DIR__ . '/config/db_connect.php';

// Simple API key authentication
define('WEBHOOK_KEY', 'ea_smart_trade_secret_key_2024');

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

$mentorId = $data['mentor_id'] ?? null;
$pair = $data['pair'] ?? '';
$direction = $data['direction'] ?? '';
$tp = $data['tp'] ?? 0;
$sl = $data['sl'] ?? 0;
$lot = $data['lot'] ?? 0.01;
$apiKey = $data['api_key'] ?? '';

// Validate
if (!$mentorId || !$pair || !$direction) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

// Verify API key
if ($apiKey !== WEBHOOK_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit();
}

if (DB_OFFLINE) {
    // Demo mode - store in JSON file
    $cacheFile = __DIR__ . '/signals_cache.json';
    $signals = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
    
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
    file_put_contents($cacheFile, json_encode($signals));
    
    echo json_encode(['success' => true, 'signal_id' => $newSignal['id']]);
    exit();
}

try {
    global $conn;
    
    // Ensure signals table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS signals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mentor_id INT NOT NULL,
        pair VARCHAR(20) NOT NULL,
        direction ENUM('BUY','SELL') NOT NULL,
        tp DECIMAL(10,5) NOT NULL,
        sl DECIMAL(10,5) NOT NULL,
        lot DECIMAL(10,3) NOT NULL,
        status ENUM('PENDING','FILLED','STOPPED','CANCELLED') DEFAULT 'PENDING',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mentor (mentor_id),
        INDEX idx_status (status)
    )");
    
    // Insert signal
    $stmt = $conn->prepare("
        INSERT INTO signals (mentor_id, pair, direction, tp, sl, lot, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
    ");
    $stmt->execute([$mentorId, $pair, $direction, $tp, $sl, $lot]);
    $signalId = $conn->lastInsertId();
    
    echo json_encode(['success' => true, 'signal_id' => $signalId]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
