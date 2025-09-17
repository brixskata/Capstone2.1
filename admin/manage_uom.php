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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage UOM - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .uom-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
        }
        
        .uom-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            border-left: 4px solid #7F1734;
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
                    <i class="fa fa-ruler me-3" style="color: #7F1734;"></i>Manage UOM
                </h1>
                <p class="text-muted">Create and manage units of measurement</p>
            </div>
            <button class="btn" data-bs-toggle="modal" data-bs-target="#addUomModal" style="background-color: #7F1734; color: white; border: none;">
                <i class="fa fa-plus me-1"></i> Add UOM
            </button>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-ruler fa-2x" style="color: #7F1734;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count($uoms); ?></h4>
                            <p class="text-muted mb-0">Total UOMs</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-box fa-2x" style="color: #198754;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo array_sum(array_column($uoms, 'product_count')); ?></h4>
                            <p class="text-muted mb-0">Total Products</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-chart-bar fa-2x" style="color: #ffc107;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count(array_filter($uoms, function($uom) { return $uom['product_count'] > 0; })); ?></h4>
                            <p class="text-muted mb-0">Active UOMs</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- UOMs List -->
        <div class="row">
            <?php if (empty($uoms)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fa fa-ruler fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No UOMs found</h4>
                        <p class="text-muted">Start by creating your first unit of measurement</p>
                        <button class="btn" data-bs-toggle="modal" data-bs-target="#addUomModal" style="background-color: #7F1734; color: white; border: none;">
                            <i class="fa fa-plus me-1"></i> Add First UOM
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($uoms as $uom): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="uom-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($uom['name']); ?></h5>
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
                        <button type="submit" name="create_uom" class="btn" style="background-color: #7F1734; color: white; border: none;">Add UOM</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
