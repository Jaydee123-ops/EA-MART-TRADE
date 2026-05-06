<?php
session_start();
header('Content-Type: application/json');

require_once 'config/db_connect.php';

// Get POST data
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

// Validate
if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

// If database is offline, accept demo login
if (defined('DB_OFFLINE') && DB_OFFLINE === true) {
    // Database offline - accept any login for demo purposes
    // Create a mock mentor session based on email
    $mentorId = abs(crc32($email)) % 9000 + 1000;
    $_SESSION['user_id'] = $mentorId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = ucfirst(explode('@', $email)[0]);
    $_SESSION['mentor_id'] = $mentorId;
    $_SESSION['logged_in'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful (demo mode)',
        'redirect' => 'index dashboard.html',
        'mentor_id' => $mentorId
    ]);
    exit;
}

// Database is available, perform real authentication
if ($conn === null || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Find user by email
$stmt = $conn->prepare("SELECT id, fullname, displayname, email, password, status, mentor_id FROM users WHERE email = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

// Check account status
if ($user['status'] === 'pending') {
    echo json_encode([
        'success' => false,
        'message' => 'Account pending approval. Please wait 5 minutes until activation.'
    ]);
    exit;
}

if ($user['status'] === 'rejected') {
    echo json_encode(['success' => false, 'message' => 'Account rejected. Contact support']);
    exit;
}

// Generate mentor_id if not exists
$mentorId = $user['mentor_id'] ?? null;
if (!$mentorId) {
    $mentorId = abs(crc32($user['id'] . $user['email'])) % 9000 + 1000;
    $update = $conn->prepare("UPDATE users SET mentor_id = ? WHERE id = ?");
    $update->bind_param("ii", $mentorId, $user['id']);
    $update->execute();
}

// Update last login
$update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
if ($update_stmt) {
    $update_stmt->bind_param("i", $user['id']);
    $update_stmt->execute();
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['displayname'];
$_SESSION['mentor_id'] = $mentorId;
$_SESSION['logged_in'] = true;

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'redirect' => 'index dashboard.html',
    'mentor_id' => $mentorId
]);
