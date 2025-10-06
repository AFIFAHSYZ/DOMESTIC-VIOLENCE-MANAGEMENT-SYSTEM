<?php

$timeout_duration = 1800; // 30 minutes

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        if (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration) {
            session_unset();
            session_destroy();
            header("Location: ../login.php?timeout=1");
            exit();
        }
    }
    $_SESSION['LAST_ACTIVITY'] = time();
} else {
    header("Location: ../login.php");
    exit();
}
?>