<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF']);

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Include DB connection
include_once 'db.php';

// Check ID verification status
$id_verified = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id_verified FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_verification = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_verified = $user_verification && $user_verification['id_verified'];
}

// Prepare cart items and total
$cart_items = [];
$cart_total = 0;
$selected_items_total = 0; // Initialize for selected items total

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $cart_item) {
        try {
            $sql = "SELECT 
                        p.product_id,
                        p.product_name,
                        COALESCE(ps.current_stock, 0) AS stock,
                        COALESCE(pp.selling_price, 0) AS price,
                        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
                    FROM products p
                    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                    WHERE p.product_id = :product_id AND p.is_archive = 0";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $quantity = $cart_item['quantity'] ?? 1;
                $item_total = ($product['price'] ?? 0) * $quantity;
                $cart_total += $item_total;

                // Check if the item is selected, default to false if not set
                $is_selected = $cart_item['selected'] ?? false;
                if ($is_selected) {
                    $selected_items_total += $item_total;
                }

                $cart_items[] = [
                    'product' => [
                        'id' => $product['product_id'] ?? 0,
                        'name' => $product['product_name'] ?? 'Unknown Product',
                        'price' => $product['price'] ?? 0,
                        'image1' => $product['image1'] ?? '',
                        'stock' => $product['stock'] ?? 0
                    ],
                    'quantity' => $quantity,
                    'total' => $item_total,
                    'selected' => $is_selected // Add selection status
                ];
            }
        } catch (Exception $e) {
            error_log("Error loading cart item for product $product_id: " . $e->getMessage());
            continue;
        }
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Cart Overlay -->
<div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>

<!-- Sliding Cart -->
<div class="sliding-cart" id="slidingCart">
    <div class="cart-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold" style="color: var(--bs-secondary);">Shopping Cart</h4>
        <button class="cart-close btn p-0" onclick="closeCart()">
            <i class="fas fa-times fs-4" style="color: var(--bs-secondary);"></i>
        </button>
    </div>
    <div class="cart-body" id="cartContent">
        <!-- Cart content will be loaded here dynamically -->
    </div>
    <div class="cart-footer" id="cartFooter" style="display: none;">
        <div class="cart-summary">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold">Subtotal:</span>
                <span class="fw-bold cart-subtotal">₱0.00</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-bold">Total:</span>
                <span class="fw-bold cart-total text-primary">₱0.00</span>
            </div>
        </div>
        <div class="d-grid gap-2">
            <button class="btn btn-outline-secondary" onclick="closeCart(); window.location.href='cart.php'">
                <i class="fas fa-shopping-cart me-2"></i>View Full Cart
            </button>
            <button class="btn btn-success" onclick="closeCart(); window.location.href='checkout.php'">
                <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
            </button>
        </div>
    </div>
</div>

<!--Navbar -->
<nav class="navbar navbar-light bg-white border-bottom sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold fs-3" style="color: var(--bs-secondary);" href="index.php">MikeMadz</a>

    <!-- Toggle Button for Mobile -->
    <button class="btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNav">
      <i class="fas fa-bars fs-4 text-dark"></i>
    </button>

    <!-- Desktop Icons -->
    <div class="d-none d-lg-flex align-items-center gap-4">
      <a class="text-dark <?php if ($current == 'favorite.php') echo 'fw-bold'; ?>" href="favorite.php">
        <i class="fas fa-heart fs-5"></i>
      </a>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a class="text-dark <?php if ($current == 'orders.php') echo 'fw-bold'; ?>" href="orders.php">
          <i class="fas fa-user fs-5"></i>
        </a>
      <?php else: ?>
        <a class="text-dark" href="login.php">
          <i class="fas fa-sign-in-alt fs-5"></i>
        </a>
      <?php endif; ?>
      <button class="btn p-0 text-dark position-relative" onclick="toggleCart()">
        <i class="fas fa-shopping-cart fs-5"></i>
        <?php if (!empty($_SESSION['cart'])): ?>
          <span class="cart-badge">
            <?php echo count($_SESSION['cart']); ?>
          </span>
        <?php endif; ?>
      </button>
    </div>
  </div>
</nav>

