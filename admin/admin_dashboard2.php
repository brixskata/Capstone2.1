<?php 
include 'db.php';
session_start();

// Ensure user is logged in and has admin access (Super Admin or Admin)
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit; 
}

try {
    // Basic statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archive = 0");
    $totalProducts = $stmt->fetchColumn();

    // Get total sales from delivered orders
    $stmt = $pdo->query("SELECT SUM(total_price) as total_sales FROM delivered_orders");
    $deliveredStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCompletedSales = $deliveredStats['total_sales'] ?: 0;

    // Get total pending sales (Pending/To Ship/Shipped)
    $stmt = $pdo->query("SELECT SUM(o.total_price)
        FROM orders o
        JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
        WHERE os.status_name IN ('Pending','To Ship','Shipped')");
    $totalPendingSales = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();

    // Sum current stock from product_stock for active products
    $stmt = $pdo->query("SELECT CO");
    $statusCounts = array_fill_keys(['Pending', 'To Ship', 'Shipped', 'Delivered'], 0);
    $statusAmounts = array_fill_keys(['Pending', 'To Ship', 'Shipped', 'Delivered'], 0);

    $stmt = $pdo->query("SELECT os.status_name, COUNT(*) AS order_count, SUM(o.total_price) AS total_amount
                        FROM orders o
                        JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                        GROUP BY os.status_name");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($statusCounts[$row['status_name']])) {
            $statusCounts[$row['status_name']] = (int)$row['order_count'];
            $statusAmounts[$row['status_name']] = (float)$row['total_amount'];
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Navigation Menu Styles -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 100vh;
        }
        /* Custom scrollbar for demonstration */
        .scrollbar-hidden::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hidden {
            -ms-overflow-style: none; /* IE and Edge */
            scrollbar-width: none; /* Firefox */
        }
        /* Styles for the smooth reveal effect */
        .logout-btn-wrapper {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
            opacity: 0;
        }
        .logout-btn-wrapper.is-visible {
            max-height: 100px; /* A value large enough to contain the button */
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
    <div class="flex-grow p-4">
        <!-- Your existing dashboard content -->
        <h1 class="text-2xl font-bold text-gray-800 mt-10">Admin Dashboard</h1>
        <div class="container mx-auto mt-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700">Total Products</h2>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalProducts; ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700">Total Completed Sales</h2>
                        <p class="text-3xl font-bold text-green-500 mt-1">₱<?php echo number_format($totalCompletedSales, 2); ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700">Total Pending Sales</h2>
                        <p class="text-3xl font-bold text-yellow-500 mt-1">₱<?php echo number_format($totalPendingSales, 2); ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700">Total Users</h2>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalUsers; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Order Status Breakdown</h2>
                <canvas id="orderStatusChart"></canvas>
            </div>
        </div>
        <div id="message-box" class="mt-6 p-4 rounded-lg shadow-md max-w-sm mx-auto hidden">
            <p id="message-text" class="text-sm font-medium"></p>
        </div>
    </div>
    
    <!-- Interactive Navigation Menu -->
    <nav id="nav-menu" class="fixed bottom-0 left-0 w-full bg-white shadow-lg border-t-2 border-gray-200 p-2 overflow-x-auto scrollbar-hidden">
        <div class="flex flex-nowrap items-center justify-between space-x-4">
            <!-- Home Button -->
            <button class="flex flex-col items-center justify-center p-2 rounded-lg text-blue-600 hover:bg-gray-200 transition-colors flex-shrink-0 min-w-[70px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-10v10a1 1 0 001 1h3M3 12l2-2m0 0l7-7 7 7m-7 7h-2.5a2.5 2.5 0 01-2.5-2.5V9.5a2.5 2.5 0 012.5-2.5h2.5a2.5 2.5 0 012.5 2.5v2.5a2.5 2.5 0 01-2.5 2.5z" />
                </svg>
                <span class="mt-1 text-xs font-medium">Home</span>
            </button>

            <!-- Menu Button -->
            <button class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-gray-200 transition-colors flex-shrink-0 min-w-[70px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span class="mt-1 text-xs font-medium">Menu</span>
            </button>

            <!-- Order Button -->
            <button class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-gray-200 transition-colors flex-shrink-0 min-w-[70px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span class="mt-1 text-xs font-medium">Order</span>
            </button>

            <!-- Location Button -->
            <button class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-gray-200 transition-colors flex-shrink-0 min-w-[70px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="mt-1 text-xs font-medium">Location</span>
            </button>

            <!-- About Button -->
            <button class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-gray-200 transition-colors flex-shrink-0 min-w-[70px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="mt-1 text-xs font-medium">About</span>
            </button>

            <!-- Maintenance/Log Out Group -->
            <div class="flex flex-col items-center flex-shrink-0 space-y-2">
                <!-- Maintenance Button -->
                <button id="maintenance-btn" class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-gray-200 transition-colors min-w-[70px]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <span class="mt-1 text-xs font-medium">Maintenance</span>
                </button>
                
                <!-- Log Out Button - Wrapper for smooth transition -->
                <div class="logout-btn-wrapper">
                    <button id="logout-btn" class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500 hover:text-red-500 hover:bg-gray-200 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="mt-1 text-xs font-medium">Log Out</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Navigation Menu Script -->
    <script>
        const maintenanceBtn = document.getElementById('maintenance-btn');
        const logoutBtnWrapper = document.querySelector('.logout-btn-wrapper');
        const messageBox = document.getElementById('message-box');
        const messageText = document.getElementById('message-text');

        // Function to show a temporary message
        function showMessage(text, isError = false) {
            messageText.textContent = text;
            messageBox.style.display = 'block';
            if (isError) {
                messageBox.classList.remove('bg-gray-200', 'text-gray-800');
                messageBox.classList.add('bg-red-100', 'text-red-700');
            } else {
                messageBox.classList.remove('bg-red-100', 'text-gray-800');
                messageBox.classList.add('bg-gray-200', 'text-gray-800');
            }
            setTimeout(() => {
                messageBox.style.display = 'none';
            }, 3000); // Hide after 3 seconds
        }

        // Event listener for the maintenance button
        maintenanceBtn.addEventListener('click', () => {
            // Toggle the visibility of the log out button with the new wrapper
            logoutBtnWrapper.classList.toggle('is-visible');
            if (logoutBtnWrapper.classList.contains('is-visible')) {
                showMessage("Maintenance options are now visible.");
            } else {
                showMessage("Maintenance options are now hidden.");
            }
        });

        // Event listener for the log out button
        const logoutBtn = document.getElementById('logout-btn');
        logoutBtn.addEventListener('click', () => {
            showMessage("You have been logged out.", true);
            // In a real application, you would add your logout logic here
            // e.g., redirecting to a login page, clearing session data, etc.
        });
        
        // Add event listeners for other buttons to show they are functional
        document.querySelectorAll('.flex-col:not(#maintenance-btn):not(.logout-btn-wrapper button)').forEach(button => {
            button.addEventListener('click', (event) => {
                const buttonText = event.currentTarget.querySelector('span').textContent;
                showMessage(`Navigating to the ${buttonText} page.`);
            });
        });

    </script>
    <script>
        // Your existing Chart.js script
        const ctx = document.getElementById('orderStatusChart').getContext('2d');
        const orderStatusChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Pending', 'To Ship', 'Shipped', 'Delivered'],
                datasets: [{
                    label: 'Number of Orders',
                    data: [<?php echo implode(', ', $statusCounts); ?>],
                    backgroundColor: [
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 159, 64, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const amounts = [<?php echo implode(', ', $statusAmounts); ?>];
                                if (amounts[context.dataIndex] > 0) {
                                    return 'Total Value: ₱' + new Intl.NumberFormat('en-PH').format(amounts[context.dataIndex]);
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12
                            },
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            padding: 10
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
      </script>
</body>
</html>