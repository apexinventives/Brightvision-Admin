<?php
session_start();
require_once __DIR__ . '/permissions.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    $account = currentAccount();
    return $account && $account['is_active'] && $account['role'] === 'admin';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    $account = currentAccount();
    if (!$account || !$account['is_active']) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }
    enforcePagePermission();
}

function redirectIfNotAdmin() {
    redirectIfNotLoggedIn();
    if (!isAdmin()) {
        denyPermission();
    }
}

// Update last login time
function updateLastLogin($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}
?>
