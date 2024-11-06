<?php
session_start();
require 'config.php'; // Ensure this file correctly sets up the $conn variable

// Check if the post_id is provided
if (!isset($_GET['post_id'])) {
    echo "Post ID not provided.";
    exit();
}

$post_id = $_GET['post_id'];

// Fetch the post details
$query = "
    SELECT bp.*, u.username, u.profile_image AS user_profile_image, b.bubble_name AS bubble_name, b.profile_image AS bubble_profile_image, b.description AS bubble_description, b.created_at AS bubble_created_at
    FROM bubble_posts bp
    JOIN users u ON bp.user_id = u.id
    JOIN bubbles b ON bp.bubble_id = b.id
    WHERE bp.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

// Close the statement
$stmt->close();

// Check if the post exists
if (!$post) {
    echo "Post not found.";
    exit();
}

// Convert the image to base64 if it exists
$image_base64 = '';
if (!empty($post['image'])) {
    $image_base64 = 'data:image/jpeg;base64,' . base64_encode($post['image']);
}

// Convert the bubble profile image to base64 if it exists
$profile_image_base64 = '';
if (!empty($post['bubble_profile_image'])) {
    $profile_image_base64 = 'data:image/jpeg;base64,' . base64_encode($post['bubble_profile_image']);
}

// Fetch the comments for the post
$query = "
    SELECT c.*, u.username, u.profile_image
    FROM bubble_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$comments_result = $stmt->get_result();
$comments = $comments_result->fetch_all(MYSQLI_ASSOC);

// Close the statement and connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bubble Posts</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .dropdown:hover .dropdown-menu { display: block; }
        .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; }
        .sidebar {
      width: 80px;
      transition: width 0.3s;
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      overflow: hidden;
    }
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1000;
    }
    .content {
      margin-top: 64px; /* Adjust based on navbar height */
      margin-left: 80px; /* Adjust based on sidebar width */
      transition: margin-left 0.3s;
    }
    .right-sidebar {
      position: fixed;
      right: 0;
      height: calc(100% - 64px); /* Adjust based on navbar height */
      overflow-y: auto;
      z-index: 100;
      margin-top: 80px;
    }
  </style>
</head>
<body class="bg-blue-50">
  <!-- Navbar -->
  <nav class="navbar bg-sky-800 text-white p-4 flex justify-between items-center">
    <div class="flex items-center">
      <a href="indexTimeline.php"><img src="../public/ps.png" alt="Peerync Logo" class="h-10 w-10"></a>
    </div>
    <div class="flex items-center">
      <a href="exploreBubble.php" class="ml-4 hover:bg-blue-400 p-2 rounded">
        <i class="fas fa-globe fa-lg"></i>
      </a>
      <a href="indexBubble.php" class="ml-4 hover:bg-blue-400 p-2 rounded">
        <i class="fas fa-comments fa-lg"></i>
      </a>
      <div class="relative ml-4">
        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image" class="w-10 h-10 rounded-full cursor-pointer" id="profileImage">
        <div class="dropdown-menu absolute right-0 mt-1 w-48 bg-white border border-gray-300 rounded shadow-lg hidden">
          <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
          <a href="logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
        </div>
      </div>
    </div>
  </nav>

<!-- Leftmost Sidebar -->
<div id="sidebar" class="fixed top-0 left-0 h-full mt-10 bg-sky-100 text-white z-50 flex flex-col items-center sidebar transition-all duration-300 shadow-lg border-r border-gray-300" style="width: 64px;">
    <ul id="bubble-list" class="space-y-4 mt-10">
        <!-- Bubble list will be populated by JavaScript -->
    </ul>
</div>

    <!-- Main Container -->
    <div id="main-content" class="content pt-8 flex justify-center">
        <div class="flex justify-center w-full max-w-6xl">
            <div class="p-4 mx-auto w-full max-w-4xl">
                <div class="bg-white p-4 shadow rounded mb-4">
                    <h2 class="text-xl font-bold"><?= htmlspecialchars($post['title']) ?></h2>
                    <p class="text-sm text-gray-500">Posted by <?= htmlspecialchars($post['username']) ?> in <?= htmlspecialchars($post['bubble_name']) ?></p>
                    <p class="mt-2"><?= htmlspecialchars($post['message']) ?></p>
                    <?php if (!empty($image_base64)): ?>
                        <img src="<?= $image_base64 ?>" alt="Post Image" class="mt-4 w-full h-auto rounded">
                    <?php endif; ?>
                </div>

                <!-- Comment form -->
                <div class="bg-white rounded-lg shadow-md mb-4">
                    <div class="p-4">
                        <h3 class="font-bold mb-2">Comment right here</h3>
                        <form action="postComment.php" method="post">
                            <textarea name="comment" class="w-full p-2 border rounded" rows="4" placeholder="What are your thoughts?"></textarea>
                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($post_id) ?>">
                            <input type="hidden" name="bubble_id" value="<?= htmlspecialchars($post['bubble_id']) ?>">
                            <button type="submit" class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Comment</button>
                        </form>
                    </div>
                </div>

                <!-- Display comments -->
                <div class="bg-white p-4 shadow rounded mb-4">
                    <h3 class="font-bold mb-2">Comments</h3>
                    <?php foreach ($comments as $comment): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($comment['username']) ?>:</p>
                            <p class="mt-1"><?= htmlspecialchars($comment['comment']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- About Bubble Section -->
            <div class="w-1/5 p-4 space-y-4">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-4">
                        <h3 class="font-bold mb-4">About Bubble</h3>
                        <div class="flex items-center mb-4">
                            <?php if (!empty($profile_image_base64)): ?>
                                <img src="<?= $profile_image_base64 ?>" alt="<?= htmlspecialchars($post['bubble_name']) ?>" class="w-10 h-10 rounded-full mr-2">
                            <?php else: ?>
                                <img src="default-profile.png" alt="Default Profile Image" class="w-10 h-10 rounded-full mr-2">
                            <?php endif; ?>
                            <span class="font-bold"><?= htmlspecialchars($post['bubble_name']) ?></span>
                        </div>
                        <p class="text-sm mb-4"><?= htmlspecialchars($post['bubble_description']) ?></p>
                        <p class="text-xs text-gray-500">Created: <?= htmlspecialchars($post['bubble_created_at']) ?></p>
                        <button class="mt-4 w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create Post</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle dropdown menu
        document.getElementById('profileImage').addEventListener('click', function() {
            const dropdownMenu = this.nextElementSibling;
            dropdownMenu.classList.toggle('hidden');
        });

        // Fetch the list of bubbles the user has joined
        function fetchJoinedBubbles() {
            fetch("joinedBubble.php")
            .then(response => response.json())
            .then(data => {
                const bubbleList = document.getElementById("bubble-list");
                bubbleList.innerHTML = "";
                data.bubbles.forEach(bubble => {
                const bubbleItem = document.createElement("li");
                bubbleItem.className = "bubble-container";
                bubbleItem.innerHTML = `
                    <a href="bubblePage.php?bubble_id=${bubble.id}" class="block p-2 text-center hover:bg-gray-700">
                    <img src="data:image/jpeg;base64,${bubble.profile_image}" alt="${bubble.bubble_name}" class="w-10 h-10 rounded-full mx-auto">
                 
                    </a>
                `;
                bubbleList.appendChild(bubbleItem);
                });
            })
            .catch(error => {
                console.error("Error fetching joined bubbles:", error);
            });
        }

        document.addEventListener("DOMContentLoaded", fetchJoinedBubbles);
    </script>
</body>
</html>