<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

if (isset($_GET['id'])) {
    $conn = getConnection();
    $id = $_GET['id'];
    
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $stmt->close();
    $conn->close();
}

header('Location: reservations.php');
exit();
?>