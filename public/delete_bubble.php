<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get bubble ID from POST request
$data = json_decode(file_get_contents('php://input'), true);
$bubble_id = $data['bubble_id'] ?? null;

if (!$bubble_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Bubble ID is required']);
    exit;
}

// Check if user has permission to delete the bubble (is owner)
$check_owner = $conn->prepare("SELECT creator_id FROM bubbles WHERE id = ?");
$check_owner->bind_param("i", $bubble_id);
$check_owner->execute();
$result = $check_owner->get_result();
$bubble = $result->fetch_assoc();

if (!$bubble || $bubble['creator_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to delete this bubble']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete all messages
    $delete_messages = $conn->prepare("DELETE FROM bubble_message WHERE bubble_id = ?");
    $delete_messages->bind_param("i", $bubble_id);
    $delete_messages->execute();

    // Delete all posts
    $delete_posts = $conn->prepare("DELETE FROM bubble_posts WHERE bubble_id = ?");
    $delete_posts->bind_param("i", $bubble_id);
    $delete_posts->execute();

    // Delete all member associations
    $delete_members = $conn->prepare("DELETE FROM user_bubble WHERE bubble_id = ?");
    $delete_members->bind_param("i", $bubble_id);
    $delete_members->execute();

    // Finally, delete the bubble itself
    $delete_bubble = $conn->prepare("DELETE FROM bubbles WHERE id = ?");
    $delete_bubble->bind_param("i", $bubble_id);
    $delete_bubble->execute();

    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    http_response_code(500);
    error_log('Failed to delete bubble: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to delete bubble']);
}
