<?php
session_start();
require 'config.php';


if (!isset($_POST['comment']) || !isset($_POST['post_id'])) {
    echo "Comment or Post ID not provided.";
    exit();
}
$comment = $_POST['comment'];
$post_id = $_POST['post_id'];
$user_id = $_SESSION['user_id'];
$sql = "INSERT INTO bubble_comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "Error preparing statement: " . $conn->error;
    exit();
}
$stmt->bind_param('iis', $post_id, $user_id, $comment);
if ($stmt->execute()) {
    header("Location: postDetails.php?post_id=" . $post_id);
    exit();
} else {
    echo "Error executing statement: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>
