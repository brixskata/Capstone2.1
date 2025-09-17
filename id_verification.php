<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if user already has verification
$stmt = $pdo->prepare("SELECT * FROM customer_id_verification WHERE user_id = ?");
$stmt->execute([$user_id]);
$existing_verification = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_id'])) {
    $id_type = $_POST['id_type'];
    $id_number = $_POST['id_number'];
    
    // Validate required fields
    if (empty($id_type) || empty($id_number)) {
        $error = "Please fill in all required fields.";
    } else {
        // Handle file uploads
        $upload_dir = 'uploads/id_verification/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $front_image = '';
        $back_image = '';
        
        // Upload front image
        if (isset($_FILES['id_front']) && $_FILES['id_front']['error'] == 0) {
            $front_file = $_FILES['id_front'];
            $front_extension = pathinfo($front_file['name'], PATHINFO_EXTENSION);
            $front_filename = 'front_' . $user_id . '_' . time() . '.' . $front_extension;
            $front_path = $upload_dir . $front_filename;
            
            if (move_uploaded_file($front_file['tmp_name'], $front_path)) {
                $front_image = $front_path;
            } else {
                $error = "Failed to upload front image.";
            }
        } else {
            $error = "Front image is required.";
        }
        
        // Upload back image (optional)
        if (empty($error) && isset($_FILES['id_back']) && $_FILES['id_back']['error'] == 0) {
            $back_file = $_FILES['id_back'];
            $back_extension = pathinfo($back_file['name'], PATHINFO_EXTENSION);
            $back_filename = 'back_' . $user_id . '_' . time() . '.' . $back_extension;
            $back_path = $upload_dir . $back_filename;
            
            if (move_uploaded_file($back_file['tmp_name'], $back_path)) {
                $back_image = $back_path;
            }
        }
        
        // Insert or update verification record
        if (empty($error)) {
            if ($existing_verification) {
                // Update existing verification
                $stmt = $pdo->prepare("UPDATE customer_id_verification SET 
                    id_type = ?, id_number = ?, id_front_image = ?, id_back_image = ?, 
                    status = 'pending', rejection_reason = NULL, admin_notes = NULL, 
                    verified_by = NULL, verified_at = NULL, updated_at = NOW() 
                    WHERE user_id = ?");
                $stmt->execute([$id_type, $id_number, $front_image, $back_image, $user_id]);
            } else {
                // Insert new verification
                $stmt = $pdo->prepare("INSERT INTO customer_id_verification 
                    (user_id, id_type, id_number, id_front_image, id_back_image, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $id_type, $id_number, $front_image, $back_image]);
            }
            
            // Create admin notification
            $stmt = $pdo->prepare("INSERT INTO admin_notifications 
                (type, title, message, data) VALUES (?, ?, ?, ?)");
            $notification_title = "New ID Verification Request";
            $notification_message = "User ID: $user_id has submitted ID verification documents.";
            $notification_data = json_encode(['user_id' => $user_id, 'id_type' => $id_type]);
            $stmt->execute(['id_verification', $notification_title, $notification_message, $notification_data]);
            
            $message = "ID verification submitted successfully! Please wait for admin approval.";
            
            // Refresh verification data
            $stmt = $pdo->prepare("SELECT * FROM customer_id_verification WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $existing_verification = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Verification - MikeMadz</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #db3030;
            --bs-warning: #ffc107;
            --bs-info: #016bf8;
            --bs-light: #f0f3f2;
            --bs-dark: #001e2b;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            border: none;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid #ffc107;
        }
        
        .status-approved {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
            border: 1px solid #198754;
        }
        
        .status-rejected {
            background: rgba(219, 48, 48, 0.1);
            color: #db3030;
            border: 1px solid #db3030;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.3);
            background: linear-gradient(135deg, #6b1429 0%, #8b1a36 100%);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }
        
        .upload-area {
            border: 2px dashed #e9ecef;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: var(--bs-secondary);
            background: rgba(127, 23, 52, 0.05);
        }
        
        .upload-area.dragover {
            border-color: var(--bs-secondary);
            background: rgba(127, 23, 52, 0.1);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="verification-card p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-id-card text-primary" style="font-size: 3rem; color: var(--bs-secondary);"></i>
                        <h2 class="mt-3 mb-2">ID Verification</h2>
                        <p class="text-muted">Upload your valid ID to place orders</p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($existing_verification): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2"></i>Current Verification Status
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>ID Type:</strong> <?= htmlspecialchars($existing_verification['id_type']) ?></p>
                                        <p><strong>ID Number:</strong> <?= htmlspecialchars($existing_verification['id_number']) ?></p>
                                        <p><strong>Status:</strong> 
                                            <span class="status-badge status-<?= $existing_verification['status'] ?>">
                                                <?= ucfirst($existing_verification['status']) ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Submitted:</strong> <?= date('M d, Y H:i', strtotime($existing_verification['created_at'])) ?></p>
                                        <?php if ($existing_verification['verified_at']): ?>
                                            <p><strong>Verified:</strong> <?= date('M d, Y H:i', strtotime($existing_verification['verified_at'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($existing_verification['status'] === 'rejected' && $existing_verification['rejection_reason']): ?>
                                    <div class="alert alert-danger mt-3">
                                        <strong>Rejection Reason:</strong><br>
                                        <?= htmlspecialchars($existing_verification['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($existing_verification['admin_notes']): ?>
                                    <div class="alert alert-info mt-3">
                                        <strong>Admin Notes:</strong><br>
                                        <?= htmlspecialchars($existing_verification['admin_notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="verificationForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="id_type" class="form-label">ID Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_type" name="id_type" required>
                                    <option value="">Select ID Type</option>
                                    <option value="Driver's License" <?= ($existing_verification && $existing_verification['id_type'] === "Driver's License") ? 'selected' : '' ?>>Driver's License</option>
                                    <option value="Passport" <?= ($existing_verification && $existing_verification['id_type'] === "Passport") ? 'selected' : '' ?>>Passport</option>
                                    <option value="National ID" <?= ($existing_verification && $existing_verification['id_type'] === "National ID") ? 'selected' : '' ?>>National ID</option>
                                    <option value="Student ID" <?= ($existing_verification && $existing_verification['id_type'] === "Student ID") ? 'selected' : '' ?>>Student ID</option>
                                    <option value="Other" <?= ($existing_verification && $existing_verification['id_type'] === "Other") ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="id_number" class="form-label">ID Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="id_number" name="id_number" 
                                       value="<?= htmlspecialchars($existing_verification['id_number'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">ID Front Image <span class="text-danger">*</span></label>
                            <div class="upload-area" onclick="document.getElementById('id_front').click()">
                                <i class="fas fa-cloud-upload-alt text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">Click to upload or drag and drop</p>
                                <small class="text-muted">PNG, JPG, JPEG up to 5MB</small>
                                <input type="file" id="id_front" name="id_front" accept="image/*" class="d-none" required>
                            </div>
                            <div id="front-preview" class="mt-3"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">ID Back Image (Optional)</label>
                            <div class="upload-area" onclick="document.getElementById('id_back').click()">
                                <i class="fas fa-cloud-upload-alt text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">Click to upload or drag and drop</p>
                                <small class="text-muted">PNG, JPG, JPEG up to 5MB</small>
                                <input type="file" id="id_back" name="id_back" accept="image/*" class="d-none">
                            </div>
                            <div id="back-preview" class="mt-3"></div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" name="submit_id" class="btn btn-primary btn-lg">
                                <i class="fas fa-upload me-2"></i>
                                <?= $existing_verification ? 'Update Verification' : 'Submit Verification' ?>
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <a href="product.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File upload preview
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).innerHTML = `
                        <img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                    `;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        document.getElementById('id_front').addEventListener('change', function() {
            previewImage(this, 'front-preview');
        });
        
        document.getElementById('id_back').addEventListener('change', function() {
            previewImage(this, 'back-preview');
        });
        
        // Drag and drop functionality
        const uploadAreas = document.querySelectorAll('.upload-area');
        uploadAreas.forEach(area => {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const input = this.querySelector('input[type="file"]');
                    input.files = files;
                    previewImage(input, input.id + '-preview');
                }
            });
        });
    </script>
</body>
</html>
