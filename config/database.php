<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'brightvi_root');
define('DB_PASS', 'Art@dalvik197');
define('DB_NAME', 'brightvi_BVMain');

// Create connection
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Initialize database tables
function initDatabase() {
    $conn = getConnection();
    
    // Create users table if not exists
    $users_table = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        number VARCHAR(20) DEFAULT '0',
        user_id VARCHAR(50) DEFAULT '0',
        email VARCHAR(255) NOT NULL UNIQUE,
        whatsapp VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        user_role VARCHAR(50) DEFAULT 'student',
        user_class VARCHAR(50),
        joined_year VARCHAR(10),
        institute VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    // Create reservations table if not exists
    $reservations_table = "CREATE TABLE IF NOT EXISTS reservations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        full_name VARCHAR(255),
        callname VARCHAR(255),
        cername VARCHAR(255),
        name_with_initials VARCHAR(255),
        nic_number VARCHAR(50),
        whatsapp_number VARCHAR(20),
        postal_address TEXT,
        participants INT(11) DEFAULT 1,
        exam_date DATE,
        exam_time TIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        nic VARCHAR(255),
        phototaken INT(1) DEFAULT 0,
        approved INT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $conn->query($users_table);
    $conn->query($reservations_table);
    
    $conn->close();
}

// Call initDatabase when this file is included
initDatabase();
?>