<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle UOM creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_uom'])) {
    $uom_name = trim($_POST['uom_name']);
    
    if (!empty($uom_name)) {
        try {
            // Check if UOM already exists
            $stmt = $pdo->prepare("SELECT 1 FROM uom WHERE name = ?");
            $stmt->execute([$uom_name]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "UOM '$uom_name' already exists";
            } else {
                // Create new UOM
                $stmt = $pdo->prepare("INSERT INTO uom (name) VALUES (?)");
                $stmt->execute([$uom_name]);
                
                $_SESSION['success'] = "UOM '$uom_name' created successfully";
                logHistory($pdo, 'UOM Created', "Created new UOM: $uom_name", $_SESSION['username']);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating UOM: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "UOM name is required";
    }
    
    header("Location: manage_uom.php");
    exit;
}

// Handle UOM deletion
if (isset($_GET['delete'])) {
    $uom_id = (int)$_GET['delete'];
    
    try {
        // Check if UOM is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE uom_id = ?");
        $stmt->execute([$uom_id]);
        $productCount = $stmt->fetchColumn();
        
        if ($productCount > 0) {
            $_SESSION['error'] = "Cannot delete UOM: $productCount product(s) are using this UOM";
        } else {
            // Get UOM name for logging
            $stmt = $pdo->prepare("SELECT name FROM uom WHERE uom_id = ?");     
            $stmt->execute([$uom_id]);
            $uom = $stmt->fetch();
            
            // Delete UOM
            $stmt = $pdo->prepare("DELETE FROM uom WHERE uom_id = ?");
            $stmt->execute([$uom_id]);
            
            $_SESSION['success'] = "UOM '{$uom['name']}' deleted successfully";
            logHistory($pdo, 'UOM Deleted', "Deleted UOM: {$uom['name']}", $_SESSION['username']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting UOM: " . $e->getMessage();
    }
    
    header("Location: manage_uom.php");
    exit;
}

// Fetch all UOMs with product counts
$stmt = $pdo->query("
    SELECT u.uom_id, u.name,
           COUNT(p.product_id) as product_count
    FROM uom u
    LEFT JOIN products p ON u.uom_id = p.uom_id
    GROUP BY u.uom_id, u.name
    ORDER BY u.name
");
$uoms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage UOM - Admin Dashboard</title>
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

        .uom-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .uom-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
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
                        <h2><i class="fas fa-ruler me-2"></i>Manage UOM</h2>
                        <p class="mb-0 opacity-75">Create and manage units of measurement</p>
                    </div>
                    <button class="btn text-white fw-bold px-4" 
                            style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                            data-bs-toggle="modal" data-bs-target="#addUomModal">
                        <i class="fa fa-plus me-2"></i>Add UOM
                    </button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-ruler"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($uoms); ?></h3>
                            <p class="card-label">Total UOMs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo array_sum(array_column($uoms, 'product_count')); ?></h3>
                            <p class="card-label">Total Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($uoms, function($uom) { return $uom['product_count'] > 0; })); ?></h3>
                            <p class="card-label">Active UOMs</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- UOMs List -->
            <div class="row g-4">
                <?php if (empty($uoms)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-ruler fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No UOMs found</h4>
                            <p class="text-muted">Start by creating your first unit of measurement</p>
                            <button class="btn text-white fw-bold px-4" 
                                    style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                                    data-bs-toggle="modal" data-bs-target="#addUomModal">
                                <i class="fa fa-plus me-2"></i>Add First UOM
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($uoms as $uom): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="uom-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($uom['name']); ?></h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item text-danger" href="manage_uom.php?delete=<?php echo $uom['uom_id']; ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this UOM? This action cannot be undone.')">
                                                    <i class="fa fa-trash me-2"></i>Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-box me-2 text-muted"></i>
                                        <span class="text-muted"><?php echo $uom['product_count']; ?> product(s)</span>
                                    </div>
                                    
                                    <?php if ($uom['product_count'] > 0): ?>
                                        <span class="badge bg-success">Active</span>
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

    <!-- Add UOM Modal -->
    <div class="modal fade" id="addUomModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="manage_uom.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New UOM</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">UOM Name</label>
                            <input type="text" class="form-control" name="uom_name" required 
                                   placeholder="e.g., pieces, kilos, boxes, liters" 
                                   maxlength="50">
                            <small class="text-muted">Enter the unit of measurement name</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_uom" class="btn text-white fw-bold px-4" 
                                style="background-color: #7F1734; border-radius: 8px;">Add UOM</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
