<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

// Get and validate input
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$is_anonymous = isset($_POST['is_anonymous']) ? (int)$_POST['is_anonymous'] : 1; // Default to anonymous

if (empty($title) || empty($content) || $category_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'All fields are required']);
    exit();
}

try {
    // Insert the post
    $stmt = $conn->prepare("
        INSERT INTO posts (user_id, category_id, title, content, is_anonymous, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $category_id, $title, $content, $is_anonymous]);

    // Get the newly created post with user info and category color
    $post_id = $conn->lastInsertId();
    $stmt = $conn->prepare("
        SELECT p.*, u.username, c.name as category_name, c.color 
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format the date
    $post['created_at'] = date('M d, Y h:i A', strtotime($post['created_at']));

    header('Content-Type: application/json');
    echo json_encode($post);

} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error creating post']);
}
?> 