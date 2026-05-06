<?php
session_start();
header('Content-Type: application/json');

// Adjust path since this file is in the sign in/ directory
require_once '../config/db_connect.php';

// Get POST data
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

// Validate
if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

// Find user by email
$stmt = $conn->prepare("SELECT id, fullname, displayname, email, password, status FROM users WHERE email = ?");
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

// Update last login
$update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$update_stmt->bind_param("i", $user['id']);
$update_stmt->execute();

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['displayname'];
$_SESSION['logged_in'] = true;

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'redirect' => '../index dashboard.html'
]);

$stmt->close();
$conn->close();

?>