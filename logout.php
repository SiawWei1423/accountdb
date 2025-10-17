<?php
session_start();
require_once('db_connection.php');

// Update last_login with logout timestamp before destroying session (Malaysia time)
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    date_default_timezone_set('Asia/Kuala_Lumpur');
    $logoutTime = date('Y-m-d H:i:s');
    
    if ($_SESSION['user_type'] === 'admin') {
        $conn->query("UPDATE admin SET last_login = '$logoutTime' WHERE admin_id = " . $_SESSION['user_id']);
    } else {
        $conn->query("UPDATE user SET last_login = '$logoutTime' WHERE user_id = " . $_SESSION['user_id']);
    }
}

session_destroy();
header('Location: login.php');
exit;
?>