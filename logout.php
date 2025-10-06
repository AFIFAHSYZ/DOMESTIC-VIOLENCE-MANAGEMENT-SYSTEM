<?php
session_start();

require 'conn.php';

// Log before clearing the session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, event_type, description)
        VALUES (?, 'logout', 'User logged out')
    ");
    $stmt->execute([$user_id]);
}

// Now clear and destroy the session
session_unset();
session_destroy();

header("Location: login.php");
exit;
