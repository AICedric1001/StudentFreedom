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

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'] ?? null;

// Validate post ID
if (!$post_id || !is_numeric($post_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit();
}

try {
    // Check if the post belongs to the current user
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post || $post['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // Begin transaction
    $conn->beginTransaction();

    // Delete related records first
    $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
    $stmt->execute([$post_id]);

    $stmt = $conn->prepare("DELETE FROM dislikes WHERE post_id = ?");
    $stmt->execute([$post_id]);

    $stmt = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
    $stmt->execute([$post_id]);

    // Finally, delete the post
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Post deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting post']);
} 