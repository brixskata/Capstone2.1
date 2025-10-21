<?php
session_start();
require_once 'includes/db.php';

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$message = '';
$error = '';

// Handle form submission for new questions (only for logged-in users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    if (!$user_id) {
        $error = 'Please log in to ask a question.';
    } else {
        $question = trim($_POST['question']);
        
        if (empty($question)) {
            $error = 'Please enter your question.';
        } elseif (strlen($question) < 10) {
            $error = 'Question must be at least 10 characters long.';
        } elseif (strlen($question) > 500) {
            $error = 'Question must not exceed 500 characters.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO faq_questions (user_id, question) VALUES (?, ?)");
                $stmt->execute([$user_id, $question]);
                $message = 'Your question has been submitted successfully! We will get back to you soon.';
            } catch (Exception $e) {
                $error = 'Failed to submit your question. Please try again.';
            }
        }
    }
}

// Fetch FAQ questions and answers
try {
    $stmt = $pdo->prepare("
        SELECT 
            fq.*,
            u.username,
            ua.username as answered_by_username,
            ui.first_name,
            ui.last_name
        FROM faq_questions fq
        LEFT JOIN users u ON fq.user_id = u.user_id
        LEFT JOIN users ua ON fq.answered_by = ua.user_id
        LEFT JOIN user_info ui ON fq.user_id = ui.user_id
        WHERE fq.status IN ('answered', 'pending')
        ORDER BY fq.created_at DESC
    ");
    $stmt->execute();
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $faqs = [];
    $error = 'Failed to load FAQ data.';
}

// Get user's own questions
try {
    $stmt = $pdo->prepare("
        SELECT fq.*, ua.username as answered_by_username
        FROM faq_questions fq
        LEFT JOIN users ua ON fq.answered_by = ua.user_id
        WHERE fq.user_id = ?
        ORDER BY fq.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $user_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_questions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - MikeMadz</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #db3030;
            --bs-warning: #ffc107;
            --bs-info: #016bf8;
            --bs-light: #f0f3f2;
            --bs-dark: #001e2b;
        }

        .faq-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .faq-header {
            background: white;
            color: var(--bs-secondary);
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }

        .faq-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

        .question-form {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
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

        .section-title {
            color: var(--bs-secondary);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--bs-light);
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
    </style>
</head>
<body>
    <?php include 'includes/user_navbar.php'; ?>

    <div class="container-fluid">
        <div class="faq-container">
            <!-- Header -->
            <div class="faq-header">
                <div class="container">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-question-circle me-3"></i>
                        Frequently Asked Questions
                    </h1>
                    <p class="lead">Have a question? Ask us anything about our products and services!</p>
                </div>
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

            <!-- Ask Question Form -->
            <div class="question-form">
                <h3 class="section-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Ask a Question
                </h3>
                <?php if ($user_id): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="question" class="form-label">Your Question</label>
                            <textarea 
                                class="form-control" 
                                id="question" 
                                name="question" 
                                rows="4" 
                                placeholder="Ask us anything about our products, delivery, pricing, or services..."
                                maxlength="500"
                                required
                            ></textarea>
                            <div class="form-text">
                                <span id="char-count">0</span>/500 characters
                            </div>
                        </div>
                        <button type="submit" name="submit_question" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>
                            Submit Question
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-lock text-muted mb-3" style="font-size: 2rem;"></i>
                        <h5 class="text-muted">Login Required</h5>
                        <p class="text-muted mb-3">Please log in to ask questions and get personalized answers.</p>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Login to Ask Question
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Questions Section (only for logged-in users) -->
            <?php if ($user_id && !empty($user_questions)): ?>
                <div class="card faq-card">
                    <div class="card-header bg-light">
                        <h4 class="section-title mb-0">
                            <i class="fas fa-user me-2"></i>
                            My Questions
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($user_questions as $faq): ?>
                            <div class="faq-question">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="status-badge status-<?php echo $faq['status']; ?>">
                                        <?php echo ucfirst($faq['status']); ?>
                                    </span>
                                    <small class="timestamp">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($faq['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-2 fw-semibold"><?php echo htmlspecialchars($faq['question']); ?></p>
                                
                                <?php if ($faq['answer']): ?>
                                    <div class="faq-answer mt-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <small class="text-success fw-semibold">
                                                <i class="fas fa-user-tie me-1"></i>
                                                Admin Response
                                            </small>
                                            <small class="timestamp">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($faq['answered_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-hourglass-half me-1"></i>
                                            Waiting for admin response...
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Public FAQ Section -->
            <div class="card faq-card">
                <div class="card-header bg-light">
                    <h4 class="section-title mb-0">
                        <i class="fas fa-globe me-2"></i>
                        Community Questions & Answers
                    </h4>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($faqs)): ?>
                        <?php foreach ($faqs as $faq): ?>
                            <div class="faq-question">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="user-info">
                                        <i class="fas fa-user me-1"></i>
                                        <?php 
                                        $display_name = $faq['first_name'] && $faq['last_name'] 
                                            ? $faq['first_name'] . ' ' . $faq['last_name']
                                            : $faq['username'];
                                        echo htmlspecialchars($display_name);
                                        ?>
                                    </div>
                                    <small class="timestamp">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($faq['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-2 fw-semibold"><?php echo htmlspecialchars($faq['question']); ?></p>
                                
                                <?php if ($faq['answer']): ?>
                                    <div class="faq-answer mt-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <small class="text-success fw-semibold">
                                                <i class="fas fa-user-tie me-1"></i>
                                                Admin Response
                                            </small>
                                            <small class="timestamp">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($faq['answered_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-question-circle"></i>
                            <h5>No questions yet</h5>
                            <p>Be the first to ask a question!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for question textarea (only for logged-in users)
        <?php if ($user_id): ?>
        document.getElementById('question').addEventListener('input', function() {
            const charCount = this.value.length;
            document.getElementById('char-count').textContent = charCount;
            
            if (charCount > 450) {
                document.getElementById('char-count').style.color = '#dc3545';
            } else if (charCount > 400) {
                document.getElementById('char-count').style.color = '#ffc107';
            } else {
                document.getElementById('char-count').style.color = '#6c757d';
            }
        });
        <?php endif; ?>

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
