<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.html");
    exit();
}

$userId = $_SESSION['user_id'];

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $imageName = basename($_FILES['profile_image']['name']);
    $targetFilePath = $targetDir . $imageName;

    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
        $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $stmt->bind_param("si", $targetFilePath, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch user data and statistics
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM user_bubble WHERE user_id = u.id) as bubble_count,
        (SELECT COUNT(*) FROM bubble_posts WHERE user_id = u.id) as post_count
        FROM users u WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// Fetch user's joined bubbles
$bubbles_sql = "SELECT b.*, ub.joined_at,
                (SELECT COUNT(*) FROM user_bubble WHERE bubble_id = b.id) as member_count 
                FROM bubbles b 
                JOIN user_bubble ub ON b.id = ub.bubble_id 
                WHERE ub.user_id = ? 
                ORDER BY ub.joined_at DESC";
$bubbles_stmt = $conn->prepare($bubbles_sql);
$bubbles_stmt->bind_param("i", $userId);
$bubbles_stmt->execute();
$bubbles_result = $bubbles_stmt->get_result();
$userBubbles = $bubbles_result->fetch_all(MYSQLI_ASSOC);
$bubbles_stmt->close();

// Fetch user's posts with bubble information
$posts_sql = "SELECT bp.*, b.bubble_name 
              FROM bubble_posts bp 
              JOIN bubbles b ON bp.bubble_id = b.id 
              WHERE bp.user_id = ? 
              ORDER BY bp.created_at DESC";