<!-- Offcanvas Menu (Mobile) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNav">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Menu</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column gap-4">
    <a class="text-dark <?php if ($current == 'favorites.php') echo 'fw-bold'; ?>" href="favorites.php">
      <i class="fas fa-heart me-2"></i> Favorites
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a class="text-dark <?php if ($current == 'orders.php') echo 'fw-bold'; ?>" href="orders.php">
        <i class="fas fa-user me-2"></i> Orders
      </a>
    <?php else: ?>
      <a class="text-dark" href="login.php">
        <i class="fas fa-sign-in-alt me-2"></i> Login
      </a>
    <?php endif; ?>
    <button class="btn p-0 text-start text-dark position-relative" onclick="toggleCart()">
      <i class="fas fa-shopping-cart me-2"></i> Cart
      <?php if (!empty($_SESSION['cart'])): ?>
        <span class="cart-badge ms-2">
          <?php echo count($_SESSION['cart']); ?>
        </span>
      <?php endif; ?>
    </button>
  </div>
</div>



<script>
  // Cart functionality
  function toggleCart() {
    const cart = document.getElementById('slidingCart');
    const overlay = document.getElementById('cartOverlay');

    cart.classList.toggle('active');
    overlay.classList.toggle('active');

    if (cart.classList.contains('active')) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'auto';
    }
  }

  function closeCart() {
    const cart = document.getElementById('slidingCart');
    const overlay = document.getElementById('cartOverlay');

    cart.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = 'auto';
  }

  // Close cart with Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeCart();
    }
  });

  // Function to update the cart badge count
  function updateCartBadge() {
      fetch('cart_count.php')
          .then(response => response.text())
          .then(count => {
              const cartBadge = document.querySelector('.cart-badge');
              if (cartBadge) {
                  cartBadge.textContent = count;
                  if (count == 0) {
                      cartBadge.style.display = 'none';
                  } else {
                      cartBadge.style.display = 'flex';
                  }
              }
          })
          .catch(error => {
              console.error('Error updating cart badge:', error);
          });
  }

  

  // Event delegation for cart interactions
  document.addEventListener('click', function(e) {
    if (e.target.closest('.increase-cart') || e.target.closest('.decrease-cart')) {
      e.preventDefault();
      const button = e.target.closest('.increase-cart') || e.target.closest('.decrease-cart');
      const productId = button.getAttribute('data-product-id');
      const action = button.classList.contains('increase-cart') ? 'add' : 'decrease';
      updateCartQuantity(productId, action);
    } else if (e.target.closest('.remove-cart-item')) {
      e.preventDefault();
      const button = e.target.closest('.remove-cart-item');
      const productId = button.getAttribute('data-product-id');
      removeCartItem(productId);
    }
  });

  function updateCartQuantity(productId, action) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('action', action);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

    fetch('cart.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Reload the entire cart content to reflect changes
        loadCartContent();
        updateCartBadge(); // Update badge count after any quantity change
        updateCartFooter(); // Update footer totals
      } else {
        console.error('Cart update failed:', data.error || 'Unknown error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
    });
  }

  function removeCartItem(productId) {
    if (!confirm('Are you sure you want to remove this item from your cart?')) {
      return;
    }

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('action', 'delete');
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

    fetch('cart.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Reload the entire cart content to reflect changes
        loadCartContent();
        updateCartBadge(); // Update badge count after removal
        updateCartFooter(); // Update footer totals
      } else {
        console.error('Cart removal failed:', data.error || 'Unknown error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
    });
  }

  // Close cart when clicking outside of it
  document.addEventListener('click', function(e) {
    const cart = document.getElementById('slidingCart');
    const cartBtn = e.target.closest('[onclick="toggleCart()"]');

    if (cart.classList.contains('active') && !cart.contains(e.target) && !cartBtn) {
      closeCart();
    }
  });

  // Function to load cart content from cart_content.php
  function loadCartContent() {
      fetch('cart_content.php')
          .then(response => response.text())
          .then(html => {
              document.getElementById('cartContent').innerHTML = html;
              updateCartBadge(); // Update badge after loading content
              updateCartFooter(); // Update footer with totals
          })
          .catch(error => {
              console.error('Error loading cart content:', error);
          });
  }

  // Function to update cart footer with totals
  function updateCartFooter() {
      fetch('cart_total.php')
          .then(response => response.json())
          .then(data => {
              const cartFooter = document.getElementById('cartFooter');
              const cartSubtotal = document.querySelector('.cart-subtotal');
              const cartTotal = document.querySelector('.cart-total');
              
              if (data.total > 0) {
                  cartFooter.style.display = 'block';
                  if (cartSubtotal) cartSubtotal.textContent = '₱' + data.total.toFixed(2);
                  if (cartTotal) cartTotal.textContent = '₱' + data.total.toFixed(2);
              } else {
                  cartFooter.style.display = 'none';
              }
          })
          .catch(error => {
              console.error('Error updating cart footer:', error);
          });
  }

  // Initial setup when the page loads
  document.addEventListener('DOMContentLoaded', () => {
      loadCartContent(); // Load cart content on page load
      updateCartBadge(); // Ensure badge is correct on load
  });
