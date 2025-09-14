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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="fw-bold">Total:</span>
            <span class="fw-bold cart-total">₱0.00</span>
        </div>
        <div class="d-grid gap-2">
            <button class="btn btn-primary" onclick="window.location.href='cart.php'">
                <i class="fas fa-shopping-cart me-2"></i>View Cart
            </button>
            <button class="btn btn-success" onclick="window.location.href='checkout.php'">
                <i class="fas fa-credit-card me-2"></i>Checkout
            </button>
        </div>
    </div>
</div>
<!-- Navbar -->
<nav class="navbar navbar-light bg-white border-bottom sticky-top shadow-sm">
  <div class="container d-flex align-items-center justify-content-between">

    <!-- Logo + Search -->
    <div class="d-flex align-items-center flex-grow-1" style="gap: 15px;">
      <!-- Logo -->
      <a class="navbar-brand d-flex align-items-center mb-0" href="index.php">
        <img src="images/Mike&MadzLogo (3).png" alt="Mike & Madz Logo" 
             style="max-height:70px; width:auto;">
      </a>

      <!-- Search Bar -->
      <div class="search-container d-none d-lg-flex flex-grow-1">
        <div class="search-input-wrapper position-relative w-100">
          <i class="fas fa-search search-icon"></i>
          <input type="text" 
                 class="form-control search-input" 
                 id="searchInput" 
                 placeholder="Search for meat, seafood, and more..."
                 autocomplete="off">
          <button class="search-clear-btn" id="searchClear" style="display: none;">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    </div>
        
        <!-- Search Results Dropdown -->
        <div class="search-results" id="searchResults" style="display: none;">
          <div class="search-results-content" id="searchResultsContent">
            <!-- Search results will be populated here -->
          </div>
        </div>
      </div>
    </div>

    <!-- Toggle Button for Mobile -->
    <button class="btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNav">
      <i class="fas fa-bars fs-4 text-dark"></i>
    </button>

    <!-- Desktop Icons -->
    <div class="d-none d-lg-flex align-items-center gap-4 me-4">
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
    <!-- Mobile Search -->
    <div class="mobile-search-container position-relative">
      <div class="search-input-wrapper">
        <i class="fas fa-search search-icon"></i>
        <input type="text" 
               class="form-control search-input" 
               id="mobileSearchInput" 
               placeholder="Search products..."
               autocomplete="off">
        <button class="search-clear-btn" id="mobileSearchClear" style="display: none;">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <!-- Mobile Search Results -->
      <div class="search-results mobile-search-results" id="mobileSearchResults" style="display: none;">
        <div class="search-results-content" id="mobileSearchResultsContent">
          <!-- Search results will be populated here -->
        </div>
      </div>
    </div>

    <a class="text-dark <?php if ($current == 'favorite.php') echo 'fw-bold'; ?>" href="favorite.php">
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
              const cartTotal = document.querySelector('.cart-total');
              
              if (data.has_items) {
                  cartFooter.style.display = 'block';
                  if (cartTotal) {
                      cartTotal.textContent = '₱' + data.total.toLocaleString('en-US', { 
                          minimumFractionDigits: 2, 
                          maximumFractionDigits: 2 
                      });
                  }
              } else {
                  cartFooter.style.display = 'none';
              }
          })
          .catch(error => {
              console.error('Error loading cart total:', error);
          });
  }

  // Search functionality
  let searchTimeout;
  let currentRequest;

  function initializeSearch() {
    const searchInputs = document.querySelectorAll('#searchInput, #mobileSearchInput');
    const searchClearBtns = document.querySelectorAll('#searchClear, #mobileSearchClear');

    searchInputs.forEach((input, index) => {
      const isMobile = input.id === 'mobileSearchInput';
      const resultsContainer = isMobile ? 
        document.getElementById('mobileSearchResults') : 
        document.getElementById('searchResults');
      const resultsContent = isMobile ? 
        document.getElementById('mobileSearchResultsContent') : 
        document.getElementById('searchResultsContent');
      const clearBtn = searchClearBtns[index];

      input.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Show/hide clear button
        if (query.length > 0) {
          clearBtn.style.display = 'block';
        } else {
          clearBtn.style.display = 'none';
          hideSearchResults(resultsContainer);
          return;
        }

        // Clear previous timeout
        if (searchTimeout) {
          clearTimeout(searchTimeout);
        }

        // Set new timeout for search
        searchTimeout = setTimeout(() => {
          performSearch(query, resultsContainer, resultsContent);
        }, 300);
      });

      input.addEventListener('focus', function() {
        const query = this.value.trim();
        if (query.length > 0) {
          performSearch(query, resultsContainer, resultsContent);
        }
      });

      // Clear button functionality
      clearBtn.addEventListener('click', function() {
        input.value = '';
        this.style.display = 'none';
        hideSearchResults(resultsContainer);
        input.focus();
      });
    });

    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
      const searchContainers = document.querySelectorAll('.search-container, .mobile-search-container');
      let clickedInside = false;

      searchContainers.forEach(container => {
        if (container.contains(e.target)) {
          clickedInside = true;
        }
      });

      if (!clickedInside) {
        hideSearchResults(document.getElementById('searchResults'));
        hideSearchResults(document.getElementById('mobileSearchResults'));
      }
    });

    // Handle keyboard navigation
    searchInputs.forEach(input => {
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          const isMobile = this.id === 'mobileSearchInput';
          const resultsContainer = isMobile ? 
            document.getElementById('mobileSearchResults') : 
            document.getElementById('searchResults');
          hideSearchResults(resultsContainer);
          this.blur();
        }
      });
    });
  }

  function performSearch(query, resultsContainer, resultsContent) {
    if (query.length < 2) {
      hideSearchResults(resultsContainer);
      return;
    }

    // Cancel previous request
    if (currentRequest) {
      currentRequest.abort();
    }

    // Show loading state
    showSearchLoading(resultsContainer, resultsContent);

    // Create new request
    currentRequest = new AbortController();
    
    fetch(`search_products.php?q=${encodeURIComponent(query)}`, {
      signal: currentRequest.signal
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displaySearchResults(data.results, resultsContainer, resultsContent);
      } else {
        showSearchError(resultsContainer, resultsContent, data.message || 'Search failed');
      }
    })
    .catch(error => {
      if (error.name !== 'AbortError') {
        console.error('Search error:', error);
        showSearchError(resultsContainer, resultsContent, 'Search failed. Please try again.');
      }
    })
    .finally(() => {
      currentRequest = null;
    });
  }

  function showSearchLoading(resultsContainer, resultsContent) {
    resultsContent.innerHTML = `
      <div class="search-loading">
        <i class="fas fa-spinner"></i>
        <div class="mt-2">Searching...</div>
      </div>
    `;
    resultsContainer.style.display = 'block';
  }

  function displaySearchResults(results, resultsContainer, resultsContent) {
    if (results.length === 0) {
      resultsContent.innerHTML = `
        <div class="search-no-results">
          <i class="fas fa-search"></i>
          <div>No products found</div>
          <small>Try searching with different keywords</small>
        </div>
      `;
    } else {
      const resultsHtml = results.map(product => `
        <a href="${product.url}" class="search-result-item">
          <img src="${product.image}" alt="${product.name}" class="search-result-image" onerror="this.src='images/placeholder.jpg'">
          <div class="search-result-details">
            <div class="search-result-name">${product.name}</div>
            <div class="search-result-category">${product.category}</div>
            <div class="search-result-price">₱${product.price}</div>
            <div class="search-result-stock ${product.stock <= 0 ? 'out-of-stock' : ''}">${product.stock > 0 ? 'In Stock' : 'Out of Stock'}</div>
          </div>
        </a>
      `).join('');
      
      resultsContent.innerHTML = resultsHtml;
    }
    
    resultsContainer.style.display = 'block';
  }

  function showSearchError(resultsContainer, resultsContent, message) {
    resultsContent.innerHTML = `
      <div class="search-no-results">
        <i class="fas fa-exclamation-triangle text-warning"></i>
        <div>${message}</div>
      </div>
    `;
    resultsContainer.style.display = 'block';
  }

  function hideSearchResults(resultsContainer) {
    if (resultsContainer) {
      resultsContainer.style.display = 'none';
    }
  }

  // Initial setup when the page loads
  document.addEventListener('DOMContentLoaded', () => {
      loadCartContent(); // Load cart content on page load
      updateCartBadge(); // Ensure badge is correct on load
      initializeSearch(); // Initialize search functionality
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
    .cart-item {
        flex-wrap: wrap;
        justify-content: center;
        text-align: center;
        gap: 0.5rem;
    }
    .item-image {
        margin-bottom: 0.5rem;
    }
    .item-details {
        width: 100%;
    }
    .item-controls {
        width: 100%;
        flex-direction: row;
        justify-content: space-around;
        margin-top: 0.5rem;
        align-items: center;
    }
    .quantity-controls {
        flex-direction: row;
    }
    .remove-btn {
        margin-top: 0;
    }
  }

  /* Animation for cart items */
  .cart-item {
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

  /* Search Styles */
  .search-container {
    max-width: 500px;
  }

  .search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
  }

  .search-input {
    border: 2px solid #e9ecef;
    border-radius: 2rem;
    padding: 0.75rem 3rem 0.75rem 3rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  }

  .search-input:focus {
    border-color: var(--bs-secondary);
    background: white;
    box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
    outline: none;
  }

  .search-icon {
    position: absolute;
    left: 1rem;
    color: #6c757d;
    font-size: 1rem;
    z-index: 10;
    transition: color 0.3s ease;
  }

  .search-input:focus + .search-icon,
  .search-input:not(:placeholder-shown) + .search-icon {
    color: var(--bs-secondary);
  }

  .search-clear-btn {
    position: absolute;
    right: 1rem;
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    transition: all 0.3s ease;
    z-index: 10;
  }

  .search-clear-btn:hover {
    color: var(--bs-danger);
    background: #f8f9fa;
  }

  /* Search Results Dropdown */
  .search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 1rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    z-index: 1000;
    max-height: 400px;
    overflow-y: auto;
    margin-top: 0.5rem;
  }

  .search-results-content {
    padding: 0.5rem;
  }

  .search-result-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    gap: 0.75rem;
  }

  .search-result-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: translateY(-1px);
    color: inherit;
    text-decoration: none;
  }

  .search-result-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 0.5rem;
    border: 1px solid #e9ecef;
    flex-shrink: 0;
  }

  .search-result-details {
    flex: 1;
    min-width: 0;
  }

  .search-result-name {
    font-weight: 600;
    color: #333;
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    line-height: 1.3;
  }

  .search-result-category {
    font-size: 0.8rem;
    color: #6c757d;
    margin: 0 0 0.25rem 0;
  }

  .search-result-price {
    font-weight: 700;
    color: var(--bs-secondary);
    font-size: 0.9rem;
    margin: 0;
  }

  .search-result-stock {
    font-size: 0.75rem;
    color: #28a745;
    margin: 0;
  }

  .search-result-stock.out-of-stock {
    color: var(--bs-danger);
  }

  .search-no-results {
    text-align: center;
    padding: 2rem 1rem;
    color: #6c757d;
  }

  .search-no-results i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
  }

  .search-loading {
    text-align: center;
    padding: 1rem;
    color: #6c757d;
  }

  .search-loading i {
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  /* Mobile Search Styles */
  .mobile-search-container {
    margin-bottom: 1rem;
  }

  .mobile-search-results {
    margin-top: 0.5rem;
    max-height: 300px;
  }

  /* Search Result Animations */
  .search-result-item {
    animation: fadeInResult 0.3s ease-out;
  }

  @keyframes fadeInResult {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Enhanced Search Focus States */
  .search-input:focus {
    border-color: var(--bs-secondary);
    background: white;
    box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
    outline: none;
  }

  /* Responsive Search */
  @media (max-width: 768px) {
    .search-container {
      max-width: 100%;
    }
    
    .search-input {
      font-size: 0.9rem;
      padding: 0.625rem 2.5rem 0.625rem 2.5rem;
    }

    .search-icon {
      left: 0.75rem;
      font-size: 0.9rem;
    }

    .search-clear-btn {
      right: 0.75rem;
    }
  }
</style>