<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db.php'; // Include the database connection

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Fetch user and role from user_type
    $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.password, ut.role
                           FROM users u
                           LEFT JOIN user_type ut ON ut.usertype_id = u.usertype_id
                           WHERE u.username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $stored = (string)($user['password'] ?? '');
        $isHash = preg_match('/^(\\$2[aby]\\$|\\$argon2)/', $stored) === 1;
        $passwordOk = $isHash ? password_verify($password, $stored) : hash_equals($stored, $password);

        if ($passwordOk) {
            // Check if user has admin or super_admin role
            $isAdmin = in_array($user['role'], ['admin', 'super_admin']);
            $isLegacyAdmin = in_array(strtolower($user['username']), ['admin', 'admin1'], true);
            
            if ($isAdmin || $isLegacyAdmin) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?: 'admin'; // Default to admin for legacy users
                header("Location: admin_dashboard2.php");
                exit;
            }
        }
    }

    // Invalid credentials or not an admin
    header("Location: login_admin.php?error=1");
    exit;
}
?>
