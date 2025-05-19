<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

// Accept both JSON and form POST
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $data = json_decode(file_get_contents('php://input'), true);
    $bubbleId = isset($data['bubble_id']) ? intval($data['bubble_id']) : 0;
} else {
    $bubbleId = isset($_POST['bubble_id']) ? intval($_POST['bubble_id']) : 0;
}

if (!$bubbleId) {
    echo json_encode(['success' => false, 'message' => 'Bubble ID is required']);
    exit;
}

// Only allow deletion if the user is the creator
$stmt = $conn->prepare("DELETE FROM bubbles WHERE id = ? AND creator_id = ?");
$stmt->bind_param("ii", $bubbleId, $userId);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed or not authorized']);
}
$stmt->close();
$conn->close();
?>
