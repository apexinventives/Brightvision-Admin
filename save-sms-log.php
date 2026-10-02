<?php
require_once 'config/session.php';
redirectIfNotLoggedIn();

// Check authentication
if (!canAccess('reservations', 'sms')) {
    http_response_code(403);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($data['logEntry'])) {
    $logFile = 'sms_logs.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    // Format: [Timestamp] ID: X, Name: Y, Phone: Z, Status: W
    $logEntry = $data['logEntry'];
    
    // Append to file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also save to a daily file for better organization
    $dailyFile = 'sms_logs_' . date('Y-m-d') . '.txt';
    file_put_contents($dailyFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
}
?>
