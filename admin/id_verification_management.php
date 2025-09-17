<?php
session_start();
include '../includes/db.php';

// Check if user is admin
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: login_admin.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if tables exist
try {
    $pdo->query("SELECT 1 FROM customer_id_verification LIMIT 1");
} catch (PDOException $e) {
    $error = "ID verification tables not found. Please run the database setup first.";
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_id = $_POST['verification_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE customer_id_verification SET 
            status = 'approved', verified_by = ?, verified_at = NOW(), admin_notes = ? 
            WHERE verification_id = ?");
        $stmt->execute([$admin_id, $admin_notes, $verification_id]);
        
        // Update user's id_verified status
        $stmt = $pdo->prepare("UPDATE users SET id_verified = 1 WHERE user_id = (
            SELECT user_id FROM customer_id_verification WHERE verification_id = ?
        )");
        $stmt->execute([$verification_id]);
        
        $message = "ID verification approved successfully!";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE customer_id_verification SET 
            status = 'rejected', verified_by = ?, verified_at = NOW(), 
            admin_notes = ?, rejection_reason = ? 
            WHERE verification_id = ?");
        $stmt->execute([$admin_id, $admin_notes, $rejection_reason, $verification_id]);
        
        $message = "ID verification rejected.";
    }
}

// Get all pending verifications
$stmt = $pdo->query("
    SELECT cv.*, u.username, ui.first_name, ui.last_name, ui.email 
    FROM customer_id_verification cv
    JOIN users u ON cv.user_id = u.user_id
    LEFT JOIN user_info ui ON u.user_id = ui.user_id
    ORDER BY cv.created_at DESC
");
$verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Verification Management - Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin Styles -->
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bs-primary: #7F1734;
            --bs-secondary: #6c757d;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #0dcaf0;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
        }
        
        /* Override admin styles for this page */
        .main-content {
            background-color: var(--bg-primary) !important;
        }
        
        .main-container {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .page-header {
            background: var(--bs-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
        }
        
        .page-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }
        
        .stats-container {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .stat-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .verification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--bs-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .user-details h5 {
            margin: 0;
            color: var(--bs-dark);
            font-weight: 700;
        }
        
        .user-details p {
            margin: 0;
            color: var(--bs-secondary);
            font-size: 0.9rem;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        
        .status-approved {
            background: #d1edff;
            color: #0f5132;
            border: 1px solid #198754;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid var(--bs-primary);
        }
        
        .info-item strong {
            color: var(--bs-primary);
            font-weight: 700;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .image-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .image-wrapper {
            text-align: center;
        }
        
        .image-wrapper strong {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--bs-dark);
            font-weight: 700;
        }
        
        .id-image {
            width: 100%;
            max-width: 250px;
            height: 180px;
            object-fit: cover;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .id-image:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border-color: var(--bs-primary);
        }
        
        .action-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid #e9ecef;
        }
        
        .form-label {
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }
        
        .btn-approve {
            background: var(--bs-success);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
        }
        
        .btn-reject {
            background: var(--bs-danger);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-approve:hover, .btn-reject:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .btn-approve:active, .btn-reject:active {
            transform: translateY(-1px);
        }
        
        .status-info {
            background: #e3f2fd;
            border: 1px solid #e1bee7;
            border-radius: 15px;
            padding: 1.5rem;
        }
        
        .status-info strong {
            color: var(--bs-primary);
            font-weight: 700;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--bs-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            color: var(--bs-dark);
            margin-bottom: 1rem;
        }
        
        /* Modal styles for manual display */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            outline: 0;
        }
        
        .modal.show {
            display: block !important;
        }
        
        .modal-dialog {
            position: relative;
            width: auto;
            margin: 0.5rem;
            pointer-events: none;
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }
        
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }
        
        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.2);
            border-radius: 0.3rem;
            outline: 0;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 100vw;
            height: 100vh;
            background-color: #000;
        }
        
        .modal-backdrop.fade {
            opacity: 0;
        }
        
        .modal-backdrop.show {
            opacity: 0.5;
        }
        
        .modal-open {
            overflow: hidden;
        }
        
        /* Ensure modal is perfectly centered */
        .modal.show .modal-dialog {
            transform: none;
            margin: 1.75rem auto;
        }
        
        .modal.show .modal-dialog-centered {
            transform: none;
            margin: 1.75rem auto;
        }
        
        /* Center the modal content */
        .modal-body {
            text-align: center;
            padding: 1rem;
        }
        
        .modal-body img {
            max-width: 100%;
            height: auto;
            max-height: 70vh;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Confirmation modal styles */
        .modal-footer .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .modal-footer .btn-secondary {
            background: #6c757d;
            border: none;
            color: white;
        }
        
        .modal-footer .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .modal-footer .btn-success {
            background: var(--bs-success);
            border: none;
            color: white;
        }
        
        .modal-footer .btn-success:hover {
            background: #157347;
            transform: translateY(-2px);
        }
        
        .modal-footer .btn-danger {
            background: var(--bs-danger);
            border: none;
            color: white;
        }
        
        .modal-footer .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .stats-container {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .image-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <main class="main-content" id="mainContent">
        <div class="main-container">
        <div class="page-header">
            <h2><i class="fas fa-id-card me-2"></i>ID Verification Management</h2>
            <div class="stats-container">
                <span class="stat-badge">
                    <i class="fas fa-clock me-1"></i>
                    <?= count(array_filter($verifications, fn($v) => $v['status'] === 'pending')) ?> Pending
                </span>
                <span class="stat-badge">
                    <i class="fas fa-check-circle me-1"></i>
                    <?= count(array_filter($verifications, fn($v) => $v['status'] === 'approved')) ?> Approved
                </span>
                <span class="stat-badge">
                    <i class="fas fa-times-circle me-1"></i>
                    <?= count(array_filter($verifications, fn($v) => $v['status'] === 'rejected')) ?> Rejected
                </span>
            </div>
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
                
                <?php if (empty($verifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-id-card"></i>
                        <h4>No ID Verifications Found</h4>
                        <p>There are currently no ID verification requests to review.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($verifications as $verification): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="verification-card">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?= strtoupper(substr($verification['first_name'], 0, 1)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <h5><?= htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']) ?></h5>
                                                    <p>@<?= htmlspecialchars($verification['username']) ?></p>
                                                    <small><?= htmlspecialchars($verification['email']) ?></small>
                                                </div>
                                            </div>
                                            <span class="status-badge status-<?= $verification['status'] ?>">
                                                <?= ucfirst($verification['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <strong>ID Type</strong>
                                                <?= htmlspecialchars($verification['id_type']) ?>
                                            </div>
                                            <div class="info-item">
                                                <strong>ID Number</strong>
                                                <?= htmlspecialchars($verification['id_number']) ?>
                                            </div>
                                            <div class="info-item">
                                                <strong>Submitted</strong>
                                                <?= date('M d, Y H:i', strtotime($verification['created_at'])) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="image-container">
                                            <div class="image-wrapper">
                                                <strong>Front Image</strong>
                                                <img src="../<?= htmlspecialchars($verification['id_front_image']) ?>" 
                                                     class="id-image" 
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#imageModal"
                                                     data-src="../<?= htmlspecialchars($verification['id_front_image']) ?>"
                                                     style="cursor: pointer;"
                                                     title="Click to view full size">
                                            </div>
                                            <div class="image-wrapper">
                                                <strong>Back Image</strong>
                                                <?php if ($verification['id_back_image']): ?>
                                                    <img src="../<?= htmlspecialchars($verification['id_back_image']) ?>" 
                                                         class="id-image" 
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-src="../<?= htmlspecialchars($verification['id_back_image']) ?>"
                                                         style="cursor: pointer;"
                                                         title="Click to view full size">
                                                <?php else: ?>
                                                    <div class="id-image d-flex align-items-center justify-content-center" style="background: #f8f9fa; color: #6c757d;">
                                                        <i class="fas fa-image fa-2x"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                
                                        <?php if ($verification['status'] === 'pending'): ?>
                                            <div class="action-form">
                                                <form method="POST">
                                                    <input type="hidden" name="verification_id" value="<?= $verification['verification_id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Admin Notes:</label>
                                                        <textarea class="form-control" name="admin_notes" rows="2" 
                                                                  placeholder="Optional notes about this verification"></textarea>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Rejection Reason (if rejecting):</label>
                                                        <textarea class="form-control" name="rejection_reason" rows="2" 
                                                                  placeholder="Reason for rejection (only if rejecting)"></textarea>
                                                    </div>
                                                    
                                                    <div class="d-flex gap-3 justify-content-center">
                                                        <button type="submit" name="action" value="approve" class="btn btn-approve">
                                                            <i class="fas fa-check me-2"></i>Approve
                                                        </button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-reject">
                                                            <i class="fas fa-times me-2"></i>Reject
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="status-info">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <strong>Status:</strong> <?= ucfirst($verification['status']) ?>
                                                    </div>
                                                    <?php if ($verification['verified_at']): ?>
                                                        <div class="col-md-6">
                                                            <strong>Processed:</strong> <?= date('M d, Y H:i', strtotime($verification['verified_at'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($verification['rejection_reason']): ?>
                                                    <div class="mt-2">
                                                        <strong>Rejection Reason:</strong><br>
                                                        <?= htmlspecialchars($verification['rejection_reason']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($verification['admin_notes']): ?>
                                                    <div class="mt-2">
                                                        <strong>Admin Notes:</strong><br>
                                                        <?= htmlspecialchars($verification['admin_notes']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </main>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ID Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--bs-primary); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Action
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage">Are you sure you want to perform this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn" id="confirmActionBtn">
                        <i class="fas fa-check me-1"></i>Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/admin_scripts.php'; ?>
    
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Image modal functionality
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            if (imageModal && modalImage) {
                imageModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (button) {
                        const imageSrc = button.getAttribute('data-src');
                        if (imageSrc) {
                            modalImage.src = imageSrc;
                            console.log('Image loaded:', imageSrc);
                        }
                    }
                });
            }
            
            // Add click event listeners to all images as backup
            const idImages = document.querySelectorAll('.id-image[data-src]');
            console.log('Found', idImages.length, 'clickable images');
            
            idImages.forEach(function(img, index) {
                console.log('Setting up click listener for image', index, img.getAttribute('data-src'));
                img.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Image clicked:', this.getAttribute('data-src'));
                    const imageSrc = this.getAttribute('data-src');
                    if (imageSrc && modalImage) {
                        modalImage.src = imageSrc;
                        // Show modal using jQuery or manual method
                        if (typeof $ !== 'undefined' && $.fn.modal) {
                            $('#imageModal').modal('show');
                        } else {
                            // Fallback: show modal manually
                            const modal = document.getElementById('imageModal');
                            modal.style.display = 'block';
                            modal.classList.add('show');
                            document.body.classList.add('modal-open');
                            
                            // Add backdrop
                            const backdrop = document.createElement('div');
                            backdrop.className = 'modal-backdrop fade show';
                            backdrop.id = 'modalBackdrop';
                            document.body.appendChild(backdrop);
                        }
                    }
                });
            });
            
            // Close modal functionality
            const closeModal = function() {
                const modal = document.getElementById('imageModal');
                const backdrop = document.getElementById('modalBackdrop');
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                }
                if (backdrop) {
                    backdrop.remove();
                }
            };
            
            // Add close button listeners for image modal
            const closeButtons = document.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
            closeButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (this.closest('#imageModal')) {
                        closeModal();
                    } else if (this.closest('#confirmModal')) {
                        closeConfirmModal();
                    }
                });
            });
            
            // Close modal when clicking backdrop
            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Close confirmation modal when clicking backdrop
            document.getElementById('confirmModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeConfirmModal();
                }
            });
            
            // Confirmation modal functionality
            const confirmModal = document.getElementById('confirmModal');
            const confirmMessage = document.getElementById('confirmMessage');
            const confirmActionBtn = document.getElementById('confirmActionBtn');
            let pendingAction = null;
            
            // Function to show confirmation modal
            function showConfirmation(message, action, buttonClass = 'btn-danger') {
                if (!confirmModal || !confirmMessage || !confirmActionBtn) {
                    console.error('Modal elements not found');
                    return;
                }
                
                confirmMessage.textContent = message;
                confirmActionBtn.className = 'btn ' + buttonClass;
                confirmActionBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirm';
                pendingAction = action;
                
                // Remove any existing backdrop
                const existingBackdrop = document.getElementById('confirmBackdrop');
                if (existingBackdrop) {
                    existingBackdrop.remove();
                }
                
                // Show modal manually
                confirmModal.style.display = 'block';
                confirmModal.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Add backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'confirmBackdrop';
                document.body.appendChild(backdrop);
                
                console.log('Confirmation modal shown');
            }
            
            // Handle confirm button click
            confirmActionBtn.addEventListener('click', function() {
                console.log('Confirm button clicked, pendingAction:', pendingAction);
                if (pendingAction) {
                    console.log('Executing pending action');
                    pendingAction();
                } else {
                    console.log('No pending action to execute');
                }
                closeConfirmModal();
            });
            
            // Close confirmation modal
            function closeConfirmModal() {
                if (!confirmModal) {
                    console.error('Confirm modal not found');
                    return;
                }
                
                confirmModal.style.display = 'none';
                confirmModal.classList.remove('show');
                document.body.classList.remove('modal-open');
                
                // Remove backdrop
                const backdrop = document.getElementById('confirmBackdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                
                pendingAction = null;
                console.log('Confirmation modal closed');
            }
            
            // Add confirmation to approve/reject buttons
            const approveButtons = document.querySelectorAll('button[name="action"][value="approve"]');
            const rejectButtons = document.querySelectorAll('button[name="action"][value="reject"]');
            
            approveButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    console.log('Approve button clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    const form = this.closest('form');
                    const userName = form.closest('.verification-card').querySelector('.user-details h5').textContent;
                    
                    console.log('Showing confirmation for approve:', userName);
                    showConfirmation(
                        `Are you sure you want to approve the ID verification for ${userName}?`,
                        function() {
                            console.log('Approval confirmed, submitting form');
                            // Create a hidden input to submit the form
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'action';
                            hiddenInput.value = 'approve';
                            form.appendChild(hiddenInput);
                            form.submit();
                        },
                        'btn-success'
                    );
                });
            });
            
            rejectButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    console.log('Reject button clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    const form = this.closest('form');
                    const userName = form.closest('.verification-card').querySelector('.user-details h5').textContent;
                    
                    console.log('Showing confirmation for reject:', userName);
                    showConfirmation(
                        `Are you sure you want to reject the ID verification for ${userName}?`,
                        function() {
                            console.log('Rejection confirmed, submitting form');
                            // Create a hidden input to submit the form
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'action';
                            hiddenInput.value = 'reject';
                            form.appendChild(hiddenInput);
                            form.submit();
                        },
                        'btn-danger'
                    );
                });
            });
        });
    </script>
</body>
</html>
