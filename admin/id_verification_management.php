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
    <?php include 'includes/admin_head.php'; ?>
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
        
        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: var(--bs-primary);
            color: white;
            border: none;
            font-weight: 700;
            padding: 1rem;
            text-align: center;
            vertical-align: middle;
        }
        
        .table tbody td {
            border: none;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bs-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .user-details h6 {
            margin: 0;
            color: var(--bs-dark);
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .user-details p {
            margin: 0;
            color: var(--bs-secondary);
            font-size: 0.8rem;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
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
        
        .id-images {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .id-image {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .id-image:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border-color: var(--bs-primary);
        }
        
        .no-image {
            width: 60px;
            height: 40px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .btn-approve {
            background: var(--bs-success);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-reject {
            background: var(--bs-danger);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-approve:hover, .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .btn-approve:active, .btn-reject:active {
            transform: translateY(-1px);
        }
        
        .info-text {
            font-size: 0.85rem;
            color: var(--bs-secondary);
        }
        
        .info-text strong {
            color: var(--bs-dark);
            font-weight: 600;
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
            
            .table-container {
                overflow-x: auto;
            }
            
            .table {
                min-width: 800px;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .id-images {
                flex-direction: column;
                gap: 0.25rem;
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
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>ID Details</th>
                                    <th>Status</th>
                                    <th>Images</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($verifications as $verification): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?= strtoupper(substr($verification['first_name'], 0, 1)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <h6><?= htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']) ?></h6>
                                                    <p>@<?= htmlspecialchars($verification['username']) ?></p>
                                                    <small><?= htmlspecialchars($verification['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="info-text">
                                                <div><strong>Type:</strong> <?= htmlspecialchars($verification['id_type']) ?></div>
                                                <div><strong>Number:</strong> <?= htmlspecialchars($verification['id_number']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $verification['status'] ?>">
                                                <?= ucfirst($verification['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="id-images">
                                                <img src="../<?= htmlspecialchars($verification['id_front_image']) ?>" 
                                                     class="id-image" 
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#imageModal"
                                                     data-src="../<?= htmlspecialchars($verification['id_front_image']) ?>"
                                                     title="Front Image - Click to view full size">
                                                <?php if ($verification['id_back_image']): ?>
                                                    <img src="../<?= htmlspecialchars($verification['id_back_image']) ?>" 
                                                         class="id-image" 
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-src="../<?= htmlspecialchars($verification['id_back_image']) ?>"
                                                         title="Back Image - Click to view full size">
                                                <?php else: ?>
                                                    <div class="no-image">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="info-text">
                                                <?= date('M d, Y', strtotime($verification['created_at'])) ?><br>
                                                <small><?= date('H:i', strtotime($verification['created_at'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($verification['status'] === 'pending'): ?>
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-approve" 
                                                            onclick="showActionModal(<?= $verification['verification_id'] ?>, 'approve', '<?= htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']) ?>')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-reject" 
                                                            onclick="showActionModal(<?= $verification['verification_id'] ?>, 'reject', '<?= htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']) ?>')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="info-text">
                                                    <?php if ($verification['verified_at']): ?>
                                                        <div><strong>Processed:</strong></div>
                                                        <div><?= date('M d, Y H:i', strtotime($verification['verified_at'])) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($verification['rejection_reason']): ?>
                                                        <div class="mt-1"><strong>Reason:</strong></div>
                                                        <div><?= htmlspecialchars($verification['rejection_reason']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($verification['admin_notes']): ?>
                                                        <div class="mt-1"><strong>Notes:</strong></div>
                                                        <div><?= htmlspecialchars($verification['admin_notes']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
    
    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--bs-primary); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="actionModalTitle">Confirm Action</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="actionForm" method="POST">
                        <input type="hidden" name="verification_id" id="verificationId">
                        <input type="hidden" name="action" id="actionType">
                        
                        <p id="actionMessage">Are you sure you want to perform this action?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Notes:</label>
                            <textarea class="form-control" name="admin_notes" id="adminNotes" rows="2" 
                                      placeholder="Optional notes about this verification"></textarea>
                        </div>
                        
                        <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
                            <label class="form-label">Rejection Reason:</label>
                            <textarea class="form-control" name="rejection_reason" id="rejectionReason" rows="2" 
                                      placeholder="Reason for rejection"></textarea>
                        </div>
                    </form>
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
                        }
                    }
                });
            }
            
            // Add click event listeners to all images
            const idImages = document.querySelectorAll('.id-image[data-src]');
            idImages.forEach(function(img) {
                img.addEventListener('click', function(e) {
                    e.preventDefault();
                    const imageSrc = this.getAttribute('data-src');
                    if (imageSrc && modalImage) {
                        modalImage.src = imageSrc;
                        if (typeof $ !== 'undefined' && $.fn.modal) {
                            $('#imageModal').modal('show');
                        } else {
                            // Fallback: show modal manually
                            const modal = document.getElementById('imageModal');
                            modal.style.display = 'block';
                            modal.classList.add('show');
                            document.body.classList.add('modal-open');
                            
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
            
            // Close modal when clicking backdrop
            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Action modal functionality
            const actionModal = document.getElementById('actionModal');
            const actionForm = document.getElementById('actionForm');
            const confirmActionBtn = document.getElementById('confirmActionBtn');
            
            // Handle confirm button click
            confirmActionBtn.addEventListener('click', function() {
                actionForm.submit();
            });
        });
        
        // Function to show action modal
        function showActionModal(verificationId, action, userName) {
            const modal = document.getElementById('actionModal');
            const modalTitle = document.getElementById('actionModalTitle');
            const actionMessage = document.getElementById('actionMessage');
            const verificationIdInput = document.getElementById('verificationId');
            const actionTypeInput = document.getElementById('actionType');
            const confirmBtn = document.getElementById('confirmActionBtn');
            const rejectionReasonDiv = document.getElementById('rejectionReasonDiv');
            
            // Set form values
            verificationIdInput.value = verificationId;
            actionTypeInput.value = action;
            
            // Update modal content based on action
            if (action === 'approve') {
                modalTitle.textContent = 'Approve ID Verification';
                actionMessage.textContent = `Are you sure you want to approve the ID verification for ${userName}?`;
                confirmBtn.className = 'btn btn-success';
                confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Approve';
                rejectionReasonDiv.style.display = 'none';
            } else if (action === 'reject') {
                modalTitle.textContent = 'Reject ID Verification';
                actionMessage.textContent = `Are you sure you want to reject the ID verification for ${userName}?`;
                confirmBtn.className = 'btn btn-danger';
                confirmBtn.innerHTML = '<i class="fas fa-times me-1"></i>Reject';
                rejectionReasonDiv.style.display = 'block';
            }
            
            // Show modal
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#actionModal').modal('show');
            } else {
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.classList.add('modal-open');
                
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'actionBackdrop';
                document.body.appendChild(backdrop);
            }
        }
    </script>
</body>
</html>
