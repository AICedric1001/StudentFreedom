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
$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($comment_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid comment ID']);
    exit();
}

if (empty($content)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Comment cannot be empty']);
    exit();
}

try {
    // First check if the comment belongs to the user
    $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Comment not found']);
        exit();
    }

    if ($comment['user_id'] !== $_SESSION['user_id']) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'You can only edit your own comments']);
        exit();
    }

    // Update the comment
    $stmt = $conn->prepare("UPDATE comments SET content = ? WHERE id = ?");
    $stmt->execute([$content, $comment_id]);

    // Get the updated comment with user info
    $stmt = $conn->prepare("
        SELECT c.*, u.username 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format the date
    $comment['created_at'] = date('M d, Y h:i A', strtotime($comment['created_at']));

    header('Content-Type: application/json');
    echo json_encode($comment);

} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error updating comment']);
}
?> 