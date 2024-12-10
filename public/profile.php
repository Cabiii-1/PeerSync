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

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $response = array('success' => false, 'message' => '');

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($currentPassword, $user['password'])) {
        $response['message'] = 'Current password is incorrect';
    } elseif ($newPassword !== $confirmPassword) {
        $response['message'] = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $response['message'] = 'Password must be at least 8 characters long';
    } else {
        // Hash the new password and update
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);
        
        if ($updateStmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Password updated successfully';
        } else {
            $response['message'] = 'Error updating password';
        }
        $updateStmt->close();
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle full name update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_name') {
    $newName = trim($_POST['full_name']);
    $response = array('success' => false, 'message' => '');

    if (empty($newName)) {
        $response['message'] = 'Name cannot be empty';
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->bind_param("si", $newName, $userId);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Name updated successfully';
            $response['new_name'] = $newName;
        } else {
            $response['message'] = 'Error updating name';
        }
        $stmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://unicons.iconscout.com/release/v2.1.9/css/unicons.css" rel="stylesheet">
    <style>
        /* Background design */
        body {
            position: relative;
            background-color: #f8fafc;
        }
        
        body::before {
            content: '';
            position: fixed;
            display: block;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background-color: #dbeafe;
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
            background-color: #93c5fd;
            bottom: -50px;
            right: -50px;
            z-index: -1;
            opacity: 0.8;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .stat-card {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
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
            border: 3px solid #3b82f6;
            border-radius: 50%;
        }

        .tab-active {
            color: #3b82f6;
            border-bottom: 3px solid #3b82f6;
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
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-secondary-100 text-white flex justify-between items-center fixed w-full top-0 z-50 px-4 py-2" style="background-color: rgb(43 84 126 / var(--tw-bg-opacity));">
        <div class="flex items-center">
            <a href="indexTimeline.php"><img src="../public/ps.png" alt="Peerync Logo" class="h-12 w-12"></a>
            <span class="text-2xl font-bold ml-2">PeerSync</span>
        </div>
        <div class="flex items-center space-x-4">
            <a href="exploreBubble.php" class="text-gray-600">
                <i class="uil uil-compass"></i> Explore
            </a>
            <a href="indexBubble.php" class="text-gray-600">
                <i class="uil uil-comments"></i> Bubbles
            </a>
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
                        <button id="edit-profile-picture" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            Edit Profile
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-6">
                        <div class="stat-card p-4 text-white text-center">
                            <div class="text-3xl font-bold"><?php echo $userData['bubble_count']; ?></div>
                            <div class="text-sm">Bubbles Joined</div>
                        </div>
                        <div class="stat-card p-4 text-white text-center">
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
                <button onclick="showTab('posts')" class="tab-button tab-active px-4 py-2" data-tab="posts">
                    <i class="uil uil-postcard mr-1"></i> Posts
                </button>
                <button onclick="showTab('bubbles')" class="tab-button px-4 py-2" data-tab="bubbles">
                    <i class="uil uil-circle mr-1"></i> Bubbles
                </button>
                <button onclick="showTab('settings')" class="tab-button px-4 py-2" data-tab="settings">
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
                <h2 class="text-2xl font-semibold text-gray-800 mb-8">Settings</h2>

                <!-- Account Settings Section -->
                <div class="mb-8">
                    <button onclick="toggleSection('account-settings')" 
                            class="w-full flex items-center justify-between text-xl font-semibold text-gray-700 mb-4 focus:outline-none">
                        <span>Account Settings</span>
                        <svg id="account-settings-arrow" class="w-6 h-6 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <div id="account-settings-content" class="hidden space-y-6">
                        <!-- Full Name -->
                        <div>
                            <label class="block text-gray-700 mb-2">Full Name</label>
                            <div class="flex space-x-2">
                                <input type="text" id="full-name" value="<?php echo htmlspecialchars($userData['full_name']); ?>" 
                                       class="flex-grow p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" placeholder="Enter your full name">
                                <button onclick="updateFullName()" 
                                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                                    Save
                                </button>
                            </div>
                            <div id="name-message" class="hidden mt-2 p-2 rounded-lg"></div>
                        </div>

                        <!-- Picture -->
                        <div>
                            <label class="block text-gray-700 mb-2">Picture</label>
                            <div class="flex items-center space-x-4">
                                <img src="<?php echo $userData['profile_image'] ?? 'default-avatar.png'; ?>" 
                                     alt="Profile" 
                                     class="h-16 w-16 rounded-full object-cover">
                                <button onclick="document.getElementById('edit-picture-modal').style.display='block'"
                                        class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                                    Change Picture
                                </button>
                            </div>
                        </div>

                        <!-- Contact Email -->
                        <div>
                            <label class="block text-gray-700 mb-2">Contact Email</label>
                            <input type="email" class="w-full p-3 border rounded-lg" 
                                   value="<?php echo htmlspecialchars($userData['email']); ?>">
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-gray-700 mb-2">Password</label>
                            <button type="button" onclick="togglePasswordForm()" 
                                    class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                                Change Password
                            </button>
                        </div>

                        <!-- Password Change Form -->
                        <div id="password-form" class="hidden p-4 bg-gray-50 rounded-lg">
                            <div class="space-y-4">
                                <!-- Add success/error message div -->
                                <div id="password-message" class="hidden rounded-lg p-3 mb-4"></div>
                                
                                <form id="change-password-form" onsubmit="return handlePasswordChange(event)">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-gray-700 mb-2">Current Password</label>
                                            <div class="relative">
                                                <input type="password" id="current-password" name="current_password" 
                                                       class="w-full p-3 border rounded-lg pr-10" required>
                                                <button type="button" 
                                                        onclick="togglePasswordVisibility('current-password')"
                                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                                    <svg id="current-password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    <svg id="current-password-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 mb-2">New Password</label>
                                            <div class="relative">
                                                <input type="password" id="new-password" name="new_password" 
                                                       class="w-full p-3 border rounded-lg pr-10" required 
                                                       minlength="8">
                                                <button type="button" 
                                                        onclick="togglePasswordVisibility('new-password')"
                                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                                    <svg id="new-password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    <svg id="new-password-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 mb-2">Confirm New Password</label>
                                            <div class="relative">
                                                <input type="password" id="confirm-password" name="confirm_password" 
                                                       class="w-full p-3 border rounded-lg pr-10" required>
                                                <button type="button" 
                                                        onclick="togglePasswordVisibility('confirm-password')"
                                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                                    <svg id="confirm-password-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    <svg id="confirm-password-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                            Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>


                        <!-- Account Security -->
                        <div class="border-t pt-6">
                            <h4 class="text-lg font-medium text-gray-700 mb-4">Account Security</h4>
                            <div class="space-y-3">
                                <button class="w-full text-left px-4 py-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors">
                                    Deactivate Account
                                </button>
                                <button class="w-full text-left px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings Section -->
                <div class="mb-8">
                    <button onclick="toggleSection('payment-settings')" 
                            class="w-full flex items-center justify-between text-xl font-semibold text-gray-700 mb-4 focus:outline-none">
                        <span>Payment Settings</span>
                        <svg id="payment-settings-arrow" class="w-6 h-6 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <div id="payment-settings-content" class="hidden space-y-6">
                        <!-- Plan -->
                        <div>
                            <label class="block text-gray-700 mb-2">Plan</label>
                            <div class="bg-gray-50 p-4 rounded-lg border">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-gray-900">Free Plan</p>
                                        <p class="text-sm text-gray-500">Basic features included</p>
                                    </div>
                                    <button class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                        Upgrade Plan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Billing -->
                        <div>
                            <label class="block text-gray-700 mb-2">Billing</label>
                            <div class="bg-gray-50 p-4 rounded-lg border">
                                <p class="text-gray-500 mb-3">No payment method added</p>
                                <button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                                    Add Payment Method
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Picture Modal -->
    <div id="edit-picture-modal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Update Profile Picture</h2>
                <button id="close-modal" class="text-gray-500">
                    <i class="uil uil-times text-xl"></i>
                </button>
            </div>
            <form id="edit-picture-form" method="post" enctype="multipart/form-data" class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <i class="uil uil-image-upload text-4xl text-gray-400"></i>
                    <p class="mt-2 text-sm text-gray-500">Click to upload or drag and drop</p>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 text-gray-600" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
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

        function toggleSection(sectionId) {
            const content = document.getElementById(sectionId + '-content');
            const arrow = document.getElementById(sectionId + '-arrow');
            
            content.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        }

        function togglePasswordForm() {
            const passwordForm = document.getElementById('password-form');
            passwordForm.classList.toggle('hidden');
        }

        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const eyeOpen = document.getElementById(inputId + '-eye');
            const eyeClosed = document.getElementById(inputId + '-eye-off');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }

        // Show Account Settings by default when opening the Settings tab
        document.querySelector('[data-tab="settings"]').addEventListener('click', function() {
            const accountContent = document.getElementById('account-settings-content');
            const accountArrow = document.getElementById('account-settings-arrow');
            if (accountContent.classList.contains('hidden')) {
                accountContent.classList.remove('hidden');
                accountArrow.classList.add('rotate-180');
            }
        });

        function updateFullName() {
            const nameInput = document.getElementById('full-name');
            const messageDiv = document.getElementById('name-message');
            const newName = nameInput.value.trim();

            if (!newName) {
                showMessage(messageDiv, 'Name cannot be empty', false);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_name');
            formData.append('full_name', newName);

            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showMessage(messageDiv, data.message, data.success);
                if (data.success) {
                    // Update the displayed name in the profile header if it exists
                    const profileName = document.querySelector('.profile-name');
                    if (profileName) {
                        profileName.textContent = data.new_name;
                    }
                }
            })
            .catch(error => {
                showMessage(messageDiv, 'An error occurred. Please try again.', false);
            });
        }

        function showMessage(element, message, isSuccess) {
            element.textContent = message;
            element.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
            element.classList.add(
                isSuccess ? 'bg-green-100' : 'bg-red-100',
                isSuccess ? 'text-green-700' : 'text-red-700'
            );
            element.classList.remove('hidden');

            if (isSuccess) {
                setTimeout(() => {
                    element.classList.add('hidden');
                }, 2000);
            }
        }
    </script>
</body>
</html>
