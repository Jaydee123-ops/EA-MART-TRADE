<?php
// Get Signals endpoint - for mobile app to fetch new signals
// GET /backend/get_signals.php?licenseKey=...
header('Content-Type: application/json');

require_once __DIR__ . '/config/db_connect.php';

$licenseKey = $_GET['licenseKey'] ?? '';

if (!$licenseKey) {
    http_response_code(400);
    echo json_encode(['error' => 'License key is required']);
    exit();
}

// Validate license and get mentor ID
if (DB_OFFLINE) {
    // Demo mode - extract mentor ID from license key
    $mentorId = abs(crc32($licenseKey)) % 9000 + 1000;
} else {
    try {
        global $conn;
        $stmt = $conn->prepare("SELECT mentor_id FROM licence_keys WHERE key_value = ? AND status = 'Active'");
        $stmt->execute([$licenseKey]);
        $license = $stmt->fetch();
        
        if (!$license) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid license key']);
            exit();
        }
        
        $mentorId = $license['mentor_id'] ?? $license['id'];
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit();
    }
}

// Get signals for this mentor
$signals = [];

if (DB_OFFLINE) {
    // Read from JSON file
    $cacheFile = __DIR__ . '/signals_cache.json';
    if (file_exists($cacheFile)) {
        $allSignals = json_decode(file_get_contents($cacheFile), true) ?: [];
        // Filter by mentor_id
        $signals = array_filter($allSignals, function($sig) use ($mentorId) {
            return $sig['mentor_id'] == $mentorId;
        });
        $signals = array_values($signals);
    }
} else {
    try {
        global $conn;
        $stmt = $conn->prepare("
            SELECT * FROM signals 
            WHERE mentor_id = ? 
            AND status = 'PENDING'
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$mentorId]);
        $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist
        $signals = [];
    }
}

echo json_encode([
    'success' => true,
    'signals' => $signals,
    'mentor_id' => $mentorId,
    'count' => count($signals)
]);
