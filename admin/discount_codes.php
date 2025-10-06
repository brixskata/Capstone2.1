<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle discount code operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount_type = $_POST['discount_type'] ?? '';
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    if ($action === 'add') {
        if (!empty($code) && !empty($discount_type) && $discount_value > 0) {
            try {
                // Check if code already exists
                $stmt = $pdo->prepare("SELECT 1 FROM discount_codes WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = "Discount code '$code' already exists";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO discount_codes (code, discount_type, discount_value, is_active, expires_at) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $discount_type, $discount_value, $is_active, $expires_at]);
                    
                    $_SESSION['success'] = "Discount code '$code' created successfully";
                    logHistory($pdo, 'Discount Code Created', "Created discount code: $code", $_SESSION['username']);
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error creating discount code: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Please fill in all required fields";
        }
    } elseif ($action === 'edit' && !empty($_POST['discount_id'])) {
        $discount_id = (int)$_POST['discount_id'];
        if (!empty($code) && !empty($discount_type) && $discount_value > 0) {
            try {
                // Check if code already exists (excluding current record)
                $stmt = $pdo->prepare("SELECT 1 FROM discount_codes WHERE code = ? AND id != ?");
                $stmt->execute([$code, $discount_id]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = "Discount code '$code' already exists";
                } else {
                    $stmt = $pdo->prepare("UPDATE discount_codes SET code=?, discount_type=?, discount_value=?, is_active=?, expires_at=? WHERE id=?");
                    $stmt->execute([$code, $discount_type, $discount_value, $is_active, $expires_at, $discount_id]);
                    
                    $_SESSION['success'] = "Discount code '$code' updated successfully";
                    logHistory($pdo, 'Discount Code Updated', "Updated discount code: $code", $_SESSION['username']);
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error updating discount code: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Please fill in all required fields";
        }
    }
    
    header("Location: discount_codes.php");
    exit;
}

// Handle discount code deletion
if (isset($_GET['delete'])) {
    $discount_id = (int)$_GET['delete'];
    
    try {
        // Get discount code for logging
        $stmt = $pdo->prepare("SELECT code FROM discount_codes WHERE id = ?");
        $stmt->execute([$discount_id]);
        $discount = $stmt->fetch();
        
        if ($discount) {
            $stmt = $pdo->prepare("DELETE FROM discount_codes WHERE id = ?");
            $stmt->execute([$discount_id]);
            
            $_SESSION['success'] = "Discount code '{$discount['code']}' deleted successfully";
            logHistory($pdo, 'Discount Code Deleted', "Deleted discount code: {$discount['code']}", $_SESSION['username']);
        } else {
            $_SESSION['error'] = "Discount code not found";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting discount code: " . $e->getMessage();
    }
    
    header("Location: discount_codes.php");
    exit;
}

// Fetch all discount codes
$stmt = $pdo->query("SELECT * FROM discount_codes ORDER BY id DESC");
$discountCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Codes - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Analytics Cards - Light Version */
        .analytics-card {
            background: white;
            color: var(--bs-dark);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .analytics-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bs-primary);
        }

        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            background: rgba(127, 23, 52, 0.1);
            color: var(--bs-primary);
        }

        .card-content {
            flex: 1;
        }

        .card-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bs-primary);
            margin: 0;
            line-height: 1;
        }

        .card-label {
            color: var(--bs-secondary);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0.5rem 0 0 0;
        }

        .discount-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .discount-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        
        .discount-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #7F1734;
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
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="main-container">
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

            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-percent me-2"></i>Discount Codes</h2>
                        <p class="mb-0 opacity-75">Create and manage discount codes for customers</p>
                    </div>
                    <button class="btn text-white fw-bold px-4" 
                            style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                            data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                        <i class="fa fa-plus me-2"></i>Add Discount Code
                    </button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-percent"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($discountCodes); ?></h3>
                            <p class="card-label">Total Codes</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($discountCodes, function($code) { return $code['is_active']; })); ?></h3>
                            <p class="card-label">Active Codes</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($discountCodes, function($code) { return !$code['is_active']; })); ?></h3>
                            <p class="card-label">Inactive Codes</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($discountCodes, function($code) { return $code['expires_at'] && strtotime($code['expires_at']) > time(); })); ?></h3>
                            <p class="card-label">Expiring Soon</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discount Codes List -->
            <div class="row g-4">
                <?php if (empty($discountCodes)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-percent fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No discount codes found</h4>
                            <p class="text-muted">Start by creating your first discount code</p>
                            <button class="btn text-white fw-bold px-4" 
                                    style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                                    data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                                <i class="fa fa-plus me-2"></i>Add First Discount Code
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($discountCodes as $discount): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="discount-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($discount['code']); ?></h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="editDiscount(<?php echo htmlspecialchars(json_encode($discount)); ?>)">
                                                    <i class="fa fa-edit me-2"></i>Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="discount_codes.php?delete=<?php echo $discount['id']; ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this discount code?')">
                                                    <i class="fa fa-trash me-2"></i>Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="text-center mb-3">
                                    <div class="discount-value">
                                        <?php if ($discount['discount_type'] === 'percent'): ?>
                                            <?php echo $discount['discount_value']; ?>%
                                        <?php else: ?>
                                            ₱<?php echo number_format($discount['discount_value'], 2); ?>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $discount['discount_type'] === 'percent' ? 'Percentage Discount' : 'Fixed Amount Discount'; ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <?php if ($discount['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($discount['expires_at']): ?>
                                        <small class="text-muted">
                                            Expires: <?php echo date('M d, Y', strtotime($discount['expires_at'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">No expiration</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add/Edit Discount Modal -->
    <div class="modal fade" id="addDiscountModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="discount_codes.php" method="POST" id="discountForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add New Discount Code</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add" id="formAction">
                        <input type="hidden" name="discount_id" id="discountId">
                        
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" name="code" id="discountCode" required 
                                   placeholder="e.g., SAVE20, WELCOME10" 
                                   maxlength="20">
                            <small class="text-muted">Enter a unique discount code (will be converted to uppercase)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="discount_type" id="discountType" required>
                                <option value="percent">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₱)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Value</label>
                            <input type="number" step="0.01" class="form-control" name="discount_value" id="discountValue" required 
                                   placeholder="Enter discount value">
                            <small class="text-muted" id="valueHelp">Enter the percentage value (e.g., 20 for 20%)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Expires At</label>
                            <input type="datetime-local" class="form-control" name="expires_at" id="discountExpires">
                            <small class="text-muted">Leave empty for no expiration</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="discountActive" checked>
                                <label for="discountActive" class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold px-4" id="submitBtn" 
                                style="background-color: #7F1734; border-radius: 8px;">Add Discount Code</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        // Update value help text based on discount type
        document.getElementById('discountType').addEventListener('change', function() {
            const valueHelp = document.getElementById('valueHelp');
            if (this.value === 'percent') {
                valueHelp.textContent = 'Enter the percentage value (e.g., 20 for 20%)';
            } else {
                valueHelp.textContent = 'Enter the fixed amount in pesos (e.g., 100 for ₱100)';
            }
        });

        // Edit discount function
        function editDiscount(discount) {
            document.getElementById('modalTitle').textContent = 'Edit Discount Code';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('discountId').value = discount.id;
            document.getElementById('discountCode').value = discount.code;
            document.getElementById('discountType').value = discount.discount_type;
            document.getElementById('discountValue').value = discount.discount_value;
            document.getElementById('discountExpires').value = discount.expires_at ? discount.expires_at.replace(' ', 'T') : '';
            document.getElementById('discountActive').checked = discount.is_active == 1;
            document.getElementById('submitBtn').textContent = 'Update Discount Code';
            
            // Update value help text
            const valueHelp = document.getElementById('valueHelp');
            if (discount.discount_type === 'percent') {
                valueHelp.textContent = 'Enter the percentage value (e.g., 20 for 20%)';
            } else {
                valueHelp.textContent = 'Enter the fixed amount in pesos (e.g., 100 for ₱100)';
            }
            
            new bootstrap.Modal(document.getElementById('addDiscountModal')).show();
        }

        // Reset form when modal is hidden
        document.getElementById('addDiscountModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('modalTitle').textContent = 'Add New Discount Code';
            document.getElementById('formAction').value = 'add';
            document.getElementById('discountId').value = '';
            document.getElementById('discountForm').reset();
            document.getElementById('discountActive').checked = true;
            document.getElementById('submitBtn').textContent = 'Add Discount Code';
        });
    </script>
</body>
</html>

</body>
</html>
