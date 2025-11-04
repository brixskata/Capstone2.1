
<?php
// Include permissions for module access checks
include_once '../includes/permissions.php';

// Check if user has any module permissions (if not, only show Dashboard)
function hasAnyModulePermissions($pdo) {
    $modules = ['inventory', 'products', 'suppliers', 'users', 'system', 'reports'];
    foreach ($modules as $module) {
        if (hasModuleAccess($pdo, $module)) {
            return true;
        }
    }
    return false;
}

$userHasModulePermissions = hasAnyModulePermissions($pdo);

// Fetch dynamic counts for sidebar badges
$sidebar_counts = [
    'products' => 0,
    'categories' => 0,
    'brands' => 0,
    'archived_products' => 0,
    'discounts' => 0,
    'suppliers' => 0,
    'users' => 0,
    'pending_verifications' => 0,
    'transactions' => 0,
    'low_stock' => 0
];

try {
    // Count products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_archived = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['products'] = $result['count'] ?? 0;
    
    // Count categories
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['categories'] = $result['count'] ?? 0;
    
    // Count brands
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM brands WHERE is_archived = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['brands'] = $result['count'] ?? 0;
    
    // Count archived products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_archived = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['archived_products'] = $result['count'] ?? 0;
    
    // Count active discount codes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM discount_codes WHERE is_active = 1 AND (expiry_date IS NULL OR expiry_date > NOW())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['discounts'] = $result['count'] ?? 0;
    
    // Count suppliers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers WHERE is_archive = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['suppliers'] = $result['count'] ?? 0;
    
    // Count users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE usertype_id = 2");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['users'] = $result['count'] ?? 0;
    
    // Count pending ID verifications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customer_id_verification WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['pending_verifications'] = $result['count'] ?? 0;
    
    // Count recent transactions (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['transactions'] = $result['count'] ?? 0;
    
    // Count low stock items
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM product_stock ps 
                        JOIN products p ON ps.product_id = p.product_id 
                        WHERE ps.current_stock <= ps.reorder_point AND p.is_archived = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sidebar_counts['low_stock'] = $result['count'] ?? 0;
    
} catch (Exception $e) {
    // Keep default values if queries fail
    error_log("Error fetching sidebar counts: " . $e->getMessage());
}
?>

