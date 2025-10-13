<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: login_admin.php");
    exit;
}

// Handle GCash settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_gcash_settings'])) {
    try {
        $gcash_account_name = trim($_POST['gcash_account_name']);
        $gcash_account_number = trim($_POST['gcash_account_number']);
        $gcash_instructions = trim($_POST['gcash_instructions']);
        
        // Validate required fields
        if (empty($gcash_account_name)) {
            throw new Exception("GCash account name is required.");
        }
        
        // Handle QR code upload
        $qr_code_path = null;
        if (isset($_FILES['gcash_qr_code']) && $_FILES['gcash_qr_code']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/gcashqr/';
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = $_FILES['gcash_qr_code'];
            
            // Validate file type
            if (!in_array($file_info['type'], $allowed_types)) {
                throw new Exception("Invalid file type. Only JPEG and PNG files are allowed.");
            }
            
            // Validate file size
            if ($file_info['size'] > $max_size) {
                throw new Exception("File size too large. Maximum size is 5MB.");
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $new_filename = 'gcash_qr_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_info['tmp_name'], $upload_path)) {
                $qr_code_path = 'assets/gcashqr/' . $new_filename;
                
                // Delete old QR code file if it exists and is different
                $old_qr_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gcash_qr_code'");
                $old_qr_stmt->execute();
                $old_qr = $old_qr_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($old_qr && $old_qr['setting_value'] !== $qr_code_path && file_exists('../' . $old_qr['setting_value'])) {
                    unlink('../' . $old_qr['setting_value']);
                }
            } else {
                throw new Exception("Failed to upload QR code file.");
            }
        }
        
        // Update settings in database
        $settings = [
            'gcash_account_name' => $gcash_account_name,
            'gcash_account_number' => $gcash_account_number,
            'gcash_instructions' => $gcash_instructions
        ];
        
        if ($qr_code_path) {
            $settings['gcash_qr_code'] = $qr_code_path;
        }
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, 'text') 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$key, $value]);
        }
        
        logHistory($pdo, 'GCash Settings Update', "Updated GCash payment settings", $_SESSION['username']);
        $_SESSION['success'] = "GCash settings updated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating GCash settings: " . $e->getMessage();
    }
    header("Location: gcash_settings.php");
    exit;
}

// Fetch current GCash settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'gcash_%'");
$settings_stmt->execute();
$settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Set default values if not set
$defaults = [
    'gcash_account_name' => 'MikeMadz Store',
    'gcash_account_number' => '',
    'gcash_instructions' => 'Scan the QR code above and complete your payment',
    'gcash_qr_code' => 'assets/gcashqr/gcash_qr.jpg'
];

foreach ($defaults as $key => $default_value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default_value;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Settings - MikeMadz Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .settings-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 1rem 1rem 0 0;
        }
        
        .form-control:focus {
            border-color: #7F1734;
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #6d1429 0%, #8f1937 100%);
            transform: translateY(-1px);
        }
        
        .qr-preview {
            max-width: 300px;
            border-radius: 0.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: #7F1734;
            background-color: rgba(127, 23, 52, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: #7F1734;
            background-color: rgba(127, 23, 52, 0.1);
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
                    <i class="fa fa-mobile-alt me-3" style="color: #7F1734;"></i>GCash Settings
                </h1>
                <p class="text-muted">Manage GCash payment configuration and QR code</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="settings-card">
                    <div class="settings-header">
                        <h4 class="mb-0">
                            <i class="fa fa-cog me-2"></i>GCash Payment Configuration
                        </h4>
                        <p class="mb-0 mt-2 opacity-75">Configure GCash payment settings and upload QR code</p>
                    </div>
                    
                    <div class="p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <!-- GCash Account Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fa fa-user me-2"></i>Account Information
                                    </h5>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">GCash Account Name *</label>
                                    <input type="text" name="gcash_account_name" class="form-control" 
                                           value="<?= htmlspecialchars($settings['gcash_account_name']) ?>" required>
                                    <small class="form-text text-muted">Name that appears on customer receipts</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">GCash Account Number</label>
                                    <input type="text" name="gcash_account_number" class="form-control" 
                                           value="<?= htmlspecialchars($settings['gcash_account_number']) ?>" 
                                           placeholder="09XX XXX XXXX">
                                    <small class="form-text text-muted">Optional: For reference purposes</small>
                                </div>
                            </div>

                            <!-- Payment Instructions -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Payment Instructions</label>
                                <textarea name="gcash_instructions" class="form-control" rows="3" 
                                          placeholder="Instructions shown to customers"><?= htmlspecialchars($settings['gcash_instructions']) ?></textarea>
                                <small class="form-text text-muted">Instructions displayed to customers during checkout</small>
                            </div>


                            <!-- QR Code Upload -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3">
                                    <i class="fa fa-qrcode me-2"></i>QR Code Management
                                </h5>
                                
                                <!-- Current QR Code Preview -->
                                <?php if (file_exists('../' . $settings['gcash_qr_code'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Current QR Code</label>
                                        <div class="text-center">
                                            <img src="../<?= htmlspecialchars($settings['gcash_qr_code']) ?>" 
                                                 alt="Current GCash QR Code" class="qr-preview">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- File Upload Area -->
                                <div class="file-upload-area" onclick="document.getElementById('qr-upload').click()">
                                    <i class="fa fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Upload New QR Code</h5>
                                    <p class="text-muted mb-3">Click here or drag and drop your QR code image</p>
                                    <p class="small text-muted">
                                        <i class="fa fa-info-circle me-1"></i>
                                        Supported formats: JPEG, PNG (Max size: 5MB)
                                    </p>
                                </div>
                                
                                <input type="file" id="qr-upload" name="gcash_qr_code" class="form-control d-none" 
                                       accept="image/jpeg,image/jpg,image/png">
                                
                                <div id="file-info" class="mt-2" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fa fa-file-image me-2"></i>
                                        <span id="file-name"></span>
                                        <span class="badge bg-primary ms-2" id="file-size"></span>
                                    </div>
                                </div>
                            </div>


                            <div class="d-flex justify-content-end">
                                <button type="submit" name="update_gcash_settings" class="btn btn-primary btn-lg">
                                    <i class="fa fa-save me-2"></i>Update GCash Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File upload handling
        const fileUploadArea = document.querySelector('.file-upload-area');
        const fileInput = document.getElementById('qr-upload');
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');

        // Click to upload
        fileUploadArea.addEventListener('click', function() {
            fileInput.click();
        });

        // Drag and drop handling
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        // File input change
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
            }
        });

        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please select a JPEG or PNG image.');
                return;
            }

            // Validate file size (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size too large. Maximum size is 5MB.');
                return;
            }

            // Show file info
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';

            // Preview image
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('img');
                preview.src = e.target.result;
                preview.className = 'qr-preview mt-2';
                preview.style.maxWidth = '200px';
                
                // Remove existing preview
                const existingPreview = fileUploadArea.querySelector('img');
                if (existingPreview) {
                    existingPreview.remove();
                }
                
                fileUploadArea.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

    </script>
</body>
</html>
