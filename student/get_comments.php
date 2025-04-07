<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Get post ID from URL
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

if ($post_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

try {
    // Get comments for the post
    $stmt = $conn->prepare("
        SELECT c.*, u.username 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.post_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($comments as &$comment) {
        $comment['created_at'] = date('M d, Y h:i A', strtotime($comment['created_at']));
    }

    header('Content-Type: application/json');
    echo json_encode($comments);

} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error fetching comments']);
}
?> 