<?php
session_start();
include 'db.php';

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';

// Build the query
$query = "SELECT * FROM history_logs WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (action LIKE ? OR details LIKE ? OR performed_by LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action_filter) {
    $query .= " AND action LIKE ?";
    $params[] = "%$action_filter%";
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
$actions_stmt = $pdo->query("SELECT DISTINCT action FROM history_logs ORDER BY action");
$actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);
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
      background-color: var(--primary-color);
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
        <i class="fa fa-history text-primary me-3"></i>Activity History
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
                return $log['performed_by'] === 'admin';
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
            <div class="stat-icon" style="background-color: var(--bs-secondary);">
              <i class="fa fa-clock"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count(array_unique(array_column($history_logs, 'action'))); ?></h4>
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
              <input type="text" class="form-control" placeholder="Search actions, details, or users" name="search" value="<?php echo htmlspecialchars($search); ?>">
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
          <div class="col-md-3">
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

              // Determine action color
              $actionColor = 'bg-secondary';
              if (strpos(strtolower($log['action']), 'add') !== false) $actionColor = 'bg-success';
              if (strpos(strtolower($log['action']), 'edit') !== false) $actionColor = 'bg-warning text-dark';
              if (strpos(strtolower($log['action']), 'delete') !== false || strpos(strtolower($log['action']), 'deactivat') !== false) $actionColor = 'bg-danger';
              if (strpos(strtolower($log['action']), 'reactivat') !== false) $actionColor = 'bg-info';
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
                            <?php echo htmlspecialchars($log['action']); ?>
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
                            Performed by: <span class="fw-semibold"><?php echo htmlspecialchars($log['performed_by']); ?></span>
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