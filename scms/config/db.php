<?php
// Strict Security: Secure PDO connection
$host = '127.0.0.1'; // Forced IPv4 for speed and security
$port = '3307';      // Change to 3307 if required by your XAMPP configuration
$db   = 'scms';      
$user = 'root';      
$pass = '';          
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Fail securely
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Prevent SQL Injection completely
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // =========================================================================
    // 🔥 ENTERPRISE SELF-HEALING ENGINE (Runs invisibly in the background) 🔥
    // =========================================================================

    // 1. Ensure the 'phone' column exists for contact tracing
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($colCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD phone VARCHAR(20) DEFAULT NULL AFTER email");
    }

    // 2. Ensure Staff Profiles table exists (Fixes the Quick Reg crash)
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        designation VARCHAR(100),
        department VARCHAR(100),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Ensure Announcements table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        target_audience ENUM('all', 'student', 'faculty', 'staff') DEFAULT 'all',
        posted_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 4. Ensure Documents table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50),
        uploaded_by INT,
        category VARCHAR(50) DEFAULT 'Notes',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 5. Zero-Setup Master Admin Injection 
    // If the database is entirely empty, it creates the supreme admin automatically.
    $adminCheck = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($adminCheck->rowCount() == 0) {
        $def_pass = password_hash('Admin@123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (name, email, password, role) VALUES ('System Admin', 'admin@gmail.com', '$def_pass', 'admin')");
    }

} catch (\PDOException $e) {
    die("<div style='text-align:center; padding: 40px; background: #ffebee; color: #c62828; font-family: sans-serif; border-bottom: 5px solid #c62828;'>
            <h2 style='margin-bottom: 10px;'>Database Connection Blocked</h2>
            <p><strong>System Error:</strong> " . $e->getMessage() . "</p>
            <p style='font-size: 0.9em; margin-top: 20px;'>Please ensure XAMPP MySQL is running and the 'scms' database is created.</p>
         </div>");
}
?>