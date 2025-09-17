<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin access
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        
        // Validate required fields
        if (empty($username) || empty($email)) {
            throw new Exception("Username and email are required.");
        }
        
        // Check if username already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception("Username already exists.");
        }
        
        // Check if email already exists in user_info (excluding current user)
        $stmt = $pdo->prepare("SELECT user_id FROM user_info WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception("Email already exists.");
        }
        
        // Update username in users table
        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE user_id = ?");
        $stmt->execute([$username, $_SESSION['user_id']]);
        
        // Check if user_info record exists
        $stmt = $pdo->prepare("SELECT user_info_id FROM user_info WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_info = $stmt->fetch();
        
        if ($user_info) {
            // Update existing user_info record
            $stmt = $pdo->prepare("UPDATE user_info SET email = ?, first_name = ?, last_name = ?, phone = ? WHERE user_id = ?");
            $stmt->execute([$email, $full_name, '', $phone, $_SESSION['user_id']]);
        } else {
            // Insert new user_info record
            $stmt = $pdo->prepare("INSERT INTO user_info (user_id, email, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $email, $full_name, '', $phone]);
        }
        
        // Update session username if changed
        if ($username !== $_SESSION['username']) {
            $_SESSION['username'] = $username;
        }
        
        logHistory($pdo, 'Profile Update', "Updated profile information", $_SESSION['username']);
        $_SESSION['success'] = "Profile updated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
    }
    header("Location: profile_settings.php");
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate required fields
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required.");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception("New password must be at least 6 characters long.");
        }
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password'])) {
            throw new Exception("Current password is incorrect.");
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
        
        logHistory($pdo, 'Password Change', "Changed password", $_SESSION['username']);
        $_SESSION['success'] = "Password changed successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error changing password: " . $e->getMessage();
    }
    header("Location: profile_settings.php");
    exit;
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_picture'])) {
    try {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select a valid image file.");
        }
        
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.");
        }
        
        // Validate file size
        if ($file['size'] > $max_size) {
            throw new Exception("File too large. Please upload an image smaller than 5MB.");
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception("Failed to upload file. Please try again.");
        }
        
        // Update database with new profile picture path
        $stmt = $pdo->prepare("
            UPDATE user_info 
            SET profile_picture = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$new_filename, $_SESSION['user_id']]);
        
        // If no user_info record exists, create one
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO user_info (user_id, profile_picture) 
                VALUES (?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $new_filename]);
        }
        
        // Delete old profile picture if it exists
        if (isset($user['profile_picture']) && !empty($user['profile_picture'])) {
            $old_file_path = $upload_dir . $user['profile_picture'];
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        }
        
        logHistory($pdo, 'Profile Picture Update', "Updated profile picture", $_SESSION['username']);
        $_SESSION['success'] = "Profile picture updated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error uploading picture: " . $e->getMessage();
    }
    header("Location: profile_settings.php");
    exit;
}

// Get current user data with profile information
$stmt = $pdo->prepare("
    SELECT u.*, ui.email, ui.first_name, ui.last_name, ui.phone, ui.profile_picture,
           CONCAT(ui.first_name, ' ', ui.last_name) as full_name
    FROM users u 
    LEFT JOIN user_info ui ON u.user_id = ui.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - MikeMadz Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .profile-picture-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
        }
        
        .profile-picture-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }
        
        .profile-picture-overlay .btn {
            border-radius: 50%;
            width: 35px;
            height: 35px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .form-section {
            padding: 2rem;
        }
        
        .section-title {
            color: #7F1734;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-2">
                    <i class="fa fa-user-cog me-3" style="color: #7F1734;"></i>Profile Settings
                </h1>
                <p class="text-muted">Manage your account information and security settings</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Profile Information Card -->
                <div class="profile-card mb-4">
                    <div class="profile-header">
                        <div class="profile-picture-container">
                            <?php 
                            $profile_picture = isset($user['profile_picture']) && !empty($user['profile_picture']) 
                                ? '../uploads/profile_pictures/' . $user['profile_picture'] 
                                : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjUwIiBmaWxsPSIjZjhmOWZhIi8+CjxjaXJjbGUgY3g9IjUwIiBjeT0iNDAiIHI9IjE1IiBmaWxsPSIjNmM3NTdkIi8+CjxwYXRoIGQ9Ik0yMCA4MGMwLTE2LjU2OSAxMy40MzEtMzAgMzAtMzBzMzAgMTMuNDMxIDMwIDMwIiBmaWxsPSIjNmM3NTdkIi8+Cjwvc3ZnPgo=';
                            ?>
                            <img src="<?php echo $profile_picture; ?>" alt="Profile" class="profile-avatar" id="profile-avatar">
                            <div class="profile-picture-overlay">
                                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#profilePictureModal">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                        </div>
                        <h4 class="mb-1"><?php echo htmlspecialchars(isset($user['full_name']) && $user['full_name'] ? $user['full_name'] : $user['username']); ?></h4>
                        <p class="mb-0 opacity-75"><?php echo ucfirst(isset($user['role']) ? $user['role'] : 'admin'); ?></p>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fa fa-user me-2"></i>Personal Information
                        </h5>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars(isset($user['username']) ? $user['username'] : ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars(isset($user['email']) ? $user['email'] : ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars(isset($user['full_name']) ? $user['full_name'] : ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars(isset($user['phone']) ? $user['phone'] : ''); ?>">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fa fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Change Card -->
                <div class="profile-card">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fa fa-lock me-2"></i>Change Password
                        </h5>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fa fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Account Information Card -->
                <div class="profile-card">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fa fa-info-circle me-2"></i>Account Information
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">User ID</label>
                            <p class="form-control-plaintext">#<?php echo $user['user_id']; ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Role</label>
                            <p class="form-control-plaintext">
                                <span class="badge bg-primary"><?php echo ucfirst(isset($user['role']) ? $user['role'] : 'admin'); ?></span>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Created</label>
                            <p class="form-control-plaintext"><?php echo isset($user['date_created']) ? date('M d, Y', strtotime($user['date_created'])) : 'Unknown'; ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Last Login</label>
                            <p class="form-control-plaintext">
                                <?php echo (isset($user['last_login']) && $user['last_login']) ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <p class="form-control-plaintext">
                                <span class="badge bg-success">Active</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Profile Picture Upload Modal -->
    <div class="modal fade" id="profilePictureModal" tabindex="-1" aria-labelledby="profilePictureModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profilePictureModalLabel">
                        <i class="fas fa-camera me-2"></i>Update Profile Picture
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <img id="imagePreview" src="<?php echo $profile_picture; ?>" alt="Preview" class="img-thumbnail" style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Select New Picture</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
                            <div class="form-text">
                                Supported formats: JPEG, PNG, GIF, WebP. Maximum size: 5MB.
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tips for best results:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Use a square image for best fit</li>
                                <li>High resolution images work better</li>
                                <li>Make sure your face is clearly visible</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_picture" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Upload Picture
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    
    <script>
        // Image preview functionality
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
