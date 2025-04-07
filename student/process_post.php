<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user data from session
$user_id = $_SESSION['user_id'];

// Validate input
if (empty($_POST['title']) || empty($_POST['content']) || empty($_POST['category_id'])) {
    header('Location: dashboard.php?error=Please fill in all required fields');
    exit();
}

try {
    // Verify category exists
    $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->execute([$_POST['category_id']]);
    if (!$stmt->fetch()) {
        header('Location: dashboard.php?error=Invalid category selected');
        exit();
    }

    // Insert post into database
    $stmt = $conn->prepare("INSERT INTO posts (user_id, category_id, title, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $_POST['category_id'],
        $_POST['title'],
        $_POST['content']
    ]);

    header('Location: dashboard.php?success=1');
    exit();

} catch(PDOException $e) {
    header('Location: dashboard.php?error=Error creating post');
    exit();
}
?> 