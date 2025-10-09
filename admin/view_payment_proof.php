<?php
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

if (isset($_GET['file'])) {
    // Get the file name from the URL parameter
    $file = $_GET['file'];
    
    // Validate and sanitize the filename
    $safe_file = basename($file);

    // Set the file path
    $file_path = 'uploads/payment_proofs/' . $safe_file;


    // Check if the file exists and is a valid file
    if (file_exists($file_path) && is_file($file_path)) {
        // Set the appropriate headers for the image
        header('Content-Type: image/png'); // Adjust the content type based on your image format (e.g., image/jpeg for .jpg)
        header('Content-Disposition: inline; filename="' . $safe_file . '"');
        readfile($file_path);
        exit;
    } else {
        echo "Payment proof not found.";
    }
} else {
    echo "No file specified.";
}
?>
