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
$notification_id = $data['notification_id'] ?? null;

// Validate notification ID
if (!$notification_id || !is_numeric($notification_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit();
}

try {
    // Check if the notification belongs to the current user
    $stmt = $conn->prepare("SELECT user_id FROM notifications WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification || $notification['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // Delete notification
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$notification_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Notification deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting notification']);
} 