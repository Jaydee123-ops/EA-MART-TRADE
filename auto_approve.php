<?php
/**
 * Auto Approve Script
 * Automatically approves user accounts that have been pending for more than 5 minutes
 * Set up a cron job to run this script every minute
 */

require_once 'config/db_connect.php';

// Approve users that were created more than 5 minutes ago and are still pending
$sql = "
    UPDATE users
    SET status = 'approved', approved_at = NOW()
    WHERE status = 'pending'
    AND created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
";

if ($conn->query($sql) === TRUE) {
    $affected = $conn->affected_rows;
    if ($affected > 0) {
        error_log("Auto-approved $affected user(s) at " . date('Y-m-d H:i:s'));
    }
    // Output for cron monitoring
    echo "Approved $affected user(s)\n";
} else {
    error_log("Auto-approve error: " . $conn->error);
    echo "Error: " . $conn->error . "\n";
}

$conn->close();

?>