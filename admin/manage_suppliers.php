<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle form submission for adding new supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $name = $_POST['name'];
        $contact_info = $_POST['contact_info'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, address_line, notes, is_archive) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$name, $phone, $email, $address, $notes]);

        logHistory($pdo, 'Added Supplier', 'Supplier Name: ' . $name . ', Contact: ' . $contact_info, $_SESSION['username']);

        $_SESSION['success'] = "Supplier added successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adding supplier: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier editing (modal submit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    try {
        $supplierId = (int)$_POST['supplier_id'];
        $name = $_POST['name'];
        $contact_info = $_POST['contact_info']; // for log only
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, address_line = ?, notes = ? WHERE supplier_id = ?");
        $stmt->execute([$name, $phone, $email, $address, $notes, $supplierId]);

        logHistory($pdo, 'Edited Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $name . ', Contact: ' . $contact_info, $_SESSION['username']);

        $_SESSION['success'] = "Supplier updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating supplier: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier archiving
if (isset($_GET['archive']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE suppliers SET is_archive = 1 WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);

    $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    logHistory($pdo, 'Archived Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $supplier['name'], $_SESSION['username']);

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier unarchiving
if (isset($_GET['unarchive']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE suppliers SET is_archive = 0 WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);

    $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    logHistory($pdo, 'Unarchived Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $supplier['name'], $_SESSION['username']);

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);

    // Check if supplier has associated products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $productCount = $stmt->fetchColumn();

    if ($productCount > 0) {
        $_SESSION['error'] = "Cannot delete supplier. They have " . $productCount . " associated products.";
    } else {
        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch();
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);

        logHistory($pdo, 'Deleted Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . ($supplier['name'] ?? ''), $_SESSION['username']);
        $_SESSION['success'] = "Supplier deleted successfully!";
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Fetch active suppliers
$activeSuppliers = $pdo->query("SELECT supplier_id AS id, name, phone, email, CONCAT_WS(', ', address_line, city, country) AS address, notes FROM suppliers WHERE is_archive = 0 ORDER BY name")->fetchAll();

// Fetch archived suppliers
$archivedSuppliers = $pdo->query("SELECT supplier_id AS id, name, phone, email, CONCAT_WS(', ', address_line, city, country) AS address, notes FROM suppliers WHERE is_archive = 1 ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Suppliers - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <?php include 'includes/admin_styles.php'; ?>
  <style>
    .supplier-card {
      background-color: var(--card-bg) !important;
      border-radius: 12px;
      padding: 20px;
      box-shadow: var(--card-shadow) !important;
      border: 1px solid var(--border-color) !important;
      transition: all 0.2s ease;
      color: var(--text-primary) !important;
    }

    .supplier-card:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,.12); }
    .supplier-card.archived { border-left: 4px solid #dc3545; opacity: .8; background-color: var(--bg-primary) !important; }

    .modal-content { background-color: var(--card-bg) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important; }
    .modal-header, .modal-footer { border-color: var(--border-color) !important; }
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <div class="mb-4">
      <h1 class="h3 fw-bold text-dark mb-2">
        <i class="fa fa-truck me-2" style="color: #7F1734;"></i>Supplier Management
      </h1>
    </div>

    <!-- Alerts -->
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

    <!-- Add Supplier Button -->
    <div class="mb-4 d-flex gap-2">
      <button type="button" class="btn text-white fw-bold px-4" style="background-color: #7F1734;" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="fa fa-plus me-2"></i>Add New Supplier
      </button>
    </div>

    <!-- Active Suppliers -->
    <div class="mb-5">
      <h4 class="fw-bold text-dark mb-3">Active Suppliers</h4>
      <div class="row g-4">
        <?php if (empty($activeSuppliers)): ?>
          <div class="col-12">
            <div class="text-center py-5">
              <i class="fa fa-truck display-4 text-muted mb-3"></i>
              <h5 class="text-muted">No active suppliers found</h5>
              <p class="text-muted">Add your first supplier using the form above.</p>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($activeSuppliers as $supplier): ?>
            <div class="col-lg-6 col-xl-4">
              <div class="supplier-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <h5 class="fw-bold mb-0"><?= htmlspecialchars($supplier['name']) ?></h5>
                  <div class="dropdown">
                    <button class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                      <i class="fa fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu">
                      <li><button class="dropdown-item" onclick="openEditSupplierModal(this)"
                        data-id="<?= $supplier['id'] ?>"
                        data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                        data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>">
                        <i class="fa fa-edit me-2"></i>Edit
                      </button></li>
                      <li><hr class="dropdown-divider"></li>
                      <li><a class="dropdown-item text-warning" href="manage_suppliers.php?archive=1&id=<?= $supplier['id'] ?>" onclick="return confirm('Archive this supplier?')">
                        <i class="fa fa-archive me-2"></i>Archive
                      </a></li>
                    </ul>
                  </div>
                </div>

                <div class="text-muted small">
                  <?php if ($supplier['phone']): ?>
                    <div class="mb-1"><i class="fa fa-phone me-2"></i><?= htmlspecialchars($supplier['phone']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['email']): ?>
                    <div class="mb-1"><i class="fa fa-envelope me-2"></i><?= htmlspecialchars($supplier['email']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['address']): ?>
                    <div class="mb-1"><i class="fa fa-map-marker me-2"></i><?= htmlspecialchars($supplier['address']) ?></div>
                  <?php endif; ?>
                </div>

                <?php if (!empty($supplier['notes'])): ?>
                  <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted"><?= htmlspecialchars($supplier['notes']) ?></small>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Archived Suppliers -->
    <?php if (!empty($archivedSuppliers)): ?>
      <div class="mb-5">
        <h4 class="fw-bold text-danger mb-3">Archived Suppliers</h4>
        <div class="row g-4">
          <?php foreach ($archivedSuppliers as $supplier): ?>
            <div class="col-lg-6 col-xl-4">
              <div class="supplier-card archived">
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <h5 class="fw-bold mb-0 text-danger"><?= htmlspecialchars($supplier['name']) ?></h5>
                  <div class="dropdown">
                    <button class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                      <i class="fa fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu">
                      <li><button class="dropdown-item" onclick="openEditSupplierModal(this)"
                        data-id="<?= $supplier['id'] ?>"
                        data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                        data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>">
                        <i class="fa fa-edit me-2"></i>Edit
                      </button></li>
                      <li><hr class="dropdown-divider"></li>
                      <li><a class="dropdown-item text-success" href="manage_suppliers.php?unarchive=1&id=<?= $supplier['id'] ?>" onclick="return confirm('Unarchive this supplier?')">
                        <i class="fa fa-undo me-2"></i>Unarchive
                      </a></li>
                      <li><a class="dropdown-item text-danger" href="manage_suppliers.php?delete=1&id=<?= $supplier['id'] ?>" onclick="return confirm('Permanently delete this supplier?')">
                        <i class="fa fa-trash me-2"></i>Delete
                      </a></li>
                    </ul>
                  </div>
                </div>

                <div class="text-muted small">
                  <?php if ($supplier['phone']): ?>
                    <div class="mb-1"><i class="fa fa-phone me-2"></i><?= htmlspecialchars($supplier['phone']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['email']): ?>
                    <div class="mb-1"><i class="fa fa-envelope me-2"></i><?= htmlspecialchars($supplier['email']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['address']): ?>
                    <div class="mb-1"><i class="fa fa-map-marker me-2"></i><?= htmlspecialchars($supplier['address']) ?></div>
                  <?php endif; ?>
                </div>

                <?php if (!empty($supplier['notes'])): ?>
                  <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted"><?= htmlspecialchars($supplier['notes']) ?></small>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <!-- Add Supplier Modal -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-plus me-2" style="color: #7F1734;"></i>Add New Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier Name</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Person</label>
                <input type="text" name="contact_info" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="tel" name="phone" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn text-white fw-bold" style="background-color: #7F1734;">
              <i class="fa fa-plus me-2"></i>Add Supplier
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Supplier Modal (same design as Add) -->
  <div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-edit me-2" style="color: #7F1734;"></i>Edit Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Person</label>
                <input type="text" name="contact_info" id="edit_contact_info" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="tel" name="phone" id="edit_phone" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" id="edit_email" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn text-white fw-bold" style="background-color: #7F1734;">
              <i class="fa fa-save me-2"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    function openEditSupplierModal(btn) {
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name') || '';
      const phone = btn.getAttribute('data-phone') || '';
      const email = btn.getAttribute('data-email') || '';
      const address = btn.getAttribute('data-address') || '';
      const notes = btn.getAttribute('data-notes') || '';
      // Optional: contact_info not in schema; keep for UI consistency
      const contact = '';

      document.getElementById('edit_supplier_id').value = id;
      document.getElementById('edit_name').value = name;
      document.getElementById('edit_phone').value = phone;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_address').value = address;
      document.getElementById('edit_notes').value = notes;
      const ci = document.getElementById('edit_contact_info');
      if (ci) ci.value = contact;

      const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
      modal.show();
    }
  </script>
</body>
</html>