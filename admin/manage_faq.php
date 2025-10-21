<?php
session_start();
require_once '../includes/db.php';
include_once '../includes/permissions.php';

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

// Check if user has FAQ management permission or is Super Admin
if (!isSuperAdmin($pdo) && !hasPermission($pdo, 'faq_manage')) {
    $_SESSION['error'] = "You don't have permission to access FAQ management.";
    header("Location: login_admin.php");
    exit;
}

$message = '';
$error = '';

// Handle form submission for answering questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    $faq_id = (int)$_POST['faq_id'];
    $answer = trim($_POST['answer']);
    $admin_id = $_SESSION['user_id'];
    
    if (empty($answer)) {
        $error = 'Please enter an answer.';
    } elseif (strlen($answer) < 10) {
        $error = 'Answer must be at least 10 characters long.';
    } elseif (strlen($answer) > 1000) {
        $error = 'Answer must not exceed 1000 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE faq_questions 
                SET answer = ?, answered_by = ?, status = 'answered', answered_at = NOW() 
                WHERE faq_id = ?
            ");
            $stmt->execute([$answer, $admin_id, $faq_id]);
            $message = 'Answer submitted successfully!';
        } catch (Exception $e) {
            $error = 'Failed to submit answer. Please try again.';
        }
    }
}

// Handle editing answers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_answer'])) {
    $faq_id = (int)$_POST['faq_id'];
    $answer = trim($_POST['answer']);
    $admin_id = $_SESSION['user_id'];
    
    if (empty($answer)) {
        $error = 'Please enter an answer.';
    } elseif (strlen($answer) < 10) {
        $error = 'Answer must be at least 10 characters long.';
    } elseif (strlen($answer) > 1000) {
        $error = 'Answer must not exceed 1000 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE faq_questions 
                SET answer = ?, answered_by = ?, answered_at = NOW() 
                WHERE faq_id = ?
            ");
            $stmt->execute([$answer, $admin_id, $faq_id]);
            $message = 'Answer updated successfully!';
        } catch (Exception $e) {
            $error = 'Failed to update answer. Please try again.';
        }
    }
}

// Handle archiving questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_question'])) {
    $faq_id = (int)$_POST['faq_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE faq_questions SET status = 'archived' WHERE faq_id = ?");
        $stmt->execute([$faq_id]);
        $message = 'Question archived successfully!';
    } catch (Exception $e) {
        $error = 'Failed to archive question. Please try again.';
    }
}

// Fetch FAQ questions
$status_filter = $_GET['status'] ?? 'all';
$where_clause = '';
$params = [];

if ($status_filter !== 'all') {
    $where_clause = 'WHERE fq.status = ?';
    $params[] = $status_filter;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            fq.*,
            u.username,
            ui.first_name,
            ui.last_name,
            ua.username as answered_by_username
        FROM faq_questions fq
        LEFT JOIN users u ON fq.user_id = u.user_id
        LEFT JOIN user_info ui ON fq.user_id = ui.user_id
        LEFT JOIN users ua ON fq.answered_by = ua.user_id
        $where_clause
        ORDER BY fq.created_at DESC
    ");
    $stmt->execute($params);
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $faqs = [];
    $error = 'Failed to load FAQ data.';
}