<!-- Enhanced Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
  <!-- Navigation Menu -->
  <div class="nav flex-column">
    <!-- Overview Section - Super Admin only -->
    <?php if (isSuperAdmin($pdo)): ?>
    <div class="nav-section">
      <div class="nav-section-title" data-bs-toggle="collapse" data-bs-target="#overviewDropdown" role="button">
        <div class="section-icon">
          <i class="fas fa-chart-pie"></i>
        </div>
        <span>Overview</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      <div class="nav-dropdown collapse show" id="overviewDropdown">
        <a href="admin_dashboard2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard2.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-tachometer-alt"></i>
          </div>
          <span>Dashboard</span>
          <div class="nav-indicator"></div>
          
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Sales Section -->
    <?php if ($userHasModulePermissions && hasPermission($pdo, 'order_view')): ?>
    <div class="nav-section">
      <div class="nav-section-title" data-bs-toggle="collapse" data-bs-target="#salesDropdown" role="button">
        <div class="section-icon">
          <i class="fas fa-shopping-cart"></i>
        </div>
        <span>Sales</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      <div class="nav-dropdown collapse show" id="salesDropdown">
        <a href="transaction_logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transaction_logs.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-receipt"></i>
          </div>
          <span>Transaction Logs</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['transactions'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['transactions'] ?></div>
          <?php endif; ?>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Inventory Management Section -->
    <?php if ($userHasModulePermissions && hasModuleAccess($pdo, 'inventory')): ?>
    <div class="nav-section">
      <div class="nav-section-title" data-bs-toggle="collapse" data-bs-target="#inventoryDropdown" role="button">
        <div class="section-icon">
          <i class="fas fa-boxes"></i>
        </div>
        <span>Inventory</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      <div class="nav-dropdown collapse show" id="inventoryDropdown">
        <a href="restocking.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'restocking.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-plus-circle"></i>
          </div>
          <span>Restocking</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['low_stock'] > 0): ?>
          <div class="nav-badge urgent"><?= $sidebar_counts['low_stock'] ?></div>
          <?php endif; ?>
        </a>
        <a href="stock_adjustment.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stock_adjustment.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-edit"></i>
          </div>
          <span>Stock Adjustment</span>
          <div class="nav-indicator"></div>
        </a>
        <a href="stock_levels.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'stock_levels.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <span>Stock Levels</span>
          <div class="nav-indicator"></div>
        </a>
        <a href="product_movement_analysis.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'product_movement_analysis.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-chart-bar"></i>
          </div>
          <span>Stock Movements</span>
          <div class="nav-indicator"></div>
        </a>
        <a href="batch_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'batch_management.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-boxes"></i>
          </div>
          <span>Batch Management</span>
          <div class="nav-indicator"></div>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Product Management Section -->
    <?php if ($userHasModulePermissions && hasModuleAccess($pdo, 'products')): ?>
    <div class="nav-section">
      <div class="nav-section-title" data-bs-toggle="collapse" data-bs-target="#productsDropdown" role="button">
        <div class="section-icon">
          <i class="fas fa-shopping-bag"></i>
        </div>
        <span>Products</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      <div class="nav-dropdown collapse show" id="productsDropdown">
        <a href="products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-box"></i>
          </div>
          <span>Products</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['products'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['products'] ?></div>
          <?php endif; ?>
        </a>
        <a href="add_product.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_product.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-plus-circle"></i>
          </div>
          <span>Add Product</span>
          <div class="nav-indicator"></div>
        </a>
        <a href="manage_categories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_categories.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-tags"></i>
          </div>
          <span>Categories</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['categories'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['categories'] ?></div>
          <?php endif; ?>
        </a>
        <a href="manage_brands.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_brands.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-trademark"></i>
          </div>
          <span>Brands</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['brands'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['brands'] ?></div>
          <?php endif; ?>
        </a>
        <a href="manage_uom.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_uom.php' ? 'active' : ''; ?>" style="display: none;">
          <div class="nav-icon">
            <i class="fas fa-ruler"></i>
          </div>
          <span>Units</span>
          <div class="nav-indicator"></div>
        </a>
        <a href="archived_products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'archived_products.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-archive"></i>
          </div>
          <span>Archived</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['archived_products'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['archived_products'] ?></div>
          <?php endif; ?>
        </a>
        <a href="discount_codes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'discount_codes.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-percent"></i>
          </div>
          <span>Discounts</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['discounts'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['discounts'] ?></div>
          <?php endif; ?>
        </a>
        <a href="manage_suppliers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_suppliers.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-truck"></i>
          </div>
          <span>Suppliers</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['suppliers'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['suppliers'] ?></div>
          <?php endif; ?>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Business Partners Section -->
    <?php if ($userHasModulePermissions && (hasModuleAccess($pdo, 'users') || isSuperAdmin($pdo))): ?>
    <div class="nav-section">
      <div class="nav-section-title" data-bs-toggle="collapse" data-bs-target="#partnersDropdown" role="button">
        <div class="section-icon">
          <i class="fas fa-cogs"></i>
        </div>
        <span>Maintenance</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      <div class="nav-dropdown collapse show" id="partnersDropdown">
        <?php if (hasModuleAccess($pdo, 'users')): ?>
        <a href="manage_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-users"></i>
          </div>
          <span>User Accounts</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['users'] > 0): ?>
          <div class="nav-badge"><?= $sidebar_counts['users'] ?></div>
          <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (isSuperAdmin($pdo)): ?>
        <a href="user_permissions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user_permissions.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-user-shield"></i>
          </div>
          <span>Roles</span>
          <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>
        <a href="id_verification_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'id_verification_management.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-id-card"></i>
          </div>
          <span>ID Verification</span>
          <div class="nav-indicator"></div>
          <?php if ($sidebar_counts['pending_verifications'] > 0): ?>
          <div class="nav-badge urgent"><?= $sidebar_counts['pending_verifications'] ?></div>
          <?php endif; ?>
        </a>
        <a href="manage_promo_messages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_promo_messages.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-bullhorn"></i>
          </div>
          <span>Promo Messages</span>
          <div class="nav-indicator"></div>
        </a>
        <?php if (isSuperAdmin($pdo) || hasPermission($pdo, 'faq_manage')): ?>
        <a href="manage_faq.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_faq.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-question-circle"></i>
          </div>
          <span>FAQ Management</span>
          <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>
        <?php if (hasModuleAccess($pdo, 'users')): ?>
        <a href="gcash_settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gcash_settings.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-mobile-alt"></i>
          </div>
          <span>GCash Settings</span>
          <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Monitoring Section -->
    <?php if ($userHasModulePermissions && (hasPermission($pdo, 'reports_view') || hasPermission($pdo, 'reports_analytics') || hasPermission($pdo, 'history_view'))): ?>
    <div class="nav-section">
      <div class="nav-section-title" data-bs-toggle="collapse" data-bs-target="#monitoringDropdown" role="button">
        <div class="section-icon">
          <i class="fas fa-chart-line"></i>
        </div>
        <span>Monitoring</span>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      <div class="nav-dropdown collapse show" id="monitoringDropdown">
        <?php if (hasPermission($pdo, 'reports_view') || hasPermission($pdo, 'reports_analytics')): ?>
        <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-chart-pie"></i>
          </div>
          <span>Reports</span>
          <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission($pdo, 'history_view')): ?>
        <a href="history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
          <div class="nav-icon">
            <i class="fas fa-history"></i>
          </div>
          <span>Activity Log</span>
          <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Bottom Section -->
    <div class="nav-section nav-section-bottom">
      <a href="logout_admin.php" class="nav-link nav-link-danger">
        <div class="nav-icon">
          <i class="fas fa-sign-out-alt"></i>
        </div>
        <span>Logout</span>
      </a>
    </div>
  </div>
</nav>

