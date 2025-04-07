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
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($post_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

if (empty($content)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Comment cannot be empty']);
    exit();
}

try {
    // Get post owner's ID first
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Post not found']);
        exit();
    }

    // Insert the comment
    $stmt = $conn->prepare("
        INSERT INTO comments (post_id, user_id, content, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$post_id, $_SESSION['user_id'], $content]);

    // Get the newly created comment with user info
    $comment_id = $conn->lastInsertId();
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.first_name, u.last_name
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format the date
    $comment['created_at'] = date('M d, Y h:i A', strtotime($comment['created_at']));

    // Create notification for post owner if different from commenter
    if ($post['user_id'] != $_SESSION['user_id']) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, content, related_id) 
            VALUES (?, 'comment', ?, ?)
        ");
        $notification_content = $comment['username'] . ' commented on your post';
        $stmt->execute([$post['user_id'], $notification_content, $post_id]);
    }

    // Check for mentions in the comment
    preg_match_all('/@(\w+)/', $content, $mentions);
    if (!empty($mentions[1])) {
        foreach ($mentions[1] as $username) {
            // Get mentioned user's ID
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $mentioned_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($mentioned_user && $mentioned_user['id'] != $_SESSION['user_id']) {
                // Create notification for mentioned user
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id) 
                    VALUES (?, 'mention', ?, ?)
                ");
                $notification_content = $comment['username'] . ' mentioned you in a comment';
                $stmt->execute([$mentioned_user['id'], $notification_content, $post_id]);
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($comment);

} catch(PDOException $e) {
    error_log("Comment error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error posting comment']);
}
?> 