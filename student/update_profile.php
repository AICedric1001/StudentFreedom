<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify current password
if (!password_verify($_POST['current_password'], $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit();
}

try {
    // Update user profile
    $stmt = $conn->prepare("
        UPDATE users 
        SET first_name = ?, 
            last_name = ?, 
            department = ?, 
            year_level = ?, 
            bio = ? 
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['department'],
        $_POST['year_level'],
        $_POST['bio'],
        $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Profile update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating profile']);
} 