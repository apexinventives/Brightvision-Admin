<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function redirectIfNotAdmin() {
    redirectIfNotLoggedIn();
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
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