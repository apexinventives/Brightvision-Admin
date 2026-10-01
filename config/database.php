<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
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

    // Reusable courses for student payment plans.
    $payment_courses_table = "CREATE TABLE IF NOT EXISTS payment_courses (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        course_number VARCHAR(50) NOT NULL UNIQUE,
        course_name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    // Student payment plans and their individual instalments.
    $payment_plans_table = "CREATE TABLE IF NOT EXISTS payment_plans (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        course_id INT(11) NULL,
        student_user_id INT(11) NULL,
        student_name VARCHAR(255) NOT NULL,
        student_id_number VARCHAR(50) NOT NULL,
        payment_method ENUM('full', 'half', 'quarter', 'scholarship', 'free_card') NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes TEXT NULL,
        created_by INT(11) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_payment_course (course_id),
        INDEX idx_payment_student_id (student_id_number),
        INDEX idx_payment_method (payment_method),
        CONSTRAINT fk_payment_plan_course FOREIGN KEY (course_id) REFERENCES payment_courses(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB";

    $payment_installments_table = "CREATE TABLE IF NOT EXISTS payment_installments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        payment_plan_id INT(11) NOT NULL,
        installment_number INT(11) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_date DATE NOT NULL,
        is_paid TINYINT(1) NOT NULL DEFAULT 0,
        paid_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_plan_installment (payment_plan_id, installment_number),
        INDEX idx_installment_date (payment_date),
        INDEX idx_installment_paid (is_paid),
        CONSTRAINT fk_installment_plan FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id) ON DELETE CASCADE
    ) ENGINE=InnoDB";
    
    $conn->query($users_table);
    $conn->query($reservations_table);
    $conn->query($payment_courses_table);
    $conn->query($payment_plans_table);
    $conn->query($payment_installments_table);

    // Upgrade installations that already had the payments table before courses were added.
    $course_column = $conn->query("SHOW COLUMNS FROM payment_plans LIKE 'course_id'");
    if ($course_column && $course_column->num_rows === 0) {
        $conn->query("ALTER TABLE payment_plans ADD course_id INT(11) NULL AFTER id, ADD INDEX idx_payment_course (course_id)");
    }
    $course_fk = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_plans' AND CONSTRAINT_NAME = 'fk_payment_plan_course' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    if ($course_fk && $course_fk->num_rows === 0) {
        $conn->query("ALTER TABLE payment_plans ADD CONSTRAINT fk_payment_plan_course FOREIGN KEY (course_id) REFERENCES payment_courses(id) ON UPDATE CASCADE ON DELETE RESTRICT");
    }
    
    $conn->close();
}

// Call initDatabase when this file is included
initDatabase();
?>
