<?php
// Start the session
session_start();

// Include the database configuration file
include 'config.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Get the post ID, user ID, and comment content from the input
    $post_id = $input['post_id'];
    $user_id = $input['user_id'];
    $comment = $input['comment'];

    // Prepare the SQL statement to insert the comment
    $sql = "INSERT INTO bubble_comments (post_id, user_id, comment, created_at) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $post_id, $user_id, $comment);

    // Execute the statement
    if ($stmt->execute()) {
        // If the comment was added successfully, return a success response
        echo json_encode(['success' => true]);
    } else {
        // If there was an error adding the comment, return an error response
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    // Close the statement
    $stmt->close();
} else {
    // If the request method is not POST, return an error response
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

// Close the database connection
$conn->close();
?>