</script>

<!-- Bootstrap JS (include before </body>) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Styles -->
<style>
  :root {
    --bs-primary: #ffffff; /* Changed to white as per scheme, though often used for accents */
    --bs-secondary: #7F1734; /* Deep Red/Maroon */
    --bs-success: #198754; /* Green */
    --bs-danger: #db3030; /* Red */
    --bs-warning: #ffc107; /* Yellow */
    --bs-info: #016bf8; /* Blue */
    --bs-light: #f0f3f2; /* Off-white/Light Grey */
    --bs-dark: #001e2b; /* Very Dark Blue/Teal */
  }

  /* Navbar */
  .navbar-brand {
    color: var(--bs-secondary) !important;
  }
  .navbar-nav .nav-link.fw-bold, .navbar-nav .nav-link:hover {
    color: var(--bs-secondary) !important;
  }

  /* Cart Badge */
  .cart-badge {
    background: linear-gradient(135deg, var(--bs-secondary), var(--bs-danger));
    color: white;
    border-radius: 50%;
    padding: 2px 7px;
    font-size: 0.7rem;
    position: absolute;
    top: -5px;
    right: -5px;
    transform: translate(40%, -40%);
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Cart Overlay */
  .cart-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
  }

  .cart-overlay.active {
    opacity: 1;
    visibility: visible;
  }

  /* Sliding Cart */
  .sliding-cart {
    position: fixed;
    top: 0;
    right: -400px; /* Start off-screen */
    width: 400px;
    height: 100vh;
    background: white; /* Changed to white for contrast */
    z-index: 1050;
    transition: right 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    overflow-y: auto;
    box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
  }

  .sliding-cart.active {
    right: 0; /* Slide in */
  }

  .cart-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    background: var(--bs-light); /* Using light color for header */
    flex-shrink: 0;
  }

  .cart-header h4 {
      color: var(--bs-secondary); /* Secondary color for header title */
      font-weight: bold;
  }

  .cart-close {
    border: none;
    background: none;
    cursor: pointer;
    transition: transform 0.2s ease;
  }

  .cart-close:hover {
    transform: scale(1.1);
  }
  .cart-close i {
      color: var(--bs-secondary); /* Secondary color for close icon */
  }

  .cart-body {
    padding: 1rem;
    flex: 1;
    overflow-y: auto;
  }

  /* Cart Items */
  .empty-cart-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6c757d;
  }

  .empty-cart-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--bs-secondary); /* Secondary color for icon */
  }

  .cart-items {
    padding: 0.5rem 0;
  }

  .cart-item {
    display: flex;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #f0f0f0;
    gap: 0.75rem;
    transition: background-color 0.2s ease;
  }

  .cart-item:last-child {
    border-bottom: none;
  }

  .cart-item:hover {
      background-color: var(--bs-light); /* Light background on hover */
  }

  .item-selection {
      padding: 0 0.5rem;
  }

  .select-item {
      accent-color: var(--bs-secondary); /* Secondary color for checkbox */
      width: 18px;
      height: 18px;
      cursor: pointer;
  }

  .item-image img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e9ecef;
  }

  .item-details {
    flex: 1;
    min-width: 0;
  }

  .item-name {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    line-height: 1.3;
    color: #333;
  }

  .item-price {
    font-size: 0.8rem;
    color: #666;
    font-weight: bold;
  }

  .item-controls {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    min-width: 120px; /* Ensure enough space for controls */
  }

  .quantity-controls {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
  }

  .qty-btn {
    background: #f8f9fa;
    border: none;
    padding: 0.25rem 0.75rem;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    transition: background-color 0.2s;
    color: var(--bs-secondary); /* Secondary color for buttons */
  }

  .qty-btn:hover {
    background: #e9ecef;
  }

  .cart-item-quantity {
    padding: 0.25rem 0.75rem;
    font-size: 0.9rem;
    font-weight: 600;
    min-width: 30px;
    text-align: center;
    background: white;
    color: var(--bs-secondary); /* Secondary color for quantity */
  }

  .item-total-price {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--bs-secondary); /* Secondary color for total price */
  }

  .remove-btn {
    background: none;
    border: none;
    color: var(--bs-danger); /* Danger color for remove button */
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 3px;
    transition: background-color 0.2s;
    font-size: 0.9rem;
  }

  .remove-btn:hover {
    background: #f8d7da; /* Light red background on hover */
  }

  .cart-footer {
    margin-top: auto; /* Push footer to the bottom */
    padding: 1.5rem;
    border-top: 1px solid #e9ecef;
    background: var(--bs-light); /* Using light color for footer */
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .cart-footer .fw-bold {
      color: var(--bs-dark); /* Dark color for total labels */
  }
  .cart-footer .selected-total, .cart-footer .cart-total {
      color: var(--bs-secondary); /* Secondary color for total values */
  }

  /* Buttons in footer */
  .cart-footer .btn-primary {
      background-color: var(--bs-secondary);
      border-color: var(--bs-secondary);
  }
  .cart-footer .btn-primary:hover {
      background-color: #5a1022; /* Darker shade of secondary */
      border-color: #5a1022;
  }


  /* Mobile Responsive */
  @media (max-width: 768px) {
    .sliding-cart {
      width: 100%;
      right: -100%;
    }
    
    .cart-item-sliding {
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 1rem;
      padding: 1.5rem;
    }
    
    .item-image {
      margin-bottom: 0.5rem;
    }
    
    .cart-item-img {
      width: 80px;
      height: 80px;
    }
    
    .item-details {
      width: 100%;
      text-align: center;
    }
    
    .item-controls {
      width: 100%;
      flex-direction: row;
      justify-content: space-between;
      align-items: center;
      margin-top: 0.5rem;
    }
    
    .quantity-controls {
      order: 1;
    }
    
    .item-total {
      order: 2;
      font-size: 1rem;
      font-weight: 700;
    }
    
    .remove-cart-item {
      order: 3;
    }
    
    .cart-header {
      padding: 1rem;
    }
    
    .cart-header h4 {
      font-size: 1.25rem;
    }
    
    .cart-footer {
      padding: 1rem;
    }
  }

  /* New Cart Item Styles */
  .cart-item-sliding {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    background: white;
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
    gap: 0.75rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  }

  .cart-item-sliding:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

  .cart-item-sliding:last-child {
    border-bottom: none;
    margin-bottom: 0;
  }

  .cart-item-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 0.5rem;
    border: 2px solid #e9ecef;
    flex-shrink: 0;
  }

  .item-details {
    flex: 1;
    min-width: 0;
  }

  .item-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--bs-secondary);
    margin-bottom: 0.25rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .item-price {
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 0.25rem;
  }

  .item-stock {
    font-size: 0.75rem;
    color: #6c757d;
  }

  .item-controls {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    min-width: 100px;
  }

  .quantity-controls {
    display: flex;
    align-items: center;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    overflow: hidden;
    background: white;
  }

  .quantity-btn {
    background: #f8f9fa;
    border: none;
    padding: 0.375rem 0.5rem;
    cursor: pointer;
    font-size: 0.8rem;
    line-height: 1;
    transition: all 0.2s ease;
    color: var(--bs-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
  }

  .quantity-btn:hover:not(:disabled) {
    background: var(--bs-secondary);
    color: white;
  }

  .quantity-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .quantity-display {
    padding: 0.375rem 0.75rem;
    font-size: 0.9rem;
    font-weight: 600;
    min-width: 40px;
    text-align: center;
    background: white;
    color: var(--bs-dark);
    border-left: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
  }

  .item-total {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--bs-secondary);
    text-align: center;
  }

  .remove-cart-item {
    background: none;
    border: none;
    color: var(--bs-danger);
    cursor: pointer;
    padding: 0.375rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .remove-cart-item:hover {
    background: #f8d7da;
    color: #721c24;
  }

  .cart-items-container {
    padding: 0.5rem 0;
  }

  .cart-summary {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #e9ecef;
  }

  /* Animation for cart items */
  .cart-item-sliding {
    animation: slideInCart 0.3s ease-out;
  }

  @keyframes slideInCart {
    from {
      opacity: 0;
      transform: translateX(20px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }
</style>