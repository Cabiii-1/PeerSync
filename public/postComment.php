<?php
session_start();
require 'config.php'; // Ensure this file correctly sets up the $conn variable

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit();
}

// Get the comment and post ID from the POST data
if (!isset($_POST['comment']) || !isset($_POST['post_id'])) {
    echo "Comment or Post ID not provided.";
    exit();
}

$comment = $_POST['comment'];
$post_id = $_POST['post_id'];
$user_id = $_SESSION['user_id']; // Assuming you store the logged-in user's ID in the session

// Prepare the SQL statement to insert the comment
$sql = "INSERT INTO bubble_comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "Error preparing statement: " . $conn->error;
    exit();
}

// Bind the parameters
$stmt->bind_param('iis', $post_id, $user_id, $comment);

// Execute the statement
if ($stmt->execute()) {
    // Redirect back to the post details page
    header("Location: postDetails.php?post_id=" . $post_id);
    exit();
} else {
    echo "Error executing statement: " . $stmt->error;
}

// Close the statement and connection
$stmt->close();
$conn->close();
?>