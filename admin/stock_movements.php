<?php
include 'db.php';
include_once '../includes/log_history.php';
include_once 'inventory_alerts.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

// Get filter parameters
$product_filter = $_GET['product'] ?? '';
$movement_type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$limit = $_GET['limit'] ?? 50;

// Build query with filters
$where_conditions = [];
$params = [];

if ($product_filter) {
    $where_conditions[] = "p.name LIKE ?";
    $params[] = "%$product_filter%";
}

if ($movement_type) {
    $where_conditions[] = "sm.movement_type = ?";
    $params[] = $movement_type;
}

if ($date_from) {
    $where_conditions[] = "sm.created_at >= ?";
    $params[] = $date_from . " 00:00:00";
}

if ($date_to) {
    $where_conditions[] = "sm.created_at <= ?";
    $params[] = $date_to . " 23:59:59";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch stock movements
$query = "
    SELECT sm.*, p.name as product_name, p.stock as current_stock, c.name as category_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    $where_clause
    ORDER BY sm.created_at DESC
    LIMIT ?
";
$params[] = $limit;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch products for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM products WHERE is_archived = 0 ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate movement statistics
$total_movements = count($movements);
$total_in = array_sum(array_map(fn($m) => $m['movement_type'] === 'in' ? $m['quantity'] : 0, $movements));
$total_out = array_sum(array_map(fn($m) => $m['movement_type'] === 'out' ? $m['quantity'] : 0, $movements));
$total_adjustments = array_sum(array_map(fn($m) => $m['movement_type'] === 'adjustment' ? $m['quantity'] : 0, $movements));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements - Inventory Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
      body { font-family: 'Poppins', 'Arial', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-950 to-gray-900 min-h-screen flex">
  <!-- Sidebar -->
  <aside class="w-64 min-h-screen bg-gradient-to-b from-cyan-700 via-blue-800 to-purple-900 shadow-xl flex flex-col">
    <div class="p-8 flex flex-col items-center border-b border-blue-900">
      <span class="text-3xl font-extrabold tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-400 to-pink-400 drop-shadow-lg">MikeMadz</span>
      <span class="mt-2 text-xs text-cyan-200 tracking-widest uppercase">Admin Panel</span>
    </div>
    <nav class="flex-1 mt-6 space-y-1 px-4">
      <a href="admin_dashboard2.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-cyan-600 hover:to-blue-700 text-cyan-100 hover:text-white transition">
        <i class="fa fa-gauge"></i> Dashboard
      </a>
      <a href="transaction_logs.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-pink-600 hover:to-purple-700 text-cyan-100 hover:text-white transition">
        <i class="fa fa-cart-shopping"></i> Transactions
      </a>
      <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-blue-600 hover:to-cyan-700 text-cyan-100 hover:text-white transition">
        <i class="fa fa-box"></i> Products
      </a>
      <a href="inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-gradient-to-r from-cyan-600 to-blue-700 text-white font-bold shadow-md">
        <i class="fa fa-warehouse"></i> Inventory
      </a>
      <div x-data="{ open: true }" class="space-y-1">
        <button type="button" onclick="toggleMaintenance()" id="maintenanceToggle" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-purple-600 hover:to-pink-700 text-cyan-100 hover:text-white transition focus:outline-none">
          <i class="fa fa-users"></i> Maintenance
          <svg id="maintenanceChevron" class="ml-auto w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="maintenanceSubmenu" class="pl-8 space-y-1">
          <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-green-600 hover:to-teal-700 text-cyan-100 hover:text-white transition">
            <i class="fa fa-id-card"></i> Accounts
          </a>
          <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-orange-600 hover:to-yellow-700 text-cyan-100 hover:text-white transition">
            <i class="fa fa-cubes"></i> Products
          </a>
          <a href="manage_suppliers.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-red-600 hover:to-pink-700 text-cyan-100 hover:text-white transition">
            <i class="fa fa-truck"></i> Suppliers
          </a>
        </div>
      </div>
      <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-pink-600 hover:to-purple-700 text-cyan-100 hover:text-white transition">
        <i class="fa fa-chart-bar"></i> Reports
      </a>
      <a href="history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-blue-600 hover:to-cyan-700 text-cyan-100 hover:text-white transition">
        <i class="fa fa-clock-rotate-left"></i> History
      </a>
      <a href="logout_admin.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gradient-to-r hover:from-red-600 hover:to-pink-700 text-cyan-100 hover:text-white transition">
        <i class="fa fa-sign-out-alt"></i> Logout
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8 md:p-12 bg-gradient-to-br from-gray-900 via-gray-950 to-gray-900 min-h-screen">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-400 to-pink-400 flex items-center gap-2">
        <i class="fas fa-chart-line"></i> Stock Movements
      </h1>
      <a href="inventory.php" class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white font-bold px-6 py-2 hover:from-blue-500 hover:to-cyan-500 transition flex items-center gap-2">
        <i class="fa fa-arrow-left"></i> Back to Inventory
      </a>
    </div>

    <!-- Movement Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="rounded-2xl bg-gradient-to-br from-cyan-700 to-blue-800 shadow-lg p-6 flex flex-col items-center">
        <i class="fa fa-list text-3xl text-cyan-300 mb-2"></i>
        <div class="text-2xl font-bold text-cyan-300"><?= $total_movements ?></div>
        <div class="text-cyan-100">Total Movements</div>
      </div>
      <div class="rounded-2xl bg-gradient-to-br from-green-600 to-teal-700 shadow-lg p-6 flex flex-col items-center">
        <i class="fa fa-arrow-up text-3xl text-green-300 mb-2"></i>
        <div class="text-2xl font-bold text-green-300"><?= $total_in ?></div>
        <div class="text-green-100">Stock In</div>
      </div>
      <div class="rounded-2xl bg-gradient-to-br from-red-600 to-pink-700 shadow-lg p-6 flex flex-col items-center">
        <i class="fa fa-arrow-down text-3xl text-red-300 mb-2"></i>
        <div class="text-2xl font-bold text-red-300"><?= $total_out ?></div>
        <div class="text-red-100">Stock Out</div>
      </div>
      <div class="rounded-2xl bg-gradient-to-br from-purple-600 to-pink-700 shadow-lg p-6 flex flex-col items-center">
        <i class="fa fa-edit text-3xl text-purple-300 mb-2"></i>
        <div class="text-2xl font-bold text-purple-300"><?= $total_adjustments ?></div>
        <div class="text-purple-100">Adjustments</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 shadow-lg p-6 mb-8">
      <h3 class="text-xl font-bold text-cyan-100 mb-4">Filter Movements</h3>
      <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
          <label class="block text-cyan-100 font-bold mb-2">Product</label>
          <select name="product" class="rounded-lg bg-gray-900 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
            <option value="">All Products</option>
            <?php foreach ($products as $product): ?>
              <option value="<?= htmlspecialchars($product['name']) ?>" <?= $product_filter === $product['name'] ? 'selected' : '' ?>><?= htmlspecialchars($product['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-cyan-100 font-bold mb-2">Movement Type</label>
          <select name="type" class="rounded-lg bg-gray-900 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
            <option value="">All Types</option>
            <option value="in" <?= $movement_type === 'in' ? 'selected' : '' ?>>Stock In</option>
            <option value="out" <?= $movement_type === 'out' ? 'selected' : '' ?>>Stock Out</option>
            <option value="adjustment" <?= $movement_type === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
          </select>
        </div>
        <div>
          <label class="block text-cyan-100 font-bold mb-2">Date From</label>
          <input type="date" name="date_from" value="<?= $date_from ?>" class="rounded-lg bg-gray-900 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
        </div>
        <div>
          <label class="block text-cyan-100 font-bold mb-2">Date To</label>
          <input type="date" name="date_to" value="<?= $date_to ?>" class="rounded-lg bg-gray-900 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
        </div>
        <div class="flex items-end">
          <button type="submit" class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white font-bold px-6 py-2 hover:from-blue-500 hover:to-cyan-500 transition">Filter</button>
        </div>
      </form>
    </div>

    <!-- Movements Table -->
    <div class="rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 shadow-lg p-6">
      <h3 class="text-xl font-bold text-cyan-100 mb-4">Movement History</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl overflow-hidden">
          <thead>
            <tr class="bg-gray-800 text-cyan-300">
              <th class="px-4 py-3 font-semibold">Date & Time</th>
              <th class="px-4 py-3 font-semibold">Product</th>
              <th class="px-4 py-3 font-semibold">Category</th>
              <th class="px-4 py-3 font-semibold">Type</th>
              <th class="px-4 py-3 font-semibold">Quantity</th>
              <th class="px-4 py-3 font-semibold">Previous Stock</th>
              <th class="px-4 py-3 font-semibold">New Stock</th>
              <th class="px-4 py-3 font-semibold">Reason</th>
              <th class="px-4 py-3 font-semibold">By</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($movements)): ?>
              <tr>
                <td colspan="9" class="px-4 py-8 text-center text-cyan-400">
                  <i class="fas fa-inbox text-4xl mb-2"></i>
                  <div>No stock movements found for the selected filters.</div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($movements as $movement): ?>
                <tr class="border-b border-gray-700 hover:bg-gray-800 transition-colors duration-200">
                  <td class="px-4 py-3 text-cyan-300"><?= date('M d, Y H:i', strtotime($movement['created_at'])) ?></td>
                  <td class="px-4 py-3 text-cyan-100"><?= htmlspecialchars($movement['product_name']) ?></td>
                  <td class="px-4 py-3 text-cyan-300"><?= htmlspecialchars($movement['category_name']) ?></td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                      <?= $movement['movement_type'] === 'in' ? 'bg-green-900 text-green-200' : '' ?>
                      <?= $movement['movement_type'] === 'out' ? 'bg-red-900 text-red-200' : '' ?>
                      <?= $movement['movement_type'] === 'adjustment' ? 'bg-blue-900 text-blue-200' : '' ?>">
                      <?= ucfirst($movement['movement_type']) ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-cyan-100 font-semibold"><?= $movement['quantity'] ?></td>
                  <td class="px-4 py-3 text-cyan-300"><?= $movement['previous_stock'] ?></td>
                  <td class="px-4 py-3 text-cyan-100 font-semibold"><?= $movement['new_stock'] ?></td>
                  <td class="px-4 py-3 text-cyan-300"><?= htmlspecialchars($movement['reason']) ?></td>
                  <td class="px-4 py-3 text-cyan-300"><?= htmlspecialchars($movement['created_by']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Movement Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
      <!-- Top Moving Products -->
      <div class="rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 shadow-lg p-6">
        <h3 class="text-xl font-bold text-cyan-100 mb-4">Top Moving Products (Last 30 Days)</h3>
        <div class="space-y-3">
          <?php 
          $top_moving = getTopMovingProducts($pdo, 5, 30);
          foreach ($top_moving as $product): ?>
            <div class="flex justify-between items-center p-3 rounded-lg bg-gray-800">
              <div>
                <div class="text-cyan-100 font-semibold"><?= htmlspecialchars($product['product_name']) ?></div>
                <div class="text-xs text-cyan-400">Current Stock: <?= $product['current_stock'] ?></div>
              </div>
              <div class="text-right">
                <div class="text-cyan-300 font-semibold"><?= $product['total_out'] ?> units</div>
                <div class="text-xs text-cyan-400"><?= $product['out_transactions'] ?> transactions</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Slow Moving Products -->
      <div class="rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 shadow-lg p-6">
        <h3 class="text-xl font-bold text-cyan-100 mb-4">Slow Moving Products (Last 30 Days)</h3>
        <div class="space-y-3">
          <?php 
          $slow_moving = getSlowMovingProducts($pdo, 5, 30);
          foreach ($slow_moving as $product): ?>
            <div class="flex justify-between items-center p-3 rounded-lg bg-gray-800">
              <div>
                <div class="text-cyan-100 font-semibold"><?= htmlspecialchars($product['product_name']) ?></div>
                <div class="text-xs text-cyan-400">Current Stock: <?= $product['current_stock'] ?></div>
              </div>
              <div class="text-right">
                <div class="text-cyan-300 font-semibold"><?= $product['total_out'] ?> units</div>
                <div class="text-xs text-cyan-400"><?= $product['out_transactions'] ?> transactions</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>

  <script>
    // Collapsible Maintenance
    function toggleMaintenance() {
      const submenu = document.getElementById('maintenanceSubmenu');
      const chevron = document.getElementById('maintenanceChevron');
      if (submenu.style.display === 'none') {
        submenu.style.display = '';
        chevron.style.transform = 'rotate(0deg)';
      } else {
        submenu.style.display = 'none';
        chevron.style.transform = 'rotate(-90deg)';
      }
    }
    
    // Default expanded
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('maintenanceSubmenu').style.display = '';
      document.getElementById('maintenanceChevron').style.transform = 'rotate(0deg)';
    });
  </script>
</body>
</html> 