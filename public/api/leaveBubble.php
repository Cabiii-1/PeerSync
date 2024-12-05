<?php
require_once '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);
$bubble_id = $data['bubble_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$bubble_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bubble ID is required']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if user is the creator of the bubble
    $stmt = $conn->prepare("SELECT creator_id FROM bubbles WHERE bubble_id = ?");
    $stmt->bind_param("i", $bubble_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bubble = $result->fetch_assoc();

    if ($bubble && $bubble['creator_id'] == $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bubble creator cannot leave their own bubble. Please delete the bubble instead.']);
        exit;
    }

    // Remove user from bubble_members
    $stmt = $conn->prepare("DELETE FROM bubble_members WHERE user_id = ? AND bubble_id = ?");
    $stmt->bind_param("ii", $user_id, $bubble_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Successfully left the bubble']);
    } else {
        throw new Exception('Failed to leave bubble');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
