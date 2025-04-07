<?php
require_once 'check_admin.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Basic validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            throw new Exception("All fields are required");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already exists");
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, 'student', 'active', NOW())
        ");
        $stmt->execute([$first_name, $last_name, $email, $hashed_password]);

        // Redirect back to users page with success message
        header("Location: users.php?success=User added successfully");
        exit();

    } catch(Exception $e) {
        // Redirect back to users page with error message
        header("Location: users.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // If someone tries to access this file directly without POST data
    header("Location: users.php");
    exit();
} 