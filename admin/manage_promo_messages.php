<?php
session_start();
include '../includes/db.php';
include '../includes/permissions.php';
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

// Check if user is superadmin using the existing permission system
if (!isSuperAdmin($pdo)) {
    $_SESSION['error'] = "This page requires Super Admin access.";
    header('Location: admin_dashboard2.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $message_text = trim($_POST['message_text']);
                $icon_class = trim($_POST['icon_class']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $sort_order = (int)$_POST['sort_order'];
                
                if (!empty($message_text)) {
                    $stmt = $pdo->prepare("INSERT INTO promo_messages (message_text, icon_class, is_active, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$message_text, $icon_class, $is_active, $sort_order]);
                    $success_message = "Promo message added successfully!";
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $message_text = trim($_POST['message_text']);
                $icon_class = trim($_POST['icon_class']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $sort_order = (int)$_POST['sort_order'];
                
                if (!empty($message_text)) {
                    $stmt = $pdo->prepare("UPDATE promo_messages SET message_text = ?, icon_class = ?, is_active = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$message_text, $icon_class, $is_active, $sort_order, $id]);
                    $success_message = "Promo message updated successfully!";
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM promo_messages WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "Promo message deleted successfully!";
                break;
                
            case 'toggle_status':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE promo_messages SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "Promo message status updated!";
                break;
        }
    }
}

// Fetch all promo messages
$stmt = $pdo->query("SELECT * FROM promo_messages ORDER BY sort_order ASC, created_at ASC");
$promo_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Common icon classes for reference
$common_icons = [
    'fas fa-gift' => 'Gift',
    'fas fa-truck' => 'Truck',
    'fas fa-user-plus' => 'User Plus',
    'fas fa-star' => 'Star',
    'fas fa-clock' => 'Clock',
    'fas fa-percentage' => 'Percentage',
    'fas fa-shipping-fast' => 'Fast Shipping',
    'fas fa-award' => 'Award',
    'fas fa-shield-alt' => 'Shield',
    'fas fa-tags' => 'Tags',
    'fas fa-fire' => 'Fire',
    'fas fa-heart' => 'Heart'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Promo Messages - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .promo-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .promo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .promo-card-header {
            background: var(--bs-primary);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        }
        
        .promo-card-header h5 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .promo-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .promo-table .table {
            margin: 0;
        }
        
        .promo-table .table th {
            background: var(--bs-light);
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: var(--bs-dark);
            border-bottom: 2px solid #e9ecef;
        }
        
        .promo-table .table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .promo-table .table tbody tr:hover {
            background: var(--bs-light);
        }

        .icon-preview {
            display: inline-block;
            width: 20px;
            text-align: center;
        }
        
        .status-badge {
            font-size: 0.8rem;
        }

        .modal-content { 
            background-color: var(--card-bg) !important; 
            border-color: var(--border-color) !important; 
            color: var(--text-primary) !important; 
        }
        
        .modal-header, .modal-footer { 
            border-color: var(--border-color) !important; 
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
    <main class="main-content">
        <div class="main-container">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-bullhorn me-3"></i>Manage Promo Messages</h2>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($promo_messages); ?></h3>
                            <p class="card-label">Total Messages</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($promo_messages, function($msg) { return $msg['is_active'] == 1; })); ?></h3>
                            <p class="card-label">Active Messages</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($promo_messages, function($msg) { return $msg['is_active'] == 0; })); ?></h3>
                            <p class="card-label">Inactive Messages</p>
                        </div>
                    </div>
                </div>
            </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Add New Promo Message -->
                <div class="promo-card mb-4">
                    <div class="promo-card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle"></i>
                            Add New Promo Message
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="message_text" class="form-label">Message Text</label>
                                        <input type="text" class="form-control" id="message_text" name="message_text" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="icon_class" class="form-label">Icon Class</label>
                                        <select class="form-select" id="icon_class" name="icon_class">
                                            <?php foreach ($common_icons as $class => $name): ?>
                                                <option value="<?= $class ?>"><?= $name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label for="sort_order" class="form-label">Sort Order</label>
                                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Message
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Promo Messages List -->
                <div class="promo-card">
                    <div class="promo-card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bullhorn"></i>
                            Current Promo Messages
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($promo_messages)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-info-circle fa-2x mb-3"></i>
                                <p>No promo messages found. Add your first message above.</p>
                            </div>
                        <?php else: ?>
                            <div class="promo-table">
                                <div class="table-responsive">
                                    <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Sort</th>
                                            <th>Icon</th>
                                            <th>Message</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($promo_messages as $message): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary"><?= $message['sort_order'] ?></span>
                                                </td>
                                                <td>
                                                    <i class="<?= htmlspecialchars($message['icon_class']) ?> icon-preview"></i>
                                                </td>
                                                <td><?= htmlspecialchars($message['message_text']) ?></td>
                                                <td>
                                                    <span class="badge <?= $message['is_active'] ? 'bg-success' : 'bg-danger' ?> status-badge">
                                                        <?= $message['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($message['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $message['id'] ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="id" value="<?= $message['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-<?= $message['is_active'] ? 'warning' : 'success' ?>">
                                                                <i class="fas fa-<?= $message['is_active'] ? 'pause' : 'play' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $message['id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </main>

    <!-- Modals -->
    <?php foreach ($promo_messages as $message): ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $message['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $message['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?= $message['id'] ?>">
                            <i class="fas fa-edit me-2"></i>Edit Promo Message
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $message['id'] ?>">
                            
                            <div class="mb-3">
                                <label for="edit_message_text_<?= $message['id'] ?>" class="form-label">Message Text</label>
                                <input type="text" class="form-control" id="edit_message_text_<?= $message['id'] ?>" name="message_text" value="<?= htmlspecialchars($message['message_text']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_icon_class_<?= $message['id'] ?>" class="form-label">Icon Class</label>
                                <select class="form-select" id="edit_icon_class_<?= $message['id'] ?>" name="icon_class">
                                    <?php foreach ($common_icons as $class => $name): ?>
                                        <option value="<?= $class ?>" <?= $class === $message['icon_class'] ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_sort_order_<?= $message['id'] ?>" class="form-label">Sort Order</label>
                                        <input type="number" class="form-control" id="edit_sort_order_<?= $message['id'] ?>" name="sort_order" value="<?= $message['sort_order'] ?>" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_is_active_<?= $message['id'] ?>" name="is_active" <?= $message['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="edit_is_active_<?= $message['id'] ?>">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal<?= $message['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $message['id'] ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel<?= $message['id'] ?>">
                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Confirm Delete
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action cannot be undone.
                        </div>
                        <p>Are you sure you want to delete this promo message?</p>
                        <div class="bg-light p-3 rounded">
                            <strong>Message:</strong> <?= htmlspecialchars($message['message_text']) ?><br>
                            <strong>Icon:</strong> <i class="<?= htmlspecialchars($message['icon_class']) ?>"></i> <?= $common_icons[$message['icon_class']] ?? $message['icon_class'] ?><br>
                            <strong>Status:</strong> <span class="badge <?= $message['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $message['is_active'] ? 'Active' : 'Inactive' ?></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $message['id'] ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i>Delete Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
