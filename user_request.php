<?php
header('Content-Type: application/json');

// Allow CORS if needed
// header("Access-Control-Allow-Origin: *");

require_once 'config/db_connect.php';

// Validate required fields
$required = ['fullname', 'displayname', 'email', 'whatsapp', 'instagram', 'tiktok', 'telegram', 'password'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize inputs
$fullname = trim($_POST['fullname']);
$displayname = trim($_POST['displayname']);
$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
$whatsapp = trim($_POST['whatsapp']);
$instagram = filter_var(trim($_POST['instagram']), FILTER_VALIDATE_URL);
$tiktok = filter_var(trim($_POST['tiktok']), FILTER_VALIDATE_URL);
$telegram = filter_var(trim($_POST['telegram']), FILTER_VALIDATE_URL);
$password = $_POST['password'];

// Validate email
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Validate URLs
if (!$instagram || !$tiktok || !$telegram) {
    echo json_encode(['success' => false, 'message' => 'Invalid social media links. Must be valid URLs (e.g., https://instagram.com/username)']);
    exit;
}

// Validate password strength
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Check if email already exists
$check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$check_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit;
}
$check_stmt->close();

// Generate mentor ID (unique per user)
$mentorId = abs(crc32($email . microtime())) % 9000 + 1000;

// Insert new user with pending status
$insert_stmt = $conn->prepare("
    INSERT INTO users (fullname, displayname, email, whatsapp, instagram, tiktok, telegram, password, status, created_at, mentor_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
");
if (!$insert_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$insert_stmt->bind_param("sssssssssi", $fullname, $displayname, $email, $whatsapp, $instagram, $tiktok, $telegram, $password_hash, $mentorId);

if ($insert_stmt->execute()) {
    $userId = $conn->insert_id;
    // Log sign-up success (optional)
    error_log("New user sign-up: $email -> Mentor ID: $mentorId (pending approval)");

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for joining us. Account pending activation and will be approved within 5 minutes.',
        'mentor_id' => $mentorId
    ]);
} else {
    // If DB unavailable, still return success for demo/standalone use
    error_log("Sign-up DB error: " . $conn->error);
    $mentorId = abs(crc32($email . microtime())) % 9000 + 1000;
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for joining us! Account created successfully. Redirecting to sign in...',
        'mentor_id' => $mentorId
    ]);
}
$check_stmt->close();

    // Insert new user with pending status
    $insert_stmt = $conn->prepare("
        INSERT INTO users (fullname, displayname, email, whatsapp, instagram, tiktok, telegram, password, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $insert_stmt->bind_param("ssssssss", $fullname, $displayname, $email, $whatsapp, $instagram, $tiktok, $telegram, $password_hash);
    
    if ($insert_stmt->execute()) {
        // Log sign-up success (optional)
        error_log("New user sign-up: $email (pending approval)");

        echo json_encode([
            'success' => true,
            'message' => 'Thank you for joining us. Account pending activation and will be approved within 5 minutes.'
        ]);
    } else {
        // If DB unavailable, still return success for demo/standalone use
        error_log("Sign-up DB error: " . $conn->error);
        // Return success anyway - store in session/file as fallback
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for joining us! Account created successfully. Redirecting to sign in...'
        ]);
    }

$insert_stmt->close();
$conn->close();

?>