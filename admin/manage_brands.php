<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle brand creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_brand'])) {
    $brand_name = trim($_POST['brand_name']);
    
    if (!empty($brand_name)) {
        try {
            // Check if brand already exists
            $stmt = $pdo->prepare("SELECT 1 FROM brands WHERE name = ? AND is_archived = 0");
            $stmt->execute([$brand_name]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Brand '$brand_name' already exists";
            } else {
                // Create new brand
                $stmt = $pdo->prepare("INSERT INTO brands (name, is_archived) VALUES (?, 0)");
                $stmt->execute([$brand_name]);
                
                $_SESSION['success'] = "Brand '$brand_name' created successfully";
                logHistory($pdo, 'Brand Created', "Created new brand: $brand_name", $_SESSION['username']);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating brand: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Brand name is required";
    }
    
    header("Location: manage_brands.php");
    exit;
}

// Handle brand archiving
if (isset($_GET['archive'])) {
    $brand_id = (int)$_GET['archive'];
    
    try {
        // Get brand name for logging
        $stmt = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
        $stmt->execute([$brand_id]);
        $brand = $stmt->fetch();
        
        if ($brand) {
            // Archive brand (soft delete)
            $stmt = $pdo->prepare("UPDATE brands SET is_archived = 1 WHERE id = ?");
            $stmt->execute([$brand_id]);
            
            $_SESSION['success'] = "Brand '{$brand['name']}' archived successfully";
            logHistory($pdo, 'Brand Archived', "Archived brand: {$brand['name']}", $_SESSION['username']);
        } else {
            $_SESSION['error'] = "Brand not found";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error archiving brand: " . $e->getMessage();
    }
    
    header("Location: manage_brands.php");
    exit;
}

// Handle brand unarchiving
if (isset($_GET['unarchive'])) {
    $brand_id = (int)$_GET['unarchive'];
    
    try {
        // Get brand name for logging
        $stmt = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
        $stmt->execute([$brand_id]);
        $brand = $stmt->fetch();
        
        if ($brand) {
            // Unarchive brand (restore)
            $stmt = $pdo->prepare("UPDATE brands SET is_archived = 0 WHERE id = ?");
            $stmt->execute([$brand_id]);
            
            $_SESSION['success'] = "Brand '{$brand['name']}' restored successfully";
            logHistory($pdo, 'Brand Restored', "Restored brand: {$brand['name']}", $_SESSION['username']);
        } else {
            $_SESSION['error'] = "Brand not found";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error restoring brand: " . $e->getMessage();
    }
    
    header("Location: manage_brands.php");
    exit;
}

// Handle brand deletion
if (isset($_GET['delete'])) {
    $brand_id = (int)$_GET['delete'];
    
    try {
        // Check if brand is in use across all tables
        $usage_checks = [
            'products' => "SELECT COUNT(*) FROM products WHERE brand_id = ?",
            'product_batches' => "SELECT COUNT(*) FROM product_batches WHERE brand_id = ?",
            'cart_items' => "SELECT COUNT(*) FROM cart_items WHERE brand_id = ?",
            'order_items' => "SELECT COUNT(*) FROM order_items WHERE brand_id = ?"
        ];
        
        $total_usage = 0;
        $usage_details = [];
        
        foreach ($usage_checks as $table => $query) {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$brand_id]);
            $count = $stmt->fetchColumn();
            $total_usage += $count;
            if ($count > 0) {
                $usage_details[] = "$count in $table";
            }
        }
        
        if ($total_usage > 0) {
            $details = implode(', ', $usage_details);
            $_SESSION['error'] = "Cannot delete brand: Brand is being used ($details)";
        } else {
            // Get brand name for logging
            $stmt = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
            $stmt->execute([$brand_id]);
            $brand = $stmt->fetch();
            
            // Delete brand
            $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
            $stmt->execute([$brand_id]);
            
            $_SESSION['success'] = "Brand '{$brand['name']}' deleted successfully";
            logHistory($pdo, 'Brand Deleted', "Deleted brand: {$brand['name']}", $_SESSION['username']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting brand: " . $e->getMessage();
    }
    
    header("Location: manage_brands.php");
    exit;
}

// Fetch all brands with product and batch counts
$stmt = $pdo->query("
    SELECT b.id, b.name, b.is_archived,
           COUNT(DISTINCT p.product_id) as product_count,
           COUNT(DISTINCT pb.batch_id) as batch_count,
           COALESCE(SUM(pb.quantity_remaining), 0) as total_stock
    FROM brands b
    LEFT JOIN products p ON b.id = p.brand_id
    LEFT JOIN product_batches pb ON b.id = pb.brand_id AND pb.is_active = 1
    WHERE b.is_archived = 0
    GROUP BY b.id, b.name, b.is_archived
    ORDER BY b.name
");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch archived brands
$archived_stmt = $pdo->query("
    SELECT b.id, b.name, b.is_archived,
           COUNT(DISTINCT p.product_id) as product_count,
           COUNT(DISTINCT pb.batch_id) as batch_count,
           COALESCE(SUM(pb.quantity_remaining), 0) as total_stock
    FROM brands b
    LEFT JOIN products p ON b.id = p.brand_id
    LEFT JOIN product_batches pb ON b.id = pb.brand_id AND pb.is_active = 1
    WHERE b.is_archived = 1
    GROUP BY b.id, b.name, b.is_archived
    ORDER BY b.name
");
$archived_brands = $archived_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Brands - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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

        .brand-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .brand-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .btn-primary-custom {
            background-color: transparent;
            color: #E8B4C8;
            border: 2px solid #E8B4C8;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary-custom:hover {
            background-color: #E8B4C8;
            color: white;
            border-color: #E8B4C8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(232, 180, 200, 0.4);
        }

        .btn-danger-custom {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-danger-custom:hover {
            background-color: #b02a37;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
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
        
        /* SweetAlert2 Custom Styles */
        .swal2-popup-custom {
            border-radius: 20px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            border: 1px solid #e9ecef !important;
        }
        
        .swal2-title-custom {
            color: var(--bs-primary) !important;
            font-weight: 700 !important;
            font-size: 1.5rem !important;
        }
        
        .swal2-html-container-custom {
            color: var(--bs-dark) !important;
            font-size: 1rem !important;
        }
        
        .swal2-confirm-button-custom {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #a91d42 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }

        .swal2-confirm-button-custom:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.3) !important;
            background: linear-gradient(135deg, #6b1429 0%, #8b1a36 100%) !important;
        }

        .swal2-cancel-button-custom {
            border: 2px solid var(--bs-secondary) !important;
            color: var(--bs-secondary) !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            background: transparent !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }

        .swal2-cancel-button-custom:hover {
            background: var(--bs-secondary) !important;
            color: white !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.2) !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="main-container">
            <!-- Success/Error messages handled by SweetAlert2 -->

            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-trademark me-2"></i>Manage Brands</h2>
                        <p class="mb-0 opacity-75">Create and manage product brands</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn text-white fw-bold px-4" 
                                style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                                data-bs-toggle="modal" data-bs-target="#addBrandModal">
                            <i class="fa fa-plus me-2"></i>Add Brand
                        </button>
                        <?php if (!empty($archived_brands)): ?>
                            <button class="btn text-white fw-bold px-4" 
                                    style="background-color: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);" 
                                    data-bs-toggle="modal" data-bs-target="#archivedBrandsModal">
                                <i class="fa fa-archive me-2"></i>Archived (<?= count($archived_brands) ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-trademark"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($brands); ?></h3>
                            <p class="card-label">Total Brands</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-warehouse"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo number_format(array_sum(array_column($brands, 'total_stock')), 1); ?></h3>
                            <p class="card-label">Total Stock (kg)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($brands, function($brand) { return $brand['product_count'] > 0; })); ?></h3>
                            <p class="card-label">Active Brands</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Brands List -->
            <div class="row g-4">
                <?php if (empty($brands)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-trademark fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No brands found</h4>
                            <p class="text-muted">Start by creating your first brand</p>
                            <button class="btn text-white fw-bold px-4" 
                                    style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                                    data-bs-toggle="modal" data-bs-target="#addBrandModal">
                                <i class="fa fa-plus me-2"></i>Add First Brand
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($brands as $brand): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="brand-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($brand['name']); ?></h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                             <li>
                                                 <a class="dropdown-item text-warning" href="#" 
                                                    onclick="confirmArchive(<?php echo $brand['id']; ?>, '<?php echo htmlspecialchars($brand['name']); ?>'); return false;">
                                                     <i class="fa fa-archive me-2"></i>Archive
                                                 </a>
                                             </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-box me-2 text-muted"></i>
                                            <small class="text-muted"><?php echo $brand['product_count']; ?> product(s)</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fa fa-boxes me-2 text-muted"></i>
                                            <small class="text-muted"><?php echo $brand['batch_count']; ?> batch(es)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($brand['total_stock'] > 0): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fa fa-warehouse me-2 text-success"></i>
                                        <small class="text-success"><?php echo number_format($brand['total_stock'], 1); ?> kg stock</small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <?php if ($brand['batch_count'] > 0): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($brand['product_count'] > 0): ?>
                                        <span class="badge bg-warning">No Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Empty</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Brand Modal -->
    <div class="modal fade" id="addBrandModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="manage_brands.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Brand</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Brand Name</label>
                            <input type="text" class="form-control" name="brand_name" required 
                                   placeholder="e.g., Coca-Cola, Nestle, Unilever" 
                                   maxlength="100">
                            <small class="text-muted">Enter the brand name</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_brand" class="btn text-white fw-bold px-4" 
                                style="background-color: #7F1734; border-radius: 8px;">Add Brand</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Archived Brands Modal -->
    <div class="modal fade" id="archivedBrandsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-archive me-2"></i>Archived Brands</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($archived_brands)): ?>
                        <div class="text-center py-4">
                            <i class="fa fa-archive fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No archived brands</h5>
                            <p class="text-muted">Archived brands will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($archived_brands as $brand): ?>
                                <div class="col-md-6">
                                    <div class="brand-card" style="opacity: 0.7;">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($brand['name']); ?></h6>
                                            <div class="d-flex gap-2">
                                                <span class="badge bg-secondary">Archived</span>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="confirmRestore(<?php echo $brand['id']; ?>, '<?php echo htmlspecialchars($brand['name']); ?>')">
                                                    <i class="fa fa-undo me-1"></i>Restore
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa fa-box me-2 text-muted"></i>
                                                    <small class="text-muted"><?php echo $brand['product_count']; ?> product(s)</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa fa-boxes me-2 text-muted"></i>
                                                    <small class="text-muted"><?php echo $brand['batch_count']; ?> batch(es)</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($brand['total_stock'] > 0): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-warehouse me-2 text-success"></i>
                                                <small class="text-success"><?php echo number_format($brand['total_stock'], 1); ?> kg stock</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    
    <script>
        // Handle success/error messages with SweetAlert2
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?= addslashes($_SESSION['success']) ?>',
                    confirmButtonColor: '#7F1734',
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'swal2-popup-custom',
                        title: 'swal2-title-custom',
                        htmlContainer: 'swal2-html-container-custom',
                        confirmButton: 'swal2-confirm-button-custom'
                    }
                });
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?= addslashes($_SESSION['error']) ?>',
                    confirmButtonColor: '#7F1734',
                    customClass: {
                        popup: 'swal2-popup-custom',
                        title: 'swal2-title-custom',
                        htmlContainer: 'swal2-html-container-custom',
                        confirmButton: 'swal2-confirm-button-custom'
                    }
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });

        // SweetAlert2 confirmation functions
        function confirmArchive(brandId, brandName) {
            Swal.fire({
                title: 'Archive Brand',
                html: `
                    <div class="text-start">
                        <p>Are you sure you want to archive <strong>"${brandName}"</strong>?</p>
                        <p class="text-muted">This will hide it from the system but preserve all data.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-archive me-2"></i>Yes, Archive',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                customClass: {
                    popup: 'swal2-popup-custom',
                    title: 'swal2-title-custom',
                    htmlContainer: 'swal2-html-container-custom',
                    confirmButton: 'swal2-confirm-button-custom',
                    cancelButton: 'swal2-cancel-button-custom'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `manage_brands.php?archive=${brandId}`;
                }
            });
        }
        
        function confirmRestore(brandId, brandName) {
            Swal.fire({
                title: 'Restore Brand',
                html: `
                    <div class="text-start">
                        <p>Are you sure you want to restore <strong>"${brandName}"</strong>?</p>
                        <p class="text-muted">This will make it visible in the system again.</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-undo me-2"></i>Yes, Restore',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                customClass: {
                    popup: 'swal2-popup-custom',
                    title: 'swal2-title-custom',
                    htmlContainer: 'swal2-html-container-custom',
                    confirmButton: 'swal2-confirm-button-custom',
                    cancelButton: 'swal2-cancel-button-custom'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `manage_brands.php?unarchive=${brandId}`;
                }
            });
        }
    </script>
</body>
</html>
