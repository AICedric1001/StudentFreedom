<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    // Get unread count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count]);
} catch (PDOException $e) {
    error_log("Unread count error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error getting unread count']);
} 