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

if ($comment_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid comment ID']);
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
        echo json_encode(['error' => 'You can only delete your own comments']);
        exit();
    }

    // Delete the comment
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error deleting comment']);
}
?> 