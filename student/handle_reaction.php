<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'] ?? null;
$action = $data['action'] ?? null;

// Validate input
if (!is_numeric($post_id) || !in_array($action, ['like', 'dislike'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

try {
    // Get post owner's ID for notification
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post_owner_id = $stmt->fetchColumn();

    if ($action === 'like') {
        // Check if user already liked the post
        $stmt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $_SESSION['user_id']]);
        $already_liked = $stmt->fetchColumn() > 0;

        if ($already_liked) {
            // Remove like
            $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
            $user_liked = false;
        } else {
            // Add like and remove dislike if exists
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
            
            // Remove dislike if exists
            $stmt = $conn->prepare("DELETE FROM dislikes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
            
            $conn->commit();
            $user_liked = true;
            
            // Create notification for post owner if not the same user
            if ($post_owner_id != $_SESSION['user_id']) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, post_id, type, from_user_id) VALUES (?, ?, 'like', ?)");
                $stmt->execute([$post_owner_id, $post_id, $_SESSION['user_id']]);
            }
        }
        $user_disliked = false;
    } else {
        // Check if user already disliked the post
        $stmt = $conn->prepare("SELECT COUNT(*) FROM dislikes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $_SESSION['user_id']]);
        $already_disliked = $stmt->fetchColumn() > 0;

        if ($already_disliked) {
            // Remove dislike
            $stmt = $conn->prepare("DELETE FROM dislikes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
            $user_disliked = false;
        } else {
            // Add dislike and remove like if exists
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("INSERT INTO dislikes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
            
            $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
            
            $conn->commit();
            $user_disliked = true;
        }
        $user_liked = false;
    }

    // Get updated counts
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM likes WHERE post_id = ?) as like_count,
        (SELECT COUNT(*) FROM dislikes WHERE post_id = ?) as dislike_count");
    $stmt->execute([$post_id, $post_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'like_count' => $counts['like_count'],
        'dislike_count' => $counts['dislike_count'],
        'user_liked' => $user_liked,
        'user_disliked' => $user_disliked
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 