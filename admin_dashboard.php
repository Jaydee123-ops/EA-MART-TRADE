<?php
session_start();

// Simple admin authentication (replace with proper login)
// For demo purposes, you'd need to create an admin login system
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

require_once 'config/db_connect.php';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved', approved_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $msg = "User approved";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $msg = "User rejected";
    }
    header("Location: admin_dashboard.php?msg=" . urlencode($msg));
    exit;
}

// Fetch pending users
$result = $conn->query("SELECT id, fullname, displayname, email, created_at FROM users WHERE status = 'pending' ORDER BY created_at ASC");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - EA SMART TRADE</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body { background: #1a1a1a; color: #e0e0e0; font-family: 'Mulish', sans-serif; }
        .table { color: #e0e0e0; }
        .table th { border-color: #444; }
        .table td { border-color: #444; vertical-align: middle; }
        .badge-pending { background: #ffc107; color: #000; }
        .badge-approved { background: #28a745; }
        .badge-rejected { background: #dc3545; }
        .btn-approve { background: #28a745; color: white; border: none; padding: 5px 15px; border-radius: 5px; }
        .btn-reject { background: #dc3545; color: white; border: none; padding: 5px 15px; border-radius: 5px; }
        .btn-approve:hover { background: #1e7e34; }
        .btn-reject:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Admin Dashboard - Pending Approvals</h2>
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-info"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <table class="table table-dark">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>WhatsApp</th>
                    <th>Social Links</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td>
                        <?= htmlspecialchars($user['fullname']) ?><br>
                        <small><?= htmlspecialchars($user['displayname']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['whatsapp']) ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($user['instagram']) ?>" target="_blank" class="text-light">Instagram</a><br>
                        <a href="<?= htmlspecialchars($user['tiktok']) ?>" target="_blank" class="text-light">TikTok</a><br>
                        <a href="<?= htmlspecialchars($user['telegram']) ?>" target="_blank" class="text-light">Telegram</a>
                    </td>
                    <td><?= date('M j, Y g:i a', strtotime($user['created_at'])) ?></td>
                    <td>
                        <a href="?action=approve&id=<?= $user['id'] ?>" class="btn-approve">Approve</a>
                        <a href="?action=reject&id=<?= $user['id'] ?>" class="btn-reject" onclick="return confirm('Reject this user?')">Reject</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <a href="logout.php" class="btn btn-secondary">Logout</a>
    </div>
</body>
</html>
<?php
$conn->close();
?>