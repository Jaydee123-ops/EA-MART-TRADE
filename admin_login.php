<?php
session_start();

require_once 'config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            header('Location: admin_dashboard.php');
            exit;
        }
    }
    $error = 'Invalid credentials';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - EA SMART TRADE</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Mulish', sans-serif; }
        .login-card { background: rgba(30,30,30,0.95); padding: 40px; border-radius: 20px; border: 1px solid #dc3545; max-width: 400px; width: 100%; }
        .logo { font-family: 'Newsreader', serif; font-size: 2rem; color: #dc3545; text-align: center; margin-bottom: 30px; }
        .form-control { background: #2a2a2a; border: 2px solid #444; color: #e0e0e0; }
        .form-control:focus { border-color: #dc3545; background: #333; }
        .btn-primary { background: linear-gradient(135deg, #000 0%, #dc3545 100%); border: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 class="logo">EA SMART TRADE</h2>
        <h4 class="text-center mb-4">Admin Login</h4>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>