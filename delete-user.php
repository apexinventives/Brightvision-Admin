<?php
require_once 'config/session.php';
require_once 'config/database.php';
redirectIfNotLoggedIn();

if (isset($_GET['id'])) {
    $conn = getConnection();
    $id = $_GET['id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete user's reservations first
        $stmt = $conn->prepare("DELETE FROM reservations WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
    
    $conn->close();
}

header('Location: users.php');
exit();
?>