<?php
// Include permissions for role checking
include_once '../includes/permissions.php';

// Fetch user profile information
$user_info = null;
if (isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, ui.first_name, ui.last_name, ui.profile_picture, ui.email
            FROM users u 
            LEFT JOIN user_info ui ON u.user_id = ui.user_id 
            WHERE u.username = ?
        ");
        $stmt->execute([$_SESSION['username']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback to basic info if query fails
        $user_info = ['username' => $_SESSION['username'], 'role' => $_SESSION['role'] ?? 'admin'];
    }
}

// Check if user is Super Admin
$is_super_admin = isSuperAdmin($pdo);

// Fetch dynamic notification counts
$notification_counts = [
    'notifications' => 0,
    'messages' => 0,
    'pending_verifications' => 0,
    'low_stock' => 0,
    'expiring_products' => 0
];

try {
    // Count pending ID verifications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customer_id_verification WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['pending_verifications'] = $result['count'] ?? 0;
    
    // Count low stock items (assuming reorder_point is set)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM product_stock ps 
                        JOIN products p ON ps.product_id = p.product_id 
                        WHERE ps.current_stock <= ps.reorder_point AND p.is_archived = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['low_stock'] = $result['count'] ?? 0;
    
    // Count recent orders (last 24 hours) as notifications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['notifications'] = $result['count'] ?? 0;
    
    // Count pending orders as messages
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Processing')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['messages'] = $result['count'] ?? 0;
    
    // Count expiring products (within 7 days) and expired batches
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM product_batches pb
                        WHERE pb.expiration_date IS NOT NULL 
                        AND pb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        AND pb.quantity_remaining > 0
                        AND pb.is_active = 1
                        AND pb.is_processed_expired = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['expiring_products'] = $result['count'] ?? 0;
    
} catch (Exception $e) {
    // Keep default values if queries fail
    error_log("Error fetching notification counts: " . $e->getMessage());
}

// Set profile picture and display name
$profile_picture = 'uploads/admin-avatar.png';
$display_name = 'Admin';
$role_display = 'Administrator';

if ($user_info) {
    if (!empty($user_info['profile_picture'])) {
        $profile_picture = '../uploads/profile_pictures/' . $user_info['profile_picture'];
    }
    
    if (!empty($user_info['first_name']) && !empty($user_info['last_name'])) {
        $display_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
    } else {
        $display_name = $user_info['username'];
    }
    
    // Set role display based on user role
    $user_role = $user_info['role'] ?? $_SESSION['role'] ?? 'admin';
    if ($is_super_admin) {
        $role_display = 'Super Administrator';
    } elseif ($user_role === 'admin') {
        $role_display = 'Administrator';
    } else {
        $role_display = ucfirst(str_replace('_', ' ', $user_role));
    }
}
?>

<!-- Enhanced Top Navbar -->
<nav class="top-navbar">
  <!-- Left Section -->
  <div class="navbar-left">
    <button class="sidebar-toggle-nav" id="sidebarToggleNav">
      <i class="fas fa-bars"></i>
    </button>
    <a href="<?php echo $is_super_admin ? 'admin_dashboard2.php' : 'basic_dashboard.php'; ?>" class="navbar-brand">
      <div class="brand-logo">
        <img src="../images/logo.png" alt="MikeMadz Logo" class="navbar-logo">
        <?php if ($is_super_admin): ?>
        <div class="logo-badge">
          <i class="fas fa-crown"></i>
        </div>
        <?php endif; ?>
      </div>
      <div class="brand-text">
        <span class="brand-name">MikeMadz</span>
        <span class="brand-subtitle">Frozen Product Store</span>
      </div>
    </a>
  </div>

  <!-- Center Section - Empty for future use -->
  <div class="navbar-center">
    <!-- Can be used for breadcrumbs, alerts, or other content -->
  </div>

  <!-- Right Section -->
  <div class="navbar-right">
    <!-- Live Clock - Moved here -->
    <div class="live-clock-container">
      <div class="clock-content">
        <span id="current-time" class="time-display">2:30 PM</span>
        <span class="clock-separator">•</span>
        <span id="current-date" class="date-display">Jan 20, 2024</span>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <button class="quick-action-btn" title="Expiring Products" onclick="showExpirationNotifications()">
        <i class="fas fa-bell"></i>
        <?php if ($notification_counts['expiring_products'] > 0): ?>
        <span class="notification-badge urgent"><?= $notification_counts['expiring_products'] ?></span>
        <?php endif; ?>
      </button>
    </div>

    <!-- Admin Profile -->
    <div class="dropdown admin-profile-dropdown">
      <div class="admin-profile" data-bs-toggle="dropdown" role="button">
        <div class="profile-avatar">
          <img src="<?php echo $profile_picture; ?>" alt="Profile" class="admin-avatar" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmOGY5ZmEiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzZjNzU3ZCIvPgo8cGF0aCBkPSJNOCAzMmMwLTYuNjI3IDUuMzczLTEyIDEyLTEyczEyIDUuMzczIDEyIDEyIiBmaWxsPSIjNmM3NTdkIi8+Cjwvc3ZnPgo='">
          <div class="status-indicator online"></div>
        </div>
        <div class="profile-info">
          <div class="profile-name"><?php echo htmlspecialchars($display_name); ?></div>
          <div class="profile-role"><?php echo $role_display; ?></div>
        </div>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      
      <div class="dropdown-menu dropdown-menu-end profile-dropdown">
        <div class="dropdown-header">
          <div class="profile-summary">
            <img src="<?php echo $profile_picture; ?>" alt="Profile" class="profile-avatar-large" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmOGY5ZmEiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzZjNzU3ZCIvPgo8cGF0aCBkPSJNOCAzMmMwLTYuNjI3IDUuMzczLTEyIDEyLTEyczEyIDUuMzczIDEyIDEyIiBmaWxsPSIjNmM3NTdkIi8+Cjwvc3ZnPgo='">
            <div class="profile-details">
              <div class="profile-name-large"><?php echo htmlspecialchars($display_name); ?></div>
              <div class="profile-email">@<?php echo htmlspecialchars($_SESSION['username']); ?></div>
              <div class="profile-status">
                <span class="status-dot online"></span>
                Online
              </div>
            </div>
          </div>
        </div>
        
        <div class="dropdown-divider"></div>
        
        <div class="dropdown-section">
          <div class="dropdown-section-title">Account</div>
          <a class="dropdown-item" href="profile_settings.php">
            <i class="fas fa-user"></i>
            <span>Profile Settings</span>
          </a>
      
        
       
       
        
        <div class="dropdown-divider"></div>
        
        <a class="dropdown-item logout-item" href="logout_admin.php">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- Expiration Notifications Modal -->
<div class="modal fade" id="expirationNotificationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fa fa-bell me-2" style="color: #dc3545;"></i>Expiring Products Alert
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="expirationNotificationsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading expiration notifications...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="batch_management.php?expiry_filter=expiring" class="btn btn-primary">
                    <i class="fa fa-external-link-alt me-1"></i>View All Batches
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function showExpirationNotifications() {
    // Show modal first
    const modal = new bootstrap.Modal(document.getElementById('expirationNotificationsModal'));
    modal.show();
    
    // Load notifications
    loadExpirationNotifications();
}

function loadExpirationNotifications() {
    const content = document.getElementById('expirationNotificationsContent');
    
    fetch('expiration_notifications.php?action=get_notifications')
        .then(response => response.json())
        .then(data => {
            console.log('Notifications loaded:', data);
            
            const notifications = [];
            // Flatten the grouped notifications
            Object.values(data).forEach(group => {
                notifications.push(...group);
            });
            
            if (notifications.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fa fa-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-success">All Good!</h5>
                        <p class="text-muted">No products are expiring within the next 7 days.</p>
                    </div>
                `;
                return;
            }
            
            // Process notifications by severity
            const severityOrder = ['danger', 'warning', 'info'];
            const severityLabels = {
                'danger': 'Expired/Critical',
                'warning': 'Expiring Soon',
                'info': 'Notice'
            };
            const severityClasses = {
                'danger': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            };
            
            let html = '<div class="row">';
            
            severityOrder.forEach(severity => {
                const groupNotifications = data[severity] || [];
                if (groupNotifications.length > 0) {
                    html += `
                        <div class="col-12 mb-3">
                            <div class="alert ${severityClasses[severity]}">
                                <h6 class="alert-heading">
                                    <i class="fa ${groupNotifications[0].icon} me-2"></i>
                                    ${severityLabels[severity]} (${groupNotifications.length})
                                </h6>
                                <div class="mt-2">
                    `;
                    
                    groupNotifications.forEach(notification => {
                        const isExpired = notification.type === 'expired_batch';
                        const actionText = isExpired ? 'Pull Out' : 'View Batch';
                        const buttonClass = isExpired ? 'btn-outline-danger' : 'btn-outline-warning';
                        
                        html += `
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong>${notification.product_name}</strong>
                                    <br>
                                    <small class="text-muted">Batch: ${notification.batch_number}</small>
                                </div>
                                <div class="text-end">
                                    <small class="text-${severity}">${notification.message}</small>
                                    <br>
                                    <button class="btn btn-sm ${buttonClass} mt-1" onclick="handlePullOut('${notification.batch_id}', '${notification.batch_number}', 0)">
                                        <i class="fa fa-box-open me-1"></i>${actionText}
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            
            html += '</div>';
            content.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            document.getElementById('expirationNotificationsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle me-2"></i>
                    Error loading expiration notifications. Please try again.
                </div>
            `;
        });
}

function handlePullOut(batchId, batchNumber, quantity) {
    // Close the notifications modal
    bootstrap.Modal.getInstance(document.getElementById('expirationNotificationsModal')).hide();
    
    // Redirect to batch management page with pull out action
    window.location.href = `batch_management.php?batch_id=${batchId}&action=pullout`;
}

// Live Clock Functionality
function updateClock() {
    const now = new Date();
    
    // Format time (12-hour format with AM/PM)
    const timeOptions = { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    };
    const timeString = now.toLocaleTimeString('en-US', timeOptions);
    
    // Format date
    const dateOptions = { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    };
    const dateString = now.toLocaleDateString('en-US', dateOptions);
    
    // Update DOM elements
    const timeElement = document.getElementById('current-time');
    const dateElement = document.getElementById('current-date');
    
    if (timeElement) timeElement.textContent = timeString;
    if (dateElement) dateElement.textContent = dateString;
}

// Initialize clock and update every second
document.addEventListener('DOMContentLoaded', function() {
    updateClock(); // Set initial time
    setInterval(updateClock, 1000); // Update every second
});
</script>

<style>
/* Right Section Layout */
.navbar-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.live-clock-container {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    min-width: 160px;
    order: 1; /* Clock first */
}

.quick-actions {
    order: 2; /* Quick actions second */
}

.admin-profile-dropdown {
    order: 3; /* Profile last */
}

.clock-content {
    display: flex;
    align-items: center;
    color: white;
    gap: 8px;
}

.time-display {
    font-size: 14px;
    opacity: 0.8;
    line-height: 1.2;
}

.clock-separator {
    font-size: 14px;
    opacity: 0.6;
    font-weight: 300;
}

.date-display {
    font-size: 14px;
    opacity: 0.8;
    line-height: 1.2;
}
</style>