// Get statistics
try {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM faq_questions");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM faq_questions WHERE status = 'pending'");
    $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as answered FROM faq_questions WHERE status = 'answered'");
    $stats['answered'] = $stmt->fetch(PDO::FETCH_ASSOC)['answered'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as archived FROM faq_questions WHERE status = 'archived'");
    $stats['archived'] = $stmt->fetch(PDO::FETCH_ASSOC)['archived'];
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'answered' => 0, 'archived' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--bs-secondary);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bs-secondary);
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .faq-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .faq-question {
            background: var(--bs-light);
            border-left: 4px solid var(--bs-secondary);
            padding: 1.5rem;
        }

        .faq-answer {
            background: white;
            border-left: 4px solid var(--bs-success);
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-answered {
            background-color: #d1edff;
            color: #0c5460;
        }

        .status-archived {
            background-color: #f8d7da;
            color: #721c24;
        }

        .user-info {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .timestamp {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .btn-primary {
            background-color: var(--bs-secondary);
            border-color: var(--bs-secondary);
        }

        .btn-primary:hover {
            background-color: #5a1022;
            border-color: #5a1022;
        }

        .filter-tabs {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            color: #6c757d;
            transition: all 0.2s;
        }

        .filter-tab.active {
            background-color: var(--bs-secondary);
            color: white;
        }

        .filter-tab:hover {
            background-color: var(--bs-light);
            color: var(--bs-secondary);
        }

        .filter-tab.active:hover {
            background-color: #5a1022;
            color: white;
        }

        .answer-form {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
        }

        .edit-answer-form {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

    <div class="main-content">
        <div class="main-container">
            <!-- Page Header -->
            <div class="page-header">
                <h2>
                    <i class="fas fa-question-circle me-2"></i>
                    FAQ Management
                </h2>
                <p class="mb-0 opacity-75">Manage customer questions and provide answers</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['total']; ?></div>
                        <div class="stats-label">Total Questions</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-warning"><?php echo $stats['pending']; ?></div>
                        <div class="stats-label">Pending</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-success"><?php echo $stats['answered']; ?></div>
                        <div class="stats-label">Answered</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-secondary"><?php echo $stats['archived']; ?></div>
                        <div class="stats-label">Archived</div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="d-flex gap-2">
                    <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list me-1"></i> All (<?php echo $stats['total']; ?>)
                    </a>
                    <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock me-1"></i> Pending (<?php echo $stats['pending']; ?>)
                    </a>
                    <a href="?status=answered" class="filter-tab <?php echo $status_filter === 'answered' ? 'active' : ''; ?>">
                        <i class="fas fa-check me-1"></i> Answered (<?php echo $stats['answered']; ?>)
                    </a>
                    <a href="?status=archived" class="filter-tab <?php echo $status_filter === 'archived' ? 'active' : ''; ?>">
                        <i class="fas fa-archive me-1"></i> Archived (<?php echo $stats['archived']; ?>)
                    </a>
                </div>
            </div>

            <!-- FAQ Questions -->
            <?php if (!empty($faqs)): ?>
                <?php foreach ($faqs as $faq): ?>
                    <div class="card faq-card">
                        <div class="faq-question">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="status-badge status-<?php echo $faq['status']; ?>">
                                        <?php echo ucfirst($faq['status']); ?>
                                    </span>
                                    <span class="ms-2 user-info">
                                        <i class="fas fa-user me-1"></i>
                                        <?php 
                                        $display_name = $faq['first_name'] && $faq['last_name'] 
                                            ? $faq['first_name'] . ' ' . $faq['last_name']
                                            : $faq['username'];
                                        echo htmlspecialchars($display_name);
                                        ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="timestamp">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($faq['created_at'])); ?>
                                    </small>
                                    <!-- Archive question button -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                        <button type="submit" name="archive_question" class="btn btn-outline-danger btn-sm" 
                                                onclick="return confirm('Are you sure you want to archive this question?')"
                                                title="Archive Question">
                                            <i class="fas fa-archive"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($faq['question']); ?></p>
                        </div>

                        <?php if ($faq['answer']): ?>
                            <div class="faq-answer">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <small class="text-success fw-semibold">
                                        <i class="fas fa-user-tie me-1"></i>
                                        Answered by <?php echo htmlspecialchars($faq['answered_by_username']); ?>
                                    </small>
                                    <small class="timestamp">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($faq['answered_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                
                                <!-- Action buttons for answered questions -->
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="toggleEditForm(<?php echo $faq['faq_id']; ?>)">
                                        <i class="fas fa-edit me-1"></i>
                                        Edit Answer
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                        <button type="submit" name="archive_question" class="btn btn-outline-secondary btn-sm" 
                                                onclick="return confirm('Are you sure you want to archive this answered question?')">
                                            <i class="fas fa-archive me-1"></i>
                                            Archive
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Edit Answer Form (hidden by default) -->
                                <div id="edit-form-<?php echo $faq['faq_id']; ?>" class="edit-answer-form mt-3" style="display: none;">
                                    <form method="POST">
                                        <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                        <div class="mb-3">
                                            <label for="edit_answer_<?php echo $faq['faq_id']; ?>" class="form-label">
                                                <i class="fas fa-edit me-1"></i>
                                                Edit Answer
                                            </label>
                                            <textarea 
                                                class="form-control" 
                                                id="edit_answer_<?php echo $faq['faq_id']; ?>" 
                                                name="answer" 
                                                rows="4" 
                                                placeholder="Update your answer..."
                                                maxlength="1000"
                                                required
                                            ><?php echo htmlspecialchars($faq['answer']); ?></textarea>
                                            <div class="form-text">
                                                <span class="edit-char-count-<?php echo $faq['faq_id']; ?>"><?php echo strlen($faq['answer']); ?></span>/1000 characters
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="edit_answer" class="btn btn-primary btn-sm">
                                                <i class="fas fa-save me-1"></i>
                                                Update Answer
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                    onclick="toggleEditForm(<?php echo $faq['faq_id']; ?>)">
                                                <i class="fas fa-times me-1"></i>
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Answer Form for Pending Questions -->
                            <div class="answer-form">
                                <form method="POST">
                                    <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                    <div class="mb-3">
                                        <label for="answer_<?php echo $faq['faq_id']; ?>" class="form-label">
                                            <i class="fas fa-reply me-1"></i>
                                            Your Answer
                                        </label>
                                        <textarea 
                                            class="form-control" 
                                            id="answer_<?php echo $faq['faq_id']; ?>" 
                                            name="answer" 
                                            rows="4" 
                                            placeholder="Provide a helpful answer to this question..."
                                            maxlength="1000"
                                            required
                                        ></textarea>
                                        <div class="form-text">
                                            <span class="char-count-<?php echo $faq['faq_id']; ?>">0</span>/1000 characters
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="submit_answer" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>
                                            Submit Answer
                                        </button>
                                        <button type="submit" name="archive_question" class="btn btn-outline-secondary" 
                                                onclick="return confirm('Are you sure you want to archive this question?')">
                                            <i class="fas fa-archive me-1"></i>
                                            Archive
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-question-circle"></i>
                    <h5>No questions found</h5>
                    <p>No questions match the current filter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        // Toggle edit form visibility
        function toggleEditForm(faqId) {
            const editForm = document.getElementById('edit-form-' + faqId);
            const editButton = document.querySelector(`button[onclick="toggleEditForm(${faqId})"]`);
            
            if (editForm.style.display === 'none') {
                editForm.style.display = 'block';
                editButton.innerHTML = '<i class="fas fa-times me-1"></i>Cancel Edit';
                editButton.classList.remove('btn-outline-primary');
                editButton.classList.add('btn-outline-secondary');
            } else {
                editForm.style.display = 'none';
                editButton.innerHTML = '<i class="fas fa-edit me-1"></i>Edit Answer';
                editButton.classList.remove('btn-outline-secondary');
                editButton.classList.add('btn-outline-primary');
            }
        }

        // Character counters for answer textareas
        document.querySelectorAll('textarea[name="answer"]').forEach(function(textarea) {
            const faqId = textarea.id.split('_')[1];
            const charCountElement = document.querySelector('.char-count-' + faqId);
            
            if (charCountElement) {
                textarea.addEventListener('input', function() {
                    const charCount = this.value.length;
                    charCountElement.textContent = charCount;
                    
                    if (charCount > 900) {
                        charCountElement.style.color = '#dc3545';
                    } else if (charCount > 800) {
                        charCountElement.style.color = '#ffc107';
                    } else {
                        charCountElement.style.color = '#6c757d';
                    }
                });
            }
        });

        // Character counters for edit answer textareas
        document.querySelectorAll('textarea[id^="edit_answer_"]').forEach(function(textarea) {
            const faqId = textarea.id.split('_')[2];
            const charCountElement = document.querySelector('.edit-char-count-' + faqId);
            
            if (charCountElement) {
                textarea.addEventListener('input', function() {
                    const charCount = this.value.length;
                    charCountElement.textContent = charCount;
                    
                    if (charCount > 900) {
                        charCountElement.style.color = '#dc3545';
                    } else if (charCount > 800) {
                        charCountElement.style.color = '#ffc107';
                    } else {
                        charCountElement.style.color = '#6c757d';
                    }
                });
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