$posts_stmt = $conn->prepare($posts_sql);
$posts_stmt->bind_param("i", $userId);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();
$userPosts = [];
while ($post = $posts_result->fetch_assoc()) {
    $userPosts[] = $post;
}
$posts_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | <?php echo htmlspecialchars($userData['username']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Background design */
        body {
            position: relative;
            background-color: #ffffff;
        }
        
        body::before {
            content: '';
            position: fixed;
            display: block;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background-color: rgb(70 130 180 / 0.1);
            margin: -50px 50px;
            z-index: -1;
        }

        body::after {
            content: '';
            position: fixed;
            display: block;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background-color: rgb(43 84 126 / 0.1);
            bottom: -50px;
            right: -50px;
            z-index: -1;
            opacity: 0.8;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-card {
            background: linear-gradient(135deg, rgb(43 84 126), rgb(70 130 180));
            border-radius: 15px;
        }

        .profile-image-container {
            position: relative;
            display: inline-block;
        }

        .profile-image-container::after {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border: 3px solid rgb(70 130 180);
            border-radius: 50%;
        }

        .tab-active {
            color: rgb(43 84 126);
            border-bottom: 3px solid rgb(43 84 126);
        }

        button[type="submit"], .btn-primary {
            background-color: rgb(70 130 180);
            transition: all 0.3s ease;
        }

        button[type="submit"]:hover, .btn-primary:hover {
            background-color: rgb(43 84 126);
        }

        #edit-profile-picture {
            background-color: rgb(70 130 180);
        }

        #edit-profile-picture:hover {
            background-color: rgb(43 84 126);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            margin: 2rem auto;
        }
        .navbar { position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; }

    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Navbar -->
    <nav class="navbar bg-secondary-100 text-white  flex justify-between items-center" style="background-color: rgb(43 84 126 / var(--tw-bg-opacity)) /* #2b547e */;">
        <div class="flex items-center">
            <a href="indexTimeline.php"><img src="../public/ps.png" alt="Peerync Logo" class="h-18 w-16"></a>
            <span class="text-2xl font-bold">PeerSync</span>
        </div>
        <div class="flex items-center space-x-4">
            <!-- Notifications Button -->
            <button id="notificationsButton" class="text-white hover:text-gray-200">
                <i class="fas fa-bell text-xl"></i>
            </button>
            <a href="exploreBubble.php" class="ml-4 hover:bg-blue-400 p-2 rounded">
                <i class="fas fa-globe fa-lg"></i>
            </a>
            <a href="indexBubble.php" class="ml-4 hover:bg-blue-400 p-2 rounded">
                <i class="fas fa-comments fa-lg"></i>
            </a>
            <a href="notebook.php" class="ml-4 hover:bg-blue-400 p-2 rounded">
                <i class="fas fa-book fa-lg"></i>
            </a>
            <div class="relative ml-4 p-4">
                <img src="<?php echo !empty($userData['profile_image']) ? htmlspecialchars($userData['profile_image']) : 'profile_page/default_profile.png'; ?>" 
                     alt="Profile Image" 
                     class="w-10 h-10 rounded-full cursor-pointer object-cover border-2 border-white/20" 
                     id="profileImage">
                <div class="dropdown-menu absolute right-0 mt-1 w-48 bg-white border border-gray-300 rounded shadow-lg hidden">
                    <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    <!-- Main Content -->
    <div class="container mx-auto px-4 pt-20">
        <!-- Profile Header -->
        <div class="profile-card p-8 mb-8">
            <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">
                <div class="profile-image-container">
                    <img id="profile-picture" 
                         src="<?php echo !empty($userData['profile_image']) ? htmlspecialchars($userData['profile_image']) : 'profile_page/default_profile.png'; ?>" 
                         alt="Profile Picture" 
                         class="w-40 h-40 rounded-full object-cover">
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($userData['username']); ?></h1>
                            <p class="text-gray-600"><?php echo htmlspecialchars($userData['email']); ?></p>
                        </div>
                        <button id="edit-profile-picture" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300 transform hover:scale-[1.02] shadow-sm">
                            <i class="uil uil-edit mr-2"></i>
                            Edit Profile
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-6">
                        <div class="stat-card p-4 text-white text-center transform hover:scale-[1.02] transition-all duration-300">
                            <div class="text-3xl font-bold"><?php echo $userData['bubble_count']; ?></div>
                            <div class="text-sm">Bubbles Joined</div>
                        </div>
                        <div class="stat-card p-4 text-white text-center transform hover:scale-[1.02] transition-all duration-300">
                            <div class="text-3xl font-bold"><?php echo $userData['post_count']; ?></div>
                            <div class="text-sm">Posts Created</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Navigation -->
        <div class="profile-card mb-8">
            <div class="flex justify-around p-4">
                <button onclick="showTab('posts')" class="tab-button tab-active px-4 py-2 transition-all duration-300" data-tab="posts">
                    <i class="uil uil-postcard mr-1"></i> Posts
                </button>
                <button onclick="showTab('bubbles')" class="tab-button px-4 py-2 transition-all duration-300" data-tab="bubbles">
                    <i class="uil uil-circle mr-1"></i> Bubbles
                </button>
                <button onclick="showTab('settings')" class="tab-button px-4 py-2 transition-all duration-300" data-tab="settings">
                    <i class="uil uil-setting mr-1"></i> Settings
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="posts-content">
            <div class="grid gap-6">
                <?php if (empty($userPosts)): ?>
                    <div class="profile-card p-8 text-center">
                        <i class="uil uil-comment-alt-notes text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Posts Yet</h3>
                        <p class="text-gray-500">Start sharing in your bubbles to see your posts here!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($userPosts as $post): ?>
                        <div class="profile-card p-6 hover:shadow-lg transition-all duration-300">
                            <!-- Post Header -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <img src="<?php echo htmlspecialchars($userData['profile_image'] ?? 'default-profile.png'); ?>" 
                                         alt="Profile" 
                                         class="w-10 h-10 rounded-full object-cover">
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($userData['username']); ?></h3>
                                        <div class="flex items-center text-sm text-gray-500">
                                            <span class="inline-block px-2 py-1 rounded-full text-xs mr-2 bg-blue-100">
                                                <?php echo htmlspecialchars($post['bubble_name']); ?>
                                            </span>
                                            <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Post Content -->
                            <div class="mb-4">
                                <p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($post['message']); ?></p>
                                <?php if (!empty($post['image'])): ?>
                                    <div class="mt-4 rounded-lg overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($post['image']); ?>" 
                                             alt="Post image" 
                                             class="w-full h-48 object-cover hover:scale-105 transition-transform duration-300">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Post Footer -->
                            <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                                <div class="text-sm text-gray-500">
                                    <i class="uil uil-clock mr-1"></i>
                                    <?php 
                                        $timestamp = strtotime($post['created_at']);
                                        $timeAgo = time() - $timestamp;
                                        if ($timeAgo < 60) {
                                            echo "Just now";
                                        } elseif ($timeAgo < 3600) {
                                            echo floor($timeAgo/60) . "m ago";
                                        } elseif ($timeAgo < 86400) {
                                            echo floor($timeAgo/3600) . "h ago";
                                        } else {
                                            echo floor($timeAgo/86400) . "d ago";
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content hidden" id="bubbles-content">
            <div class="grid md:grid-cols-2 gap-6" id="bubble-list">
                <?php foreach ($userBubbles as $bubble): ?>
                    <div class="profile-card p-6 hover:shadow-lg transition-all duration-300">
                        <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($bubble['bubble_name']); ?></h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($bubble['description']); ?></p>
                        <div class="flex justify-between items-center mt-4">
                            <div class="text-sm text-gray-500">
                                <i class="uil uil-users-alt mr-1"></i>
                                <?php echo htmlspecialchars($bubble['member_count']); ?> members
                            </div>
                            <div class="text-sm text-gray-500">
                                <i class="uil uil-clock mr-1"></i>
                                <?php echo date('M j, Y', strtotime($bubble['joined_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tab-content hidden" id="settings-content">
            <div class="profile-card p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Profile Settings</h3>
                <form class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">Username</label>
                        <input type="text" class="w-full p-3 border rounded-lg" value="<?php echo htmlspecialchars($userData['username']); ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" class="w-full p-3 border rounded-lg" value="<?php echo htmlspecialchars($userData['email']); ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Bio</label>
                        <textarea class="w-full p-3 border rounded-lg" rows="4"></textarea>
                    </div>
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg transform hover:scale-[1.02] transition-all duration-300 shadow-sm">
                        Save Changes
                    </button>
                </form>
                <form id="change-password-form" class="space-y-6 mt-6">
                    <div>
                        <label class="block text-gray-700 mb-2">Current Password</label>
                        <input type="password" id="current-password" class="w-full p-3 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">New Password</label>
                        <input type="password" id="new-password" class="w-full p-3 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Confirm New Password</label>
                        <input type="password" id="confirm-password" class="w-full p-3 border rounded-lg">
                    </div>
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg transform hover:scale-[1.02] transition-all duration-300 shadow-sm">
                        Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Picture Modal -->
    <div id="edit-picture-modal" class="modal">
        <div class="modal-content bg-white rounded-xl shadow-xl max-w-md mx-auto">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h2 class="text-xl font-bold text-gray-800">Update Profile Picture</h2>
                <button id="close-modal" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="edit-picture-form" method="post" enctype="multipart/form-data" class="space-y-6">
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 transition-colors cursor-pointer bg-gray-50 hover:bg-gray-100">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                    <p class="text-sm text-gray-600 mb-2">Click to upload or drag and drop</p>
                    <p class="text-xs text-gray-500">Supported formats: JPG, PNG, GIF</p>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden">
                </div>
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" 
                            class="px-4 py-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-all duration-300" 
                            onclick="closeModal()">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transform hover:scale-[1.02] transition-all duration-300 shadow-sm">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                        Upload Picture
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        function showTab(tabId) {
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all buttons
            tabButtons.forEach(button => {
                button.classList.remove('tab-active');
            });

            // Show selected tab content
            document.getElementById(`${tabId}-content`).classList.remove('hidden');

            // Add active class to clicked button
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('tab-active');
        }

        // Add click event listeners to tab buttons
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                showTab(button.getAttribute('data-tab'));
            });
        });

        // Profile picture upload functionality
        const modal = document.getElementById('edit-picture-modal');
        const editButton = document.getElementById('edit-profile-picture');
        const closeButton = document.getElementById('close-modal');
        const fileInput = document.getElementById('profile_image');
        const dropZone = document.querySelector('.border-dashed');
        const form = document.getElementById('edit-picture-form');

        function openModal() {
            modal.style.display = 'flex';
            showTab('settings'); // Switch to settings tab when editing profile
        }

        function closeModal() {
            modal.style.display = 'none';
            fileInput.value = ''; // Reset file input
        }

        // Event listeners for modal
        editButton.addEventListener('click', openModal);
        closeButton.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // File upload handling
        dropZone.addEventListener('click', () => fileInput.click());
        
        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    form.submit();
                } else {
                    alert('Please upload an image file');
                }
            }
        }

        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        // Settings form handling
        const settingsForm = document.querySelector('#settings-content form');
        settingsForm.addEventListener('submit', (e) => {
            e.preventDefault();
            // Add your settings update logic here
            alert('Settings updated successfully');
        });

        // Change password form handling
        document.getElementById('change-password-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match');
                return;
            }
            
            fetch('changePassword.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Password updated successfully');
                    document.getElementById('change-password-form').reset();
                } else {
                    alert(data.error || 'Failed to update password');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating password');
            });
        });
    </script>
</body>
</html>
