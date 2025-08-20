<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get supplier data for editing
if (isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        header("Location: manage_suppliers.php");
        exit;
    }
} else {
    header("Location: manage_suppliers.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $contact_info = $_POST['contact_info'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $notes = $_POST['notes'];

    try {
        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_info = ?, email = ?, phone = ?, address = ?, notes = ? WHERE id = ?");
        $stmt->execute([$name, $contact_info, $email, $phone, $address, $notes, $id]);

        logHistory($pdo, 'Edited Supplier', 'Supplier ID: ' . $id . ', Name: ' . $name . ', Contact: ' . $contact_info, $_SESSION['username']);

        $_SESSION['success'] = "Supplier updated successfully!";
        header("Location: manage_suppliers.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating supplier: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supplier - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body { font-family: 'Poppins', 'Arial', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-950 to-gray-900 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-400 to-pink-400">
                    <i class="fa fa-edit"></i> Edit Supplier
                </h1>
                <a href="manage_suppliers.php" class="rounded-lg bg-gradient-to-r from-gray-600 to-gray-700 text-white font-bold px-6 py-2 hover:from-gray-700 hover:to-gray-600 transition">
                    <i class="fa fa-arrow-left"></i> Back to Suppliers
                </a>
            </div>

            <!-- Edit Form -->
            <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-8 shadow-lg">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $supplier['id'] ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Supplier Name -->
                        <div class="md:col-span-2">
                            <label for="name" class="block text-cyan-100 font-bold mb-2">Supplier Name</label>
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($supplier['name']) ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                        </div>

                        <!-- Contact Info -->
                        <div class="md:col-span-2">
                            <label for="contact_info" class="block text-cyan-100 font-bold mb-2">Contact Info</label>
                            <input type="text" name="contact_info" id="contact_info" value="<?= htmlspecialchars($supplier['contact_info'] ?? '') ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
                        </div>

                        <!-- Email and Phone -->
                        <div>
                            <label for="email" class="block text-cyan-100 font-bold mb-2">Email</label>
                            <input type="email" name="email" id="email" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
                        </div>

                        <div>
                            <label for="phone" class="block text-cyan-100 font-bold mb-2">Phone</label>
                            <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
                        </div>

                        <!-- Address -->
                        <div class="md:col-span-2">
                            <label for="address" class="block text-cyan-100 font-bold mb-2">Address</label>
                            <textarea name="address" id="address" rows="3" 
                                      class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-cyan-100 font-bold mb-2">Notes</label>
                            <textarea name="notes" id="notes" rows="4" 
                                      class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400"><?= htmlspecialchars($supplier['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end gap-4 mt-8">
                        <a href="manage_suppliers.php" class="rounded-lg bg-gradient-to-r from-gray-600 to-gray-700 text-white font-bold px-6 py-2 hover:from-gray-700 hover:to-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white font-bold px-6 py-2 hover:from-blue-500 hover:to-cyan-500 transition">
                            <i class="fa fa-save"></i> Update Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 