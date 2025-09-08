<?php
session_start();
include 'db.php';
include '../includes/permissions.php';

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';

// Build the query with proper JOINs
$query = "SELECT hl.*, hat.name as action_name, u.username as performed_by_username, ut.role as performed_by_role
          FROM history_logs hl
          LEFT JOIN history_action_types hat ON hl.history_action_type_id = hat.history_action_type_id
          LEFT JOIN users u ON hl.performed_by = u.user_id
          LEFT JOIN user_type ut ON u.usertype_id = ut.usertype_id
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (hat.name LIKE ? OR hl.details LIKE ? OR u.username LIKE ? OR ut.role LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action_filter) {
    $query .= " AND hat.name LIKE ?";
    $params[] = "%$action_filter%";
}

if ($role_filter) {
    $query .= " AND ut.role = ?";
    $params[] = $role_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $query .= " AND DATE(performed_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND performed_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $query .= " AND performed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $query .= " AND performed_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

$query .= " ORDER BY performed_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$history_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter
$actions_stmt = $pdo->query("SELECT DISTINCT hat.name as action_name 
                            FROM history_logs hl
                            LEFT JOIN history_action_types hat ON hl.history_action_type_id = hat.history_action_type_id
                            WHERE hat.name IS NOT NULL
                            ORDER BY hat.name");
$actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique roles for filter
$roles_stmt = $pdo->query("SELECT DISTINCT ut.role 
                          FROM history_logs hl
                          LEFT JOIN users u ON hl.performed_by = u.user_id
                          LEFT JOIN user_type ut ON u.usertype_id = ut.usertype_id
                          WHERE ut.role IS NOT NULL
                          ORDER BY ut.role");
$roles = $roles_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Activity History - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?php include 'includes/admin_styles.php'; ?>
  <style>
    .history-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
      border: 1px solid #e9ecef;
    }

    .activity-item {
      padding: 16px;
      border-bottom: 1px solid #f1f3f4;
      transition: background-color 0.2s ease;
    }

    .activity-item:hover {
      background-color: #f8f9fa;
    }

    .activity-item:last-child {
      border-bottom: none;
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
      border: 1px solid #e9ecef;
      transition: transform 0.2s ease;
    }
    
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    }
    
    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: white;
    }

    .action-badge {
      font-size: 11px;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .timeline-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: #7F1734;
    }
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <div class="mb-4">
      <h1 class="h3 fw-bold text-dark mb-2">
        <i class="fa fa-history me-3" style="color: #7F1734;"></i>Activity History
      </h1>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-info">
              <i class="fa fa-list"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count($history_logs); ?></h4>
              <small class="text-muted text-uppercase">Total Logs</small>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-success">
              <i class="fa fa-calendar-day"></i>
            </div>
            <div class="ms-3">
              <?php
              $today_count = count(array_filter($history_logs, function($log) {
                return date('Y-m-d', strtotime($log['performed_at'])) === date('Y-m-d');
              }));
              ?>
              <h4 class="fw-bold mb-0"><?php echo $today_count; ?></h4>
              <small class="text-muted text-uppercase">Today's Activities</small>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-warning">
              <i class="fa fa-user-shield"></i>
            </div>
            <div class="ms-3">
              <?php
              $admin_count = count(array_filter($history_logs, function($log) {
                return $log['performed_by_role'] === 'admin' || $log['performed_by_username'] === 'admin';
              }));
              ?>
              <h4 class="fw-bold mb-0"><?php echo $admin_count; ?></h4>
              <small class="text-muted text-uppercase">Admin Actions</small>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon" style="background-color: #7F1734;">
              <i class="fa fa-clock"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count(array_unique(array_column($history_logs, 'action_name'))); ?></h4>
              <small class="text-muted text-uppercase">Action Types</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
      <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Search History</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa fa-search"></i></span>
              <input type="text" class="form-control" placeholder="Search actions, details, users, or roles" name="search" value="<?php echo htmlspecialchars($search); ?>">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Action Filter</label>
            <select class="form-select" name="action_filter">
              <option value="">All Actions</option>
              <?php foreach ($actions as $action): ?>
                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($action); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Date Filter</label>
            <select class="form-select" name="date_filter">
              <option value="">All Time</option>
              <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
              <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
              <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
              <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>This Year</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Role Filter</label>
            <select class="form-select" name="role_filter">
              <option value="">All Roles</option>
              <?php foreach ($roles as $role): ?>
                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars(ucfirst($role)); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-info w-100">
              <i class="fa fa-filter me-1"></i> Filter
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- History Timeline -->
    <div class="history-card">
      <div class="card-header bg-transparent border-0 py-4">
        <h5 class="fw-bold mb-0">
          <i class="fa fa-timeline text-secondary me-2"></i>Activity Timeline
        </h5>
      </div>
      <div class="card-body">
        <?php if (empty($history_logs)): ?>
          <div class="text-center py-5">
            <i class="fa fa-history display-4 text-muted mb-3"></i>
            <h5 class="text-muted">No history logs found</h5>
            <p class="text-muted">Try adjusting your search or filter criteria.</p>
          </div>
        <?php else: ?>
          <div class="row">
            <?php
            $currentDate = '';
            foreach ($history_logs as $index => $log):
              $logDate = date('Y-m-d', strtotime($log['performed_at']));
              $showDateHeader = $logDate !== $currentDate;
              $currentDate = $logDate;

              // Determine action color and name
              $actionColor = 'bg-secondary';
              $actionName = $log['action_name'] ?? 'Unknown';
              
              // If action_name is null or empty, try to determine from details
              if (empty($actionName) || $actionName === 'Unknown') {
                $details = strtolower($log['details'] ?? '');
                if (strpos($details, 'stock adjustment') !== false) $actionName = 'Stock Adjustment';
                elseif (strpos($details, 'restocking') !== false) $actionName = 'Restocking';
                elseif (strpos($details, 'added brand') !== false) $actionName = 'Added Brand';
                elseif (strpos($details, 'added uom') !== false) $actionName = 'Added UOM';
                elseif (strpos($details, 'deleted brand') !== false) $actionName = 'Deleted Brand';
                elseif (strpos($details, 'deleted uom') !== false) $actionName = 'Deleted UOM';
                elseif (strpos($details, 'login') !== false) $actionName = 'Login';
                else $actionName = 'System Action';
              }
              
              // Set colors based on action type
              if (strpos(strtolower($actionName), 'add') !== false) $actionColor = 'bg-success';
              elseif (strpos(strtolower($actionName), 'edit') !== false) $actionColor = 'bg-warning text-dark';
              elseif (strpos(strtolower($actionName), 'delete') !== false) $actionColor = 'bg-danger';
              elseif (strpos(strtolower($actionName), 'restock') !== false) $actionColor = 'bg-info';
              elseif (strpos(strtolower($actionName), 'adjustment') !== false) $actionColor = 'bg-warning text-dark';
              elseif (strpos(strtolower($actionName), 'login') !== false) $actionColor = 'text-white';
            ?>

              <?php if ($showDateHeader): ?>
                <div class="col-12">
                  <div class="text-center mb-4">
                    <h6 class="fw-bold text-muted text-uppercase">
                      <?php echo date('F j, Y', strtotime($log['performed_at'])); ?>
                    </h6>
                    <hr class="my-2">
                  </div>
                </div>
              <?php endif; ?>

              <div class="col-lg-6 col-12 mb-3">
                <div class="d-flex">
                  <div class="flex-shrink-0 me-3 text-center" style="width: 24px;">
                    <div class="timeline-dot"></div>
                  </div>
                  <div class="flex-grow-1">
                    <div class="history-card">
                      <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <span class="action-badge <?php echo $actionColor; ?>">
                            <?php echo htmlspecialchars($actionName); ?>
                          </span>
                          <small class="text-muted">
                            <?php echo date('g:i A', strtotime($log['performed_at'])); ?>
                          </small>
                        </div>

                        <p class="mb-2 text-dark">
                          <?php echo htmlspecialchars($log['details']); ?>
                        </p>

                        <div class="d-flex align-items-center">
                          <i class="fa fa-user text-muted me-2"></i>
                          <small class="text-muted">
                            Performed by: 
                            <?php if (!empty($log['performed_by_username'])): ?>
                              <span class="fw-semibold"><?php echo htmlspecialchars($log['performed_by_username']); ?></span>
                              <?php if (!empty($log['performed_by_role'])): ?>
                                <span class="badge ms-2" style="background-color: #7F1734;"><?php echo htmlspecialchars(ucfirst($log['performed_by_role'])); ?></span>
                              <?php else: ?>
                                <span class="badge bg-secondary ms-2">User</span>
                              <?php endif; ?>
                            <?php else: ?>
                              <span class="fw-semibold text-muted">System</span>
                              <span class="badge bg-dark ms-2">System</span>
                            <?php endif; ?>
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <?php include 'includes/admin_scripts.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>