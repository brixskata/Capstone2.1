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

// Include DB connection (robust path)
include_once __DIR__ . '/db.php';
// Make global PDO available when this file is included inside a function scope
global $pdo;

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
    foreach ($_SESSION['cart'] as $nav_cart_key => $nav_cart_item) {
        try {
            // Extract product_id from cart key (format: product_id_unit_brand_X_batch_Y)
            $nav_product_id = intval(explode('_', $nav_cart_key)[0]);
            
            $sql = "SELECT 
                        p.product_id,
                        p.product_name,
                        COALESCE((
                            SELECT ps.current_stock
                            FROM product_stock ps
                            WHERE ps.product_id = p.product_id
                            ORDER BY ps.last_restock_date DESC, ps.productstock_id DESC
                            LIMIT 1
                        ), 0) AS stock,
                        COALESCE((
                            SELECT (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price
                            FROM product_batches pb
                            LEFT JOIN product_pricing pp ON pb.product_id = pp.product_id
                            WHERE pb.product_id = p.product_id 
                            AND pb.quantity_remaining > 0 
                            AND pb.is_active = 1
                            ORDER BY pb.expiration_date ASC
                            LIMIT 1
                        ), 0) AS price,
                        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
                    FROM products p
                    WHERE p.product_id = :product_id AND p.is_archive = 0";
            $nav_stmt = $pdo->prepare($sql);
            $nav_stmt->bindParam(':product_id', $nav_product_id);
            $nav_stmt->execute();
            $navProduct = $nav_stmt->fetch(PDO::FETCH_ASSOC);

            if ($navProduct) {
                $nav_quantity = $nav_cart_item['quantity'] ?? 1;
                $nav_item_total = ($navProduct['price'] ?? 0) * $nav_quantity;
                $cart_total += $nav_item_total;

                // Check if the item is selected, default to false if not set
                $is_selected = $nav_cart_item['selected'] ?? false;
                if ($is_selected) {
                    $selected_items_total += $nav_item_total;
                }

                $cart_items[] = [
                    'product' => [
                        'id' => $navProduct['product_id'] ?? 0,
                        'name' => $navProduct['product_name'] ?? 'Unknown Product',
                        'price' => $navProduct['price'] ?? 0,
                        'image1' => $navProduct['image1'] ?? '',
                        'stock' => $navProduct['stock'] ?? 0
                    ],
                    'quantity' => $nav_quantity,
                    'total' => $nav_item_total,
                    'selected' => $is_selected // Add selection status
                ];
            }
        } catch (Exception $e) {
            error_log("Error loading cart item for product $nav_product_id: " . $e->getMessage());
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
            <button class="btn btn-success" onclick="proceedToCheckoutFromSlidingCart()">
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <a class="text-dark <?php if ($current == 'favorites.php') echo 'fw-bold'; ?>" href="favorites.php">
          <i class="fas fa-heart fs-5"></i>
        </a>
      <?php endif; ?>
      
      <!-- Profile Dropdown -->
      <div class="dropdown">
        <?php if (isset($_SESSION['user_id'])): ?>
          <button class="btn btn-link text-dark p-0 profile-dropdown-btn" type="button" id="profileDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
            <i class="fas fa-user fs-5"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="profileDropdown">
            <li><a class="dropdown-item" href="orders.php">
              <i class="fas fa-user"></i>Profile
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item dark-mode-toggle" type="button">
              <i class="fas fa-moon theme-icon"></i>Dark Mode
            </button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item logout-item" type="button" onclick="confirmLogout()">
              <i class="fas fa-sign-out-alt"></i>Logout
            </button></li>
          </ul>
        <?php else: ?>
          <button class="btn btn-link text-dark p-0 profile-dropdown-btn" type="button" id="guestDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
            <i class="fas fa-user fs-5"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="guestDropdown">
            <li><a class="dropdown-item" href="login.php">
              <i class="fas fa-sign-in-alt me-2"></i>Login
            </a></li>
            <li><a class="dropdown-item" href="register.php">
              <i class="fas fa-user-plus me-2"></i>Register
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item dark-mode-toggle" type="button">
              <i class="fas fa-moon theme-icon"></i>Dark Mode
            </button></li>
          </ul>
        <?php endif; ?>
      </div>
      <?php if ($current == 'cart.php'): ?>
        <a class="btn p-0 text-dark position-relative" href="cart.php" title="View Full Cart">
          <i class="fas fa-shopping-cart fs-5"></i>
          <?php if (!empty($_SESSION['cart'])): ?>
            <span class="cart-badge">
              <?php echo count($_SESSION['cart']); ?>
            </span>
          <?php endif; ?>
        </a>
      <?php elseif ($current == 'checkout.php'): ?>
        <a class="btn p-0 text-dark position-relative" href="checkout.php" title="View Checkout">
          <i class="fas fa-shopping-cart fs-5"></i>
          <?php if (!empty($_SESSION['cart'])): ?>
            <span class="cart-badge">
              <?php echo count($_SESSION['cart']); ?>
            </span>
          <?php endif; ?>
        </a>
      <?php else: ?>
        <button class="btn p-0 text-dark position-relative" onclick="toggleCart()" title="Open Cart">
          <i class="fas fa-shopping-cart fs-5"></i>
          <?php if (!empty($_SESSION['cart'])): ?>
            <span class="cart-badge">
              <?php echo count($_SESSION['cart']); ?>
            </span>
          <?php endif; ?>
        </button>
      <?php endif; ?>
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
    <?php if (isset($_SESSION['user_id'])): ?>
      <a class="text-dark <?php if ($current == 'favorites.php') echo 'fw-bold'; ?>" href="favorites.php">
        <i class="fas fa-heart me-2"></i> Favorites
      </a>
      <a class="text-dark <?php if ($current == 'orders.php') echo 'fw-bold'; ?>" href="orders.php">
        <i class="fas fa-user me-2"></i> Profile
      </a>
    <?php else: ?>
      <a class="text-dark" href="login.php">
        <i class="fas fa-sign-in-alt me-2"></i> Login
      </a>
      <a class="text-dark" href="register.php">
        <i class="fas fa-user-plus me-2"></i> Register
      </a>
    <?php endif; ?>
    
    <hr class="my-2">
    
    <button class="btn btn-link text-dark text-start p-0 dark-mode-toggle">
      <i class="fas fa-moon theme-icon me-2"></i>Dark Mode
    </button>
    <?php if ($current == 'cart.php'): ?>
      <a class="text-dark text-start position-relative" href="cart.php">
        <i class="fas fa-shopping-cart me-2"></i> View Cart
        <?php if (!empty($_SESSION['cart'])): ?>
          <span class="cart-badge ms-2">
            <?php echo count($_SESSION['cart']); ?>
          </span>
        <?php endif; ?>
      </a>
    <?php elseif ($current == 'checkout.php'): ?>
      <a class="text-dark text-start position-relative" href="checkout.php">
        <i class="fas fa-shopping-cart me-2"></i> View Checkout
        <?php if (!empty($_SESSION['cart'])): ?>
          <span class="cart-badge ms-2">
            <?php echo count($_SESSION['cart']); ?>
          </span>
        <?php endif; ?>
      </a>
    <?php else: ?>
      <button class="btn p-0 text-start text-dark position-relative" onclick="toggleCart()">
        <i class="fas fa-shopping-cart me-2"></i> Cart
        <?php if (!empty($_SESSION['cart'])): ?>
          <span class="cart-badge ms-2">
            <?php echo count($_SESSION['cart']); ?>
          </span>
        <?php endif; ?>
      </button>
    <?php endif; ?>
  </div>
</div>



<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* SweetAlert2 Custom Styles */
.swal2-popup {
    border-radius: 1rem !important;
    font-family: 'Inter', sans-serif !important;
}

.swal2-title {
    font-weight: 600 !important;
}

.swal2-confirm {
    border: none !important;
    border-radius: 0.5rem !important;
    font-weight: 600 !important;
    order: 1 !important; /* Left side */
}

.swal2-cancel {
    border: none !important;
    border-radius: 0.5rem !important;
    font-weight: 600 !important;
    order: 2 !important; /* Right side */
}

.swal2-success .swal2-confirm {
    background: #198754 !important; /* Green for success */
}

.swal2-warning .swal2-confirm {
    background: #ffc107 !important; /* Yellow for warnings */
    color: var(--text-primary) !important;
}

.swal2-danger .swal2-confirm {
    background: #dc3545 !important; /* Red for delete/danger */
}

.swal2-info .swal2-confirm {
    background: #0dcaf0 !important; /* Blue for info */
}

.swal2-cancel {
    background: #6c757d !important; /* Gray for cancel */
}

.swal2-actions {
    justify-content: space-between !important;
    gap: 1rem !important;
}
</style>

<script>
  // Cart functionality
  function toggleCart() {
    // Check if we're on the cart page or checkout page
    if (window.location.pathname.includes('cart.php')) {
      console.log('🚫 Sliding cart disabled on cart page - redirecting to cart.php');
      // Instead of opening sliding cart, redirect to cart page
      window.location.href = 'cart.php';
      return;
    }
    
    if (window.location.pathname.includes('checkout.php')) {
      console.log('🚫 Sliding cart disabled on checkout page - redirecting to checkout.php');
      // Instead of opening sliding cart, redirect to checkout page
      window.location.href = 'checkout.php';
      return;
    }
    
    console.log('🛒 Opening sliding cart...');
    const cart = document.getElementById('slidingCart');
    const overlay = document.getElementById('cartOverlay');

    cart.classList.toggle('active');
    overlay.classList.toggle('active');

    if (cart.classList.contains('active')) {
      document.body.style.overflow = 'hidden';
      // Load cart content only when opening
      loadCartContent();
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
      const cartKey = button.getAttribute('data-cart-key');
      const action = button.classList.contains('increase-cart') ? 'add' : 'decrease';
      updateCartQuantity(productId, action, cartKey);
    } else if (e.target.closest('.remove-cart-item')) {
      e.preventDefault();
      const button = e.target.closest('.remove-cart-item');
      const productId = button.getAttribute('data-product-id');
      const cartKey = button.getAttribute('data-cart-key');
      const cartItem = button.closest('.cart-item-sliding');
      const productName = cartItem ? cartItem.querySelector('.item-name')?.textContent || 'this item' : 'this item';
      
      // Show SweetAlert2 confirmation (Success Style)
      Swal.fire({
        title: 'Confirm Removal',
        text: `Are you sure you want to remove ${productName} from your cart?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check me-1"></i>Confirm',
        cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
        customClass: {
          popup: 'swal2-success',
          confirmButton: 'swal2-confirm',
          cancelButton: 'swal2-cancel'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          Swal.fire({
            title: 'Removing...',
            text: 'Please wait while we remove this item.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            },
            customClass: {
              popup: 'swal2-info'
            }
          });
          
          // Make the removal request
          const formData = new FormData();
          formData.append('product_id', productId);
          formData.append('action', 'delete');
          if (cartKey) {
            formData.append('cart_key', cartKey);
          }
          formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

          fetch('cart.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Show success message (Success Style)
              Swal.fire({
                title: 'Confirmed!',
                text: 'Item removed successfully.',
                icon: 'success',
                confirmButtonColor: '#198754',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Great!',
                customClass: {
                  popup: 'swal2-success',
                  confirmButton: 'swal2-confirm'
                }
              });
              
              // Update cart badge and footer only
              updateCartBadge();
              updateCartFooter();
            } else {
              // Show error message
              Swal.fire({
                title: 'Error',
                text: data.error || 'Failed to remove item from cart.',
                icon: 'error',
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="fas fa-times me-1"></i>OK',
                customClass: {
                  popup: 'swal2-danger',
                  confirmButton: 'swal2-confirm'
                }
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              title: 'Connection Error',
              html: `
                <div class="text-center">
                  <i class="fas fa-wifi text-danger mb-3" style="font-size: 3rem;"></i>
                  <p>Unable to remove item from cart.</p>
                  <p class="text-muted">Please check your internet connection and try again.</p>
                </div>
              `,
              showCancelButton: true,
              confirmButtonColor: '#dc3545',
              cancelButtonColor: '#6c757d',
              confirmButtonText: '<i class="fas fa-redo me-1"></i>Try Again',
              cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
              customClass: {
                popup: 'swal2-danger',
                confirmButton: 'swal2-confirm',
                cancelButton: 'swal2-cancel'
              }
            }).then((retryResult) => {
              if (retryResult.isConfirmed) {
                // Retry the removal
                button.click();
              }
            });
          });
        }
      });
    }
  });

  // Handle direct quantity input changes (minimum 1)
  document.addEventListener('change', function(e) {
    const input = e.target.closest('.quantity-input');
    if (!input) return;
    const productId = input.getAttribute('data-product-id');
    const cartKey = input.getAttribute('data-cart-key');
    let value = parseFloat(input.value);
    const min = 1; // Minimum quantity is 1
    const max = input.getAttribute('max') ? parseFloat(input.getAttribute('max')) : Number.POSITIVE_INFINITY;
    if (isNaN(value)) value = min;
    value = Math.max(min, Math.min(max, value));
    value = Math.round(value * 10) / 10;
    input.value = value.toFixed(1);
    updateCartQuantityExact(productId, value, cartKey);
  });

  function updateCartQuantity(productId, action, cartKey = null) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('action', action);
    if (cartKey) {
      formData.append('cart_key', cartKey);
    }
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

    fetch('cart.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Check if we're on the cart page
        if (window.location.pathname.includes('cart.php')) {
          // Preserve selection state before reload
          const selectedItems = [];
          const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
          checkedBoxes.forEach(checkbox => {
            selectedItems.push(checkbox.dataset.cartKey);
          });
          
          // Store selection state in sessionStorage
          sessionStorage.setItem('cart_selection_state', JSON.stringify(selectedItems));
          
          // Refresh the page to show updated cart content
          location.reload();
        } else {
          // Update cart badge and footer for other pages
          updateCartBadge(); // Update badge count after any quantity change
          updateCartFooter(); // Update footer totals
          
          // Also refresh sliding cart content if it's open
          const slidingCart = document.getElementById('slidingCart');
          if (slidingCart && slidingCart.classList.contains('active')) {
            loadCartContent();
          }
        }
      } else {
        console.error('Cart update failed:', data.error || 'Unknown error');
        // Show error message if on cart page
        if (window.location.pathname.includes('cart.php')) {
          Swal.fire({
            title: 'Error',
            text: data.message || 'Failed to update cart',
            icon: 'error',
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'OK'
          });
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      // Show error message if on cart page
      if (window.location.pathname.includes('cart.php')) {
        Swal.fire({
          title: 'Connection Error',
          text: 'Unable to update cart. Please check your connection and try again.',
          icon: 'error',
          confirmButtonColor: '#dc3545',
          confirmButtonText: 'OK'
        });
      }
    });
  }

  // Send exact decimal quantity update
  function updateCartQuantityExact(productId, quantity, cartKey = null) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('action', 'set_quantity');
    formData.append('quantity', quantity);
    if (cartKey) {
      formData.append('cart_key', cartKey);
    }
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

    fetch('cart.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Check if we're on the cart page
        if (window.location.pathname.includes('cart.php')) {
          // Preserve selection state before reload
          const selectedItems = [];
          const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
          checkedBoxes.forEach(checkbox => {
            selectedItems.push(checkbox.dataset.cartKey);
          });
          
          // Store selection state in sessionStorage
          sessionStorage.setItem('cart_selection_state', JSON.stringify(selectedItems));
          
          // Refresh the page to show updated cart content
          location.reload();
        } else {
          // Update cart badge and footer for other pages
          updateCartBadge();
          updateCartFooter();
          
          // Also refresh sliding cart content if it's open
          const slidingCart = document.getElementById('slidingCart');
          if (slidingCart && slidingCart.classList.contains('active')) {
            loadCartContent();
          }
        }
      } else {
        console.error('Cart update failed:', data.error || data.message || 'Unknown error');
        // Show error message if on cart page
        if (window.location.pathname.includes('cart.php')) {
          Swal.fire({
            title: 'Error',
            text: data.message || 'Failed to update cart',
            icon: 'error',
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'OK'
          });
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      // Show error message if on cart page
      if (window.location.pathname.includes('cart.php')) {
        Swal.fire({
          title: 'Connection Error',
          text: 'Unable to update cart. Please check your connection and try again.',
          icon: 'error',
          confirmButtonColor: '#dc3545',
          confirmButtonText: 'OK'
        });
      }
    });
  }

  function removeCartItem(productId, cartKey = null) {
    // This function is now handled by the modal in cart_content.php
    // The modal will show confirmation and handle the removal
    console.log('Remove cart item function called - handled by modal');
  }

  // Close cart when clicking outside of it
  document.addEventListener('click', function(e) {
    const cart = document.getElementById('slidingCart');
    const cartBtn = e.target.closest('[onclick="toggleCart()"]');

    if (cart.classList.contains('active') && !cart.contains(e.target) && !cartBtn) {
      closeCart();
    }
  });

  // Debounce mechanism for cart content loading
  let cartContentTimeout;
  
  // Function to load cart content from cart_content.php
  function loadCartContent() {
      // Clear any pending load
      if (cartContentTimeout) {
          clearTimeout(cartContentTimeout);
      }
      
      // Debounce the loading
      cartContentTimeout = setTimeout(() => {
          console.log('📡 Loading cart content from cart_content.php...');
          fetch('cart_content.php')
              .then(response => response.text())
              .then(html => {
                  console.log('📄 Cart content HTML received, length:', html.length);
                  
                  // Insert HTML content
                  document.getElementById('cartContent').innerHTML = html;
                  
                  // Extract and execute JavaScript from the loaded content
                  const scriptTags = document.querySelectorAll('#cartContent script');
                  console.log('🔧 Found', scriptTags.length, 'script tags in cart content');
                  
                  // Execute scripts after a small delay to ensure DOM is ready
                  setTimeout(() => {
                      scriptTags.forEach((script, index) => {
                          console.log(`📜 Executing script ${index + 1}...`);
                          try {
                              // Create a new script element and execute it
                              const newScript = document.createElement('script');
                              newScript.textContent = script.textContent;
                              document.head.appendChild(newScript);
                              document.head.removeChild(newScript);
                              console.log(`✅ Script ${index + 1} executed successfully`);
                          } catch (error) {
                              console.error(`❌ Error executing script ${index + 1}:`, error);
                          }
                      });
                  }, 100); // Small delay to ensure DOM is ready
                  
                  // Only update footer - badge is updated separately
                  // Always update footer on non-cart pages, or on cart page without selection functionality
                  updateCartFooter();
              })
              .catch(error => {
                  console.error('❌ Error loading cart content:', error);
              });
      }, 100); // 100ms debounce
  }

  // Function to proceed to checkout from sliding cart with selected items
  function proceedToCheckoutFromSlidingCart() {
    console.log('🛒 Proceeding to checkout from sliding cart...');
    
    // Get selected cart items
    const selectedItems = [];
    const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
    
    console.log('🔍 Found', checkedBoxes.length, 'selected items');
    
    checkedBoxes.forEach(checkbox => {
      const cartKey = checkbox.dataset.cartKey;
      if (cartKey) {
        selectedItems.push(cartKey);
        console.log('✅ Selected item:', cartKey);
      }
    });
    
    if (selectedItems.length === 0) {
      // No items selected, show warning
      Swal.fire({
        title: 'No Items Selected',
        text: 'Please select at least one item to checkout.',
        icon: 'warning',
        confirmButtonColor: '#7F1734',
        confirmButtonText: 'OK'
      });
      return;
    }
    
    console.log('📋 Proceeding with', selectedItems.length, 'selected items:', selectedItems);
    
    // Close the sliding cart
    closeCart();
    
    // Create a form to submit selected items to checkout
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'checkout.php';
    form.style.display = 'none';
    
    const selectedItemsInput = document.createElement('input');
    selectedItemsInput.type = 'hidden';
    selectedItemsInput.name = 'selected_items';
    selectedItemsInput.value = JSON.stringify(selectedItems);
    
    form.appendChild(selectedItemsInput);
    document.body.appendChild(form);
    
    // Submit the form
    form.submit();
  }

  // Function to update cart footer with totals
  function updateCartFooter() {
      console.log('🔄 updateCartFooter() called from user_navbar.php');
      console.log('📍 Current page:', window.location.pathname);
      
      // Check if we're on the cart page or checkout page with selection functionality
      if (window.location.pathname.includes('cart.php') || window.location.pathname.includes('checkout.php')) {
          console.log('⏭️ Skipping footer update (on cart/checkout page)');
          // Don't override selection-based totals on cart/checkout page
          return;
      }
      
      console.log('📡 Fetching cart total from server...');
      fetch('cart_total.php')
          .then(response => response.json())
          .then(data => {
              console.log('📊 Server response:', data);
              const cartFooter = document.getElementById('cartFooter');
              const cartSubtotal = document.querySelector('.cart-subtotal');
              const cartTotal = document.querySelector('.cart-total');
              
              console.log('🔍 Footer elements found:');
              console.log('  - #cartFooter:', cartFooter ? 'YES' : 'NO');
              console.log('  - .cart-subtotal:', cartSubtotal ? 'YES' : 'NO');
              console.log('  - .cart-total:', cartTotal ? 'YES' : 'NO');
              
              if (data.total > 0) {
                  cartFooter.style.display = 'block';
                  if (cartSubtotal) {
                      cartSubtotal.textContent = '₱' + data.total.toFixed(2);
                      console.log('✅ Updated .cart-subtotal:', cartSubtotal.textContent);
                  }
                  if (cartTotal) {
                      cartTotal.textContent = '₱' + data.total.toFixed(2);
                      console.log('✅ Updated .cart-total:', cartTotal.textContent);
                  }
              } else {
                  cartFooter.style.display = 'none';
                  console.log('🙈 Footer hidden (server total = 0)');
              }
          })
          .catch(error => {
              console.error('❌ Error updating cart footer:', error);
          });
  }

  // Initial setup when the page loads
  document.addEventListener('DOMContentLoaded', () => {
      // Only update badge on load - content loads when cart is opened
      updateCartBadge();
  });
</script>

<!-- Bootstrap JS (include before </body>) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Dark Mode JavaScript -->
<script src="assets/js/dark-mode.js"></script>

<!-- Initialize Dropdowns -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing dropdowns...');
    
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
        return;
    }
    
    // Find all dropdown toggles
    var dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    console.log('Found dropdown toggles:', dropdownToggles.length);
    
    // Initialize each dropdown
    dropdownToggles.forEach(function(toggle, index) {
        console.log('Initializing dropdown', index, toggle);
        
        try {
            var dropdown = new bootstrap.Dropdown(toggle);
            console.log('Dropdown initialized successfully:', dropdown);
            
            // Add click event listener
            toggle.addEventListener('click', function(e) {
                console.log('Dropdown clicked:', e.target);
            });
            
            // Add show/hide event listeners
            toggle.addEventListener('show.bs.dropdown', function(e) {
                console.log('Dropdown showing:', e.target);
            });
            
            toggle.addEventListener('shown.bs.dropdown', function(e) {
                console.log('Dropdown shown:', e.target);
            });
            
        } catch (error) {
            console.error('Error initializing dropdown:', error);
        }
    });
    
    // Test manual dropdown toggle
    setTimeout(function() {
        var testToggle = document.getElementById('profileDropdown') || document.getElementById('guestDropdown');
        if (testToggle) {
            console.log('Testing dropdown toggle:', testToggle);
            
            // Add a simple click test
            testToggle.addEventListener('click', function(e) {
                console.log('Button clicked!', e);
                
                // Try Bootstrap dropdown first
                var dropdown = bootstrap.Dropdown.getInstance(testToggle);
                if (dropdown) {
                    console.log('Using existing dropdown instance');
                    dropdown.toggle();
                } else {
                    console.log('Creating new dropdown instance');
                    try {
                        var newDropdown = new bootstrap.Dropdown(testToggle);
                        newDropdown.toggle();
                    } catch (error) {
                        console.error('Bootstrap dropdown failed:', error);
                        // Fallback: manual toggle
                        var menu = testToggle.nextElementSibling;
                        if (menu && menu.classList.contains('dropdown-menu')) {
                            menu.classList.toggle('show');
                            console.log('Manual dropdown toggle');
                        }
                    }
                }
            });
            
            // Test if button is clickable
            console.log('Button clickable test:', testToggle.offsetWidth, testToggle.offsetHeight);
        }
    }, 1000);
});

// Logout confirmation with SweetAlert2
function confirmLogout() {
  Swal.fire({
    title: 'Confirm Logout',
    text: 'Are you sure you want to logout? You will need to sign in again to access your account.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#7F1734',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i>Yes, Logout',
    cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
    customClass: {
      popup: 'swal2-success',
      confirmButton: 'swal2-confirm',
      cancelButton: 'swal2-cancel'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading state
      Swal.fire({
        title: 'Logging Out...',
        text: 'Please wait while we log you out.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        },
        customClass: {
          popup: 'swal2-info'
        }
      });
      
      // Redirect to logout page
      setTimeout(() => {
        window.location.href = 'logout.php';
      }, 1000);
    }
  });
}
</script>

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
    background: var(--bg-card);
    z-index: 1050;
    transition: right 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    overflow-y: auto;
    box-shadow: -5px 0 15px var(--shadow-dark);
    display: flex;
    flex-direction: column;
  }

  .sliding-cart.active {
    right: 0; /* Slide in */
  }

  .cart-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-tertiary);
    flex-shrink: 0;
  }

  .cart-header h4 {
      color: var(--brand-primary);
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
      color: var(--text-primary);
  }

  .cart-body {
    padding: 1rem;
    flex: 1;
    overflow-y: auto;
    background: var(--bg-card);
  }

  /* Cart Items */
  .empty-cart-state {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--text-primary);
  }

  .empty-cart-state i {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    color: var(--bs-secondary);
    opacity: 0.7;
  }

  .empty-cart-state h6 {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 1rem;
  }

  .empty-cart-state p {
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-bottom: 1rem;
  }

  .empty-cart-state .btn {
    background: var(--bs-secondary);
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
  }

  .empty-cart-state .btn:hover {
    background: #6d1429;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(127, 23, 52, 0.3);
  }

  .empty-cart-state .btn i {
    color: white !important;
  }

  .cart-items {
    padding: 0.5rem 0;
  }

  .cart-item {
    display: flex;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-light);
    gap: 0.75rem;
    transition: background-color 0.2s ease;
  }

  .cart-item:last-child {
    border-bottom: none;
  }

  .cart-item:hover {
      background-color: var(--bg-tertiary);
  }

  .item-selection {
      padding: 0 0.5rem;
  }

  .select-item {
      accent-color: var(--brand-primary);
      width: 18px;
      height: 18px;
      cursor: pointer;
  }

  .item-image img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid var(--border-light);
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
    color: var(--text-primary);
  }

  .item-price {
    font-size: 0.8rem;
    color: var(--text-secondary);
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
    border: 1px solid var(--border-light);
    border-radius: 4px;
    overflow: hidden;
  }

  .qty-btn {
    background: var(--bg-tertiary);
    border: none;
    padding: 0.25rem 0.75rem;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    transition: background-color 0.2s;
    color: var(--text-primary);
  }

  .qty-btn:hover {
    background: var(--border-light);
  }

  .cart-item-quantity {
    padding: 0.25rem 0.75rem;
    font-size: 0.9rem;
    font-weight: 600;
    min-width: 30px;
    text-align: center;
    background: var(--bg-card);
    color: var(--text-primary);
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
    border-top: 1px solid var(--border-light);
    background: var(--bg-tertiary);
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .cart-footer .fw-bold {
      color: var(--text-primary);
  }
  .cart-footer .selected-total, .cart-footer .cart-total {
      color: var(--brand-primary);
  }

  /* Buttons in footer */
  .cart-footer .btn-primary {
      background-color: var(--brand-primary);
      border-color: var(--brand-primary);
  }
  .cart-footer .btn-primary:hover {
      background-color: var(--brand-secondary);
      border-color: var(--brand-secondary);
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
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-card);
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
    gap: 0.75rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px var(--shadow-light);
  }

  .cart-item-sliding:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--shadow-medium);
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
    border: 2px solid var(--border-light);
    flex-shrink: 0;
  }

  .item-details {
    flex: 1;
    min-width: 0;
  }

  .item-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
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
    color: var(--text-secondary);
    font-weight: 500;
    margin-bottom: 0.25rem;
  }

  .item-stock {
    font-size: 0.75rem;
    color: var(--text-secondary);
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
    border: 1px solid var(--border-light);
    border-radius: 0.375rem;
    overflow: hidden;
    background: var(--bg-card);
  }

  .quantity-btn {
    background: var(--bg-tertiary);
    border: none;
    padding: 0.375rem 0.5rem;
    cursor: pointer;
    font-size: 0.8rem;
    line-height: 1;
    transition: all 0.2s ease;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
  }

  .quantity-btn:hover:not(:disabled) {
    background: var(--brand-primary);
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
    background: var(--bg-card);
    color: var(--text-primary);
    border-left: 1px solid var(--border-light);
    border-right: 1px solid var(--border-light);
    transition: all 0.2s ease;
  }
  
  /* Override for input type quantity display */
  .quantity-display.quantity-input {
    border: none;
    outline: none;
    box-shadow: none;
    background: var(--bg-card);
    width: 60px;
    padding: 0.375rem 0.5rem;
    text-align: center;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-primary);
    border-left: 1px solid var(--border-light);
    border-right: 1px solid var(--border-light);
    transition: all 0.2s ease;
  }
  
  .quantity-display.quantity-input:focus {
    background: var(--bg-tertiary);
    border-left-color: var(--brand-primary);
    border-right-color: var(--brand-primary);
  }
  
  .quantity-display.quantity-input::-webkit-outer-spin-button,
  .quantity-display.quantity-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  
  .quantity-display.quantity-input[type=number] {
    -moz-appearance: textfield;
    appearance: textfield;
  }

  /* Decimal input styling for sliding cart */
  .cart-item-sliding .quantity-controls { 
    border: 1px solid var(--border-light);
    border-radius: 0.375rem;
    overflow: hidden;
    background: var(--bg-card);
  }
  
  .cart-item-sliding input.quantity-input {
    border: none;
    outline: none;
    box-shadow: none;
    background: var(--bg-card);
    width: 60px;
    padding: 0.375rem 0.5rem;
    text-align: center;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-primary);
    border-left: 1px solid var(--border-light);
    border-right: 1px solid var(--border-light);
    transition: all 0.2s ease;
  }
  
  .cart-item-sliding input.quantity-input:focus {
    background: var(--bg-tertiary);
    border-left-color: var(--brand-primary);
    border-right-color: var(--brand-primary);
  }
  
  .cart-item-sliding input.quantity-input::-webkit-outer-spin-button,
  .cart-item-sliding input.quantity-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  
  .cart-item-sliding input.quantity-input[type=number] {
    -moz-appearance: textfield;
    appearance: textfield;
  }

  .item-total {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--brand-primary);
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
    background: var(--bg-tertiary);
    color: var(--bs-danger);
  }

  .cart-items-container {
    padding: 0.5rem 0;
  }

  .cart-summary {
    background: var(--bg-card);
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid var(--border-light);
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

  /* Dropdown Styles */
  .navbar .dropdown-menu {
    background-color: var(--modal-bg);
    border-color: var(--border-medium);
    box-shadow: 0 4px 20px var(--shadow-dark);
    z-index: 1050;
    min-width: 180px;
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    right: auto;
  }

  .dropdown-item {
    color: var(--text-primary);
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    border-left: 3px solid transparent;
    border-radius: 0.5rem;
    margin-bottom: 0.25rem;
  }

  .dropdown-item:hover {
    background-color: var(--bg-tertiary);
    color: var(--brand-primary);
    border-left-color: var(--brand-primary);
    transform: translateX(2px);
  }

  .dropdown-item:focus {
    background-color: var(--bg-tertiary);
    color: var(--brand-primary);
    outline: none;
    border-left-color: var(--brand-primary);
  }

  .dropdown-item i {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
  }

  /* Logout button special styling */
  .dropdown-item.logout-item {
    color: var(--bs-danger);
  }

  .dropdown-item.logout-item:hover {
    background-color: var(--bs-danger);
    color: var(--text-light);
    border-left-color: var(--bs-danger);
  }

  .dropdown-divider {
    border-color: var(--border-light);
    margin: 0.5rem 0;
  }

  /* Ensure dropdown is visible */
  .dropdown-menu.show {
    display: block;
  }

  /* Dark mode toggle button styling */
  .dark-mode-toggle {
    background: none;
    border: none;
    color: var(--text-primary);
    padding: 0.5rem 1rem;
    width: 100%;
    text-align: left;
    transition: all 0.2s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
  }

  .dark-mode-toggle:hover {
    background-color: var(--bg-tertiary);
    color: var(--brand-primary);
  }

  .dark-mode-toggle i {
    margin-right: 0.5rem;
    width: 16px;
    text-align: center;
  }

  /* Profile Dropdown Button */
  .profile-dropdown-btn {
    border: none;
    background: none;
    cursor: pointer;
  }

  /* Hide Bootstrap dropdown arrow */
  .profile-dropdown-btn::after {
    display: none !important;
  }

  .profile-dropdown-btn:hover {
    color: var(--text-primary) !important;
  }

  .profile-dropdown-btn:focus {
    color: var(--text-primary) !important;
    box-shadow: none;
  }

  .profile-dropdown-btn:active {
    color: var(--text-primary) !important;
  }

  /* SweetAlert2 Custom Styles */
  .swal2-popup {
    border-radius: 1rem !important;
    font-family: 'Inter', sans-serif !important;
  }

  .swal2-title {
    font-weight: 600 !important;
  }

  .swal2-confirm {
    background-color: var(--brand-primary) !important;
    border-color: var(--brand-primary) !important;
  }

  .swal2-cancel {
    background-color: var(--text-secondary) !important;
    border-color: var(--text-secondary) !important;
  }

  [data-theme="dark"] .swal2-popup {
    background-color: var(--bg-card) !important;
    color: var(--text-primary) !important;
  }

  [data-theme="dark"] .swal2-title {
    color: var(--text-primary) !important;
  }

  [data-theme="dark"] .swal2-content {
    color: var(--text-secondary) !important;
  }
</style>