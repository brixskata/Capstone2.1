<?php
session_start();
include 'includes/db.php';

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$products = [];
$categories = [];

try {
    // Fetch all categories for the sidebar
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's favorite products if logged in
    $user_favorites = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Check if a category is selected
    if (isset($_GET['category'])) {
        $selectedCategory = $_GET['category'];

        if ($selectedCategory === 'all') {
            // Fetch all non-archived products and their UOM
            $products = $pdo->query("
                SELECT p.*, uom.name AS uom_name 
                FROM products p
                LEFT JOIN units_of_measurement uom ON p.uom_id = uom.id
                WHERE p.is_archived = 0
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get the selected category's ID
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = :name");
            $stmt->execute(['name' => $selectedCategory]);
            $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($categoryData) {
                // Fetch products for selected category with UOM
                $stmt = $pdo->prepare("
                    SELECT p.*, uom.name AS uom_name 
                    FROM products p
                    LEFT JOIN units_of_measurement uom ON p.uom_id = uom.id
                    WHERE p.category_id = :category_id AND p.is_archived = 0
                ");
                $stmt->execute(['category_id' => $categoryData['id']]);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } else {
        // Fetch all non-archived products with UOM by default
        $products = $pdo->query("
            SELECT p.*, uom.name AS uom_name 
            FROM products p
            LEFT JOIN units_of_measurement uom ON p.uom_id = uom.id
            WHERE p.is_archived = 0
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Error fetching data: " . $e->getMessage();
}

// Fetch cart items for sliding cart display
$cart_items = [];
$cart_total = 0;
foreach ($_SESSION['cart'] ?? [] as $product_id => $cart_item) {
    $sql = "SELECT * FROM products WHERE id = :product_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $item_total = $product['price'] * $cart_item['quantity'];
        $cart_total += $item_total;
        $cart_items[] = [
            'product' => $product,
            'quantity' => $cart_item['quantity'],
            'total' => $item_total
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog - MikeMadz</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--bs-dark);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }

        /* Promo Banner */
        .promo-banner {
            background: var(--bs-secondary);
            color: white;
            padding: 0.75rem 0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Navigation */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--bs-secondary) !important;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--bs-dark) !important;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }

        .navbar-nav .nav-link:hover {
            color: var(--bs-secondary) !important;
        }

        /* Cart styles are now handled by the shared navbar */

        /* Category Section */
        .category-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .category-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .category-select {
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .category-select:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127,23,52,0.25);
        }

        /* Product Cards */
        .product-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--bs-secondary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 3;
        }

        .product-badge.in-stock {
            background: var(--bs-success);
        }

        .product-badge.low-stock {
            background: var(--bs-warning);
        }

        .product-badge.out-of-stock {
            background: #6c757d;
        }

        .swiper {
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--bs-secondary);
        }

        .product-desc {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin-bottom: 0.5rem;
        }

        .product-stock {
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .stock-available {
            color: var(--bs-success);
            font-weight: 600;
        }

        .stock-out {
            color: var(--bs-danger);
            font-weight: 600;
        }

        .btn-add-cart {
            background: var(--bs-secondary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
        }

        .btn-add-cart:hover {
            background: #6b1429;
            color: white;
        }

        .btn-favorite {
            background: white;
            color: var(--bs-secondary);
            border: 2px solid var(--bs-secondary);
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-favorite:hover {
            background: var(--bs-secondary);
            color: white;
        }

        .btn-favorite.favorited {
            background: var(--bs-danger) !important;
            color: white !important;
            border-color: var(--bs-danger) !important;
        }

        /* Footer */
        .footer {
            background: var(--bs-dark);
            color: white;
            padding: 3rem 0 1rem;
            margin-top: 4rem;
        }

        .footer-title {
            color: white;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-link {
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.3s ease;
            display: block;
            padding: 0.25rem 0;
        }

        .footer-link:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid #495057;
            padding-top: 1rem;
            margin-top: 2rem;
            text-align: center;
            color: #adb5bd;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sliding-cart {
                width: 100%;
                right: -100%;
            }

            .category-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .sliding-cart {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Promo Banner -->
    <div class="w-100 text-white text-center py-2 fw-bold d-flex flex-wrap justify-content-center align-items-center gap-3" style="background: var(--bs-secondary); font-size: 0.9rem;">
        <span>₱1,000 OFF on orders ₱10,000+</span>
        <span class="d-none d-md-inline">|</span>
        <span>Free Nationwide Delivery on ₱7,000+</span>
        <span class="d-none d-md-inline">|</span>
        <span>Sign up & get 10% OFF your first order</span>
    </div>

    <?php include 'includes/user_navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Category Section -->
        <div class="category-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <?php if (isset($_GET['category'])): ?>
                        <h1 class="category-title">Category: <?= htmlspecialchars(ucfirst($_GET['category'])) ?></h1>
                    <?php else: ?>
                        <h1 class="category-title">All Products</h1>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <form method="get">
                        <select name="category" class="form-select category-select" onchange="this.form.submit()">
                            <option value="all" <?= !isset($_GET['category']) || $_GET['category'] === 'all' ? 'selected' : '' ?>>All Products</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['name']) ?>" <?= isset($_GET['category']) && $_GET['category'] === $category['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($category['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="row g-4">
            <?php foreach ($products as $product): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="product-card">
                        <!-- Stock Badge -->
                        <?php if ($product['stock'] > 10): ?>
                            <span class="product-badge in-stock">In Stock</span>
                        <?php elseif ($product['stock'] > 0): ?>
                            <span class="product-badge low-stock">Low Stock</span>
                        <?php else: ?>
                            <span class="product-badge out-of-stock">Out of Stock</span>
                        <?php endif; ?>

                        <!-- Product Images Carousel -->
                        <div class="swiper mySwiper">
                            <div class="swiper-wrapper">
                                <?php if (!empty($product['image1'])): ?>
                                    <div class="swiper-slide">
                                        <img src="admin/<?= htmlspecialchars($product['image1']) ?>" class="w-100" style="height: 200px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($product['image2'])): ?>
                                    <div class="swiper-slide">
                                        <img src="admin/<?= htmlspecialchars($product['image2']) ?>" class="w-100" style="height: 200px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($product['image3'])): ?>
                                    <div class="swiper-slide">
                                        <img src="admin/<?= htmlspecialchars($product['image3']) ?>" class="w-100" style="height: 200px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>

                        <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>

                        <div class="product-price">
                            ₱<?= number_format($product['price'], 2) ?>
                            <span class="fs-6 text-muted"> / <?= htmlspecialchars($product['uom_name'] ?? '') ?></span>
                        </div>

                        <div class="product-stock">
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-available">
                                    Stock: <?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                </span>
                            <?php else: ?>
                                <span class="stock-out">Out of Stock</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($product['stock'] > 0): ?>
                            <form class="add-to-cart-form d-flex align-items-center gap-2" method="POST" action="cart.php">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>" class="form-control" style="width: 70px;">
                                <button type="submit" class="btn-add-cart">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                                <button type="button" class="btn-favorite <?= in_array($product['id'], $user_favorites) ? 'favorited' : '' ?>" data-product-id="<?= $product['id'] ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Out of Stock</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Footer -->
        <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
        // Initialize Swiper
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".mySwiper").forEach((el) => {
                new Swiper(el, {
                    loop: true,
                    autoplay: {
                        delay: 2000,
                        disableOnInteraction: false,
                    },
                    pagination: {
                        el: el.querySelector(".swiper-pagination"),
                        clickable: true,
                    },
                });
            });
        });

        // Cart functionality is now handled by the shared navbar

        // Add to cart functionality
        document.querySelectorAll('.add-to-cart-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const button = this.querySelector('.btn-add-cart');
                const originalText = button.innerHTML;
                
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart badge and show/hide based on quantity
                        const cartBadge = document.querySelector('.cart-badge');
                        if (cartBadge) {
                            cartBadge.textContent = data.cart_qty;
                            if (data.cart_qty > 0) {
                                cartBadge.style.display = 'flex';
                            } else {
                                cartBadge.style.display = 'none';
                            }
                        } else if (data.cart_qty > 0) {
                            // Create badge if it doesn't exist and there are items
                            const cartButton = document.querySelector('[onclick="toggleCart()"]');
                            if (cartButton) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'cart-badge';
                                newBadge.textContent = data.cart_qty;
                                cartButton.appendChild(newBadge);
                            }
                        }

                        // Refresh cart content
                        refreshCartContent();

                        // Show success message
                        showToast('Product added to cart!', 'success');
                        
                        // Reset button
                        button.disabled = false;
                        button.innerHTML = originalText;
                        
                        // Reset quantity to 1
                        const quantityInput = this.querySelector('input[name="quantity"]');
                        if (quantityInput) {
                            quantityInput.value = 1;
                        }
                    } else {
                        showToast(data.error || 'Failed to add product to cart', 'error');
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error. Please try again.', 'error');
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            });
        });

        // Function to refresh cart content
        function refreshCartContent() {
            fetch('cart_content.php')
                .then(response => response.text())
                .then(html => {
                    const cartBody = document.querySelector('.cart-body');
                    const cartFooter = document.querySelector('.cart-footer');
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update cart body
                    const newCartBody = doc.querySelector('.cart-body');
                    if (cartBody && newCartBody) {
                        cartBody.innerHTML = newCartBody.innerHTML;
                    }
                    
                    // Update cart footer
                    const newCartFooter = doc.querySelector('.cart-footer');
                    if (newCartFooter) {
                        if (cartFooter) {
                            cartFooter.innerHTML = newCartFooter.innerHTML;
                            cartFooter.style.display = 'block';
                        } else {
                            // Create footer if it doesn't exist
                            const slidingCart = document.querySelector('.sliding-cart');
                            if (slidingCart) {
                                slidingCart.appendChild(newCartFooter);
                            }
                        }
                    } else if (cartFooter) {
                        cartFooter.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error refreshing cart:', error);
                });
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
            toast.style.zIndex = '9999';
            
            const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast-header ${bgClass} text-white">
                    <i class="${icon} me-2"></i>
                    <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Favorite functionality
        document.querySelectorAll('.btn-favorite').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const isFavorited = this.classList.contains('favorited');
                
                fetch('favorite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + productId
                })
                .then(response => response.text())
                .then(data => {
                    let message, icon, headerClass;
                    
                    if (data === 'added') {
                        message = 'Added to favorites!';
                        icon = 'fas fa-heart text-danger';
                        headerClass = 'bg-success text-white';
                        
                        // Update button appearance to show it's favorited
                        this.classList.add('favorited');
                        this.style.background = 'var(--bs-danger)';
                        this.style.color = 'white';
                        this.style.borderColor = 'var(--bs-danger)';
                        
                    } else if (data === 'removed') {
                        message = 'Removed from favorites!';
                        icon = 'fas fa-heart-broken text-warning';
                        headerClass = 'bg-warning text-dark';
                        
                        // Update button appearance to show it's not favorited
                        this.classList.remove('favorited');
                        this.style.background = 'white';
                        this.style.color = 'var(--bs-secondary)';
                        this.style.borderColor = 'var(--bs-secondary)';
                        
                    } else if (data === 'not_logged_in') {
                        message = 'Please log in to add favorites';
                        icon = 'fas fa-exclamation-circle text-warning';
                        headerClass = 'bg-warning text-dark';
                        
                        // Redirect to login after showing message
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else {
                        message = 'Something went wrong. Please try again.';
                        icon = 'fas fa-exclamation-circle text-danger';
                        headerClass = 'bg-danger text-white';
                    }
                    
                    // Show message
                    const toast = document.createElement('div');
                    toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast-header ${headerClass}">
                            <i class="${icon} me-2"></i>
                            <strong class="me-auto">Favorites</strong>
                            <button type="button" class="btn-close ${headerClass.includes('text-white') ? 'btn-close-white' : ''}" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    `;
                    document.body.appendChild(toast);

                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    const toast = document.createElement('div');
                    toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast-header bg-danger text-white">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong class="me-auto">Error</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            Network error. Please try again.
                        </div>
                    `;
                    document.body.appendChild(toast);

                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                });
            });
        });

        // --- Cart Item Manipulation Functionality ---
        // This section adds functionality to update quantity and remove items from the cart

        function updateCartItem(productId, quantity) {
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&action=${quantity > 0 ? 'update' : 'remove'}&quantity=${quantity}`
            })
            .then(response => response.text())
            .then(() => {
                window.location.reload(); // Reload to reflect changes
            });
        }

        // Event listeners for quantity change (plus/minus buttons)
        document.querySelectorAll('.cart-quantity-update').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const currentQuantityInput = document.querySelector(`input[name="quantity"][data-product-id="${productId}"]`);
                let currentQuantity = parseInt(currentQuantityInput.value);

                if (this.classList.contains('fa-plus')) {
                    currentQuantity++;
                } else if (this.classList.contains('fa-minus')) {
                    currentQuantity = Math.max(0, currentQuantity - 1); // Ensure quantity doesn't go below 0
                }
                currentQuantityInput.value = currentQuantity;
                updateCartItem(productId, currentQuantity);
            });
        });

        // Event listeners for remove button
        document.querySelectorAll('.cart-item-remove').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                updateCartItem(productId, 0); // 0 quantity signifies removal
            });
        });
    </script>
</body>
</html>