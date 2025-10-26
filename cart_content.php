<?php
// Session already started in cart.php, so we don't need to start it again
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/db.php';
include_once 'includes/cart_manager.php';

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_items = [];
$cart_total = 0;

if (!empty($_SESSION['cart']) && isset($_SESSION['user_id'])) {
    // Use CartManager to load cart data from database
    $cartManager = new CartManager($pdo);
    $cart_data = $cartManager->loadCartFromDatabase($_SESSION['user_id']);
    
    foreach ($cart_data as $cart_key => $cart_item) {
        // Handle both simple product_id keys and composite keys (product_id_unit_boxid)
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $brand_id = $cart_item['brand_id'] ?? null;
        // Ensure brand_id is integer for SQL queries
        if ($brand_id !== null) {
            $brand_id = intval($brand_id);
        }
        $qty = $cart_item['quantity'] ?? 1;
        $unit = $cart_item['unit'] ?? 'kilo';
        $weight = $cart_item['weight'] ?? null;
        
        // Get product basic info
        $product_sql = "SELECT p.product_name, p.product_description,
                        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
                       FROM products p 
                       WHERE p.product_id = ? AND p.is_archive = 0";
        $product_stmt = $pdo->prepare($product_sql);
        $product_stmt->execute([$product_id]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) continue;
        
        // Get brand-specific pricing and stock if brand_id is provided
            if ($brand_id) {
            $brand_sql = "SELECT 
                            b.name as brand_name,
                            pb.unit_cost,
                            pb.quantity_remaining,
                            u.name as unit_name,
                            COALESCE(pp.markup_price, 0) as markup_price,
                            (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price,
                            (SELECT COALESCE(SUM(pb2.quantity_remaining), 0) 
                             FROM product_batches pb2 
                             WHERE pb2.product_id = pb.product_id 
                             AND pb2.brand_id = pb.brand_id 
                             AND pb2.quantity_remaining > 0 
                             AND pb2.is_active = 1) as total_stock
                         FROM product_batches pb
                         JOIN brands b ON pb.brand_id = b.id
                         JOIN products p ON pb.product_id = p.product_id
                         JOIN uom u ON p.uom_id = u.uom_id
                         LEFT JOIN product_pricing pp ON pb.product_id = pp.product_id
                         WHERE pb.product_id = ? 
                         AND pb.brand_id = ?
                         AND pb.quantity_remaining > 0 
                         AND pb.is_active = 1
                         ORDER BY pb.received_date DESC, pb.batch_id DESC
                         LIMIT 1";
            
            $brand_stmt = $pdo->prepare($brand_sql);
            $brand_stmt->execute([$product_id, $brand_id]);
            $brand_data = $brand_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($brand_data) {
                $unit_price = $brand_data['unit_cost'];
                $markup_price = $brand_data['markup_price'];
                $final_price = $brand_data['final_price'];
                $stock = $brand_data['total_stock'];
                $brand_name = $brand_data['brand_name'];
                $unit_name = $brand_data['unit_name'];
            } else {
                // Fallback to general pricing if brand-specific not found
                $general_sql = "SELECT 
                                  COALESCE(pp.markup_price, 0) + COALESCE((
                                      SELECT pb.unit_cost 
                                      FROM product_batches pb 
                                      WHERE pb.product_id = p.product_id 
                                      AND pb.quantity_remaining > 0 
                                      AND pb.is_active = 1
                                      ORDER BY pb.received_date DESC, pb.batch_id DESC 
                                      LIMIT 1
                                  ), 0) AS final_price,
                                  COALESCE(ps.current_stock, 0) AS stock,
                                  u.name as unit_name
                               FROM products p
                               LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                               LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                               LEFT JOIN uom u ON p.uom_id = u.uom_id
                               WHERE p.product_id = ? AND p.is_archive = 0";
                
                $general_stmt = $pdo->prepare($general_sql);
                $general_stmt->execute([$product_id]);
                $general_data = $general_stmt->fetch(PDO::FETCH_ASSOC);
                
                $unit_price = 0;
                $markup_price = $general_data['final_price'] ?? 0;
                $final_price = $general_data['final_price'] ?? 0;
                $stock = $general_data['stock'] ?? 0;
                $brand_name = 'General';
                $unit_name = 'kilo';
            }
        } else {
            // Use general pricing
            $general_sql = "SELECT 
                              COALESCE(pp.markup_price, 0) + COALESCE((
                                  SELECT pb.unit_cost 
                                  FROM product_batches pb 
                                  WHERE pb.product_id = p.product_id 
                                  AND pb.quantity_remaining > 0 
                                  AND pb.is_active = 1
                                  ORDER BY pb.received_date DESC, pb.batch_id DESC 
                                  LIMIT 1
                              ), 0) AS final_price,
                              COALESCE(ps.current_stock, 0) AS stock,
                              u.name as unit_name
                           FROM products p
                           LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                           LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                           LEFT JOIN uom u ON p.uom_id = u.uom_id
                           WHERE p.product_id = ? AND p.is_archive = 0";
            
            $general_stmt = $pdo->prepare($general_sql);
            $general_stmt->execute([$product_id]);
            $general_data = $general_stmt->fetch(PDO::FETCH_ASSOC);
            
            $unit_price = 0;
            $markup_price = $general_data['final_price'] ?? 0;
            $final_price = $general_data['final_price'] ?? 0;
            $stock = $general_data['stock'] ?? 0;
            $brand_name = 'General';
            $unit_name = 'kilo';
        }
        
        // Calculate item total using final_price (unit_cost + markup_price)
        $item_total = $final_price * $qty;
            $cart_total += $item_total;
            
        // Create display name with brand info
        $display_name = $product['product_name'];
        if ($brand_name && $brand_name !== 'General') {
            $display_name .= " - " . $brand_name;
        }
        
        // Add unit info
            if ($unit === 'piece') {
            $display_name .= " (per piece)";
            } else if ($unit === 'box' && $weight) {
            $display_name .= " (Box - " . number_format($weight, 2) . "kg)";
            } else {
            $display_name .= " (per " . $unit_name . ")";
            }
            
            $cart_items[] = [
                'product' => [
                'id' => $product_id,
                    'name' => $display_name,
                'price' => (float)$final_price, // This is unit_cost + markup_price
                'image1' => $product['image1'] ?? '',
                    'stock' => (float)$stock
                ],
                'quantity' => $qty,
                'total' => $item_total,
            'unit' => $unit_name,
                'weight' => $weight,
                'brand_id' => $brand_id,
                'brand_name' => $brand_name ?? null,
            'cart_key' => $cart_key
            ];
        }
    }

// Store cart total in session
$_SESSION['cart_total'] = $cart_total;
?>

<style>
/* Minimalist Cart Selection Styles */
.item-selection {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
}

.cart-item-checkbox {
    width: 18px;
    height: 18px;
    accent-color: #7F1734;
    cursor: pointer;
}

.cart-item-sliding {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border-light);
    transition: background-color 0.2s ease;
}

.cart-item-sliding:hover {
    background-color: var(--bg-tertiary);
}

.cart-selection-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg-tertiary);
    border-top: 1px solid var(--border-light);
    font-size: 0.9rem;
}

.selection-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.selection-toggle input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #7F1734;
}

.selected-count {
    color: var(--text-secondary);
    font-weight: 500;
}

#selected-count {
    color: var(--brand-primary);
    font-weight: 700;
}
</style>

<?php if (empty($cart_items)): ?>
    <div class="empty-cart-state">
        <i class="fas fa-shopping-cart"></i>
        <h6>Cart is empty</h6>
        <p>Add products to get started</p>
        <button class="btn" onclick="closeCart(); window.location.href='product.php'">
            <i class="fas fa-shopping-bag me-1"></i>Browse Products
        </button>
    </div>
<?php else: ?>
    <!-- Selection Controls at Top -->
    <div class="cart-selection-controls">
        <div class="selection-toggle">
            <input type="checkbox" id="select-all-items" checked>
            <label for="select-all-items">Select All</label>
        </div>
        <div class="selected-count">
            <span id="selected-count"><?= count($cart_items) ?></span> items selected
        </div>
    </div>
    
    <div class="cart-items-container">
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item-sliding" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>">
                <div class="item-selection">
                    <input type="checkbox" class="cart-item-checkbox" 
                           data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>">
                </div>
                
                <div class="item-image">
                    <img src="<?= !empty($item['product']['image1']) ? 'admin/' . htmlspecialchars($item['product']['image1']) : 'images/placeholder.jpg' ?>" 
                         alt="<?= htmlspecialchars($item['product']['name']) ?>" 
                         class="cart-item-img"
                         onerror="this.src='images/placeholder.jpg'">
                </div>
                
                <div class="item-details">
                    <div class="item-name"><?= htmlspecialchars($item['product']['name']) ?></div>
                    <?php if ($item['brand_id'] && isset($item['brand_name'])): ?>
                        <div class="item-brand text-primary small">
                            <i class="fas fa-tag me-1"></i>
                            Brand: <?= htmlspecialchars($item['brand_name']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="item-price">₱<?= number_format($item['product']['price'], 2) ?> each</div>
                    <div class="item-stock small" style="color: var(--text-secondary);">
                        <i class="fas fa-box me-1"></i>
                        <?= (float)$item['product']['stock'] > 0 ? number_format((float)$item['product']['stock'], 1) . ' in stock' : 'Out of stock' ?>
                    </div>
                </div>
                
                <div class="item-controls">
                    <div class="quantity-controls">
                        <button class="quantity-btn decrease-cart" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>"
                                <?= (float)$item['quantity'] <= 1.0 ? 'disabled' : '' ?>>
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-display quantity-input" value="<?= number_format((float)$item['quantity'], 1, '.', '') ?>" step="0.1" min="1.0" max="<?= (float)$item['product']['stock'] ?>" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>" inputmode="decimal" aria-label="Quantity" />
                        <button class="quantity-btn increase-cart" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>"
                                <?= (float)$item['quantity'] >= (float)$item['product']['stock'] ? 'disabled' : '' ?>>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="item-total">₱<?= number_format($item['total'], 2) ?></div>
                    
                    <button class="remove-cart-item" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>" title="Remove Item">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
console.log('🎯 SCRIPT START: cart_content.php JavaScript is running!');
console.log('📍 Current URL:', window.location.href);
console.log('📅 Timestamp:', new Date().toISOString());

// Cart Selection Management
function initializeCartSelection() {
    console.log('🎯 Cart selection system initializing...');
    console.log('📍 Current page:', window.location.pathname);
    
    const selectAllCheckbox = document.getElementById('select-all-items');
    const selectedCountSpan = document.getElementById('selected-count');
    
    console.log('🔍 Elements found:');
    console.log('  - Select All checkbox:', selectAllCheckbox ? 'YES' : 'NO');
    console.log('  - Selected count span:', selectedCountSpan ? 'YES' : 'NO');
    
    // Update selected count and cart total - always get fresh elements
    function updateSelectedCount() {
        console.log('🔄 updateSelectedCount() called');
        console.log('📍 Call stack:', new Error().stack);
        
        // Always get fresh references to elements
        const currentItemCheckboxes = document.querySelectorAll('.cart-item-checkbox');
        const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
        
        console.log('🔍 DEBUG: Element Analysis');
        console.log('  - Current item checkboxes found:', currentItemCheckboxes.length);
        console.log('  - Checked boxes found:', checkedBoxes.length);
        console.log('  - Select All checkbox exists:', selectAllCheckbox ? 'YES' : 'NO');
        console.log('  - Selected count span exists:', selectedCountSpan ? 'YES' : 'NO');
        
        // Debug: Check cart items in DOM
        const cartItems = document.querySelectorAll('.cart-item-sliding');
        console.log('  - Cart items in DOM:', cartItems.length);
        cartItems.forEach((item, index) => {
            const cartKey = item.dataset.cartKey;
            const checkbox = item.querySelector('.cart-item-checkbox');
            console.log(`  📦 Cart Item ${index + 1}:`, {
                cartKey: cartKey,
                hasCheckbox: checkbox ? 'YES' : 'NO',
                checkboxChecked: checkbox ? checkbox.checked : 'N/A'
            });
        });
        
        // Debug: Log each checkbox state
        console.log('🔍 DEBUG: All checkboxes in DOM:');
        const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
        console.log('  - Total checkboxes in DOM:', allCheckboxes.length);
        allCheckboxes.forEach((checkbox, index) => {
            console.log(`  📋 DOM Checkbox ${index + 1}:`, {
                class: checkbox.className,
                id: checkbox.id,
                checked: checkbox.checked,
                cartKey: checkbox.dataset.cartKey,
                element: checkbox
            });
        });
        
        console.log('🔍 DEBUG: Cart item checkboxes only:');
        currentItemCheckboxes.forEach((checkbox, index) => {
            console.log(`  📋 Cart Checkbox ${index + 1}:`, {
                checked: checkbox.checked,
                cartKey: checkbox.dataset.cartKey,
                element: checkbox
            });
        });
        
        if (selectedCountSpan) {
            selectedCountSpan.textContent = checkedBoxes.length;
            console.log('✅ Updated selected count span to:', checkedBoxes.length);
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            const previousState = {
                checked: selectAllCheckbox.checked,
                indeterminate: selectAllCheckbox.indeterminate
            };
            
            if (checkedBoxes.length === currentItemCheckboxes.length && currentItemCheckboxes.length > 0) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
                console.log('🔘 Select All: All checked');
                console.log('🔘 State change:', previousState, '→', {checked: true, indeterminate: false});
            } else if (checkedBoxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
                console.log('🔘 Select All: None checked');
                console.log('🔘 State change:', previousState, '→', {checked: false, indeterminate: false});
            } else {
                selectAllCheckbox.indeterminate = true;
                console.log('🔘 Select All: Some checked (indeterminate)');
                console.log('🔘 State change:', previousState, '→', {checked: false, indeterminate: true});
            }
        }
        
        // Update cart total based on selected items
        updateCartTotalForSelected();
    }
    
    // Update cart total for selected items only
    function updateCartTotalForSelected() {
        console.log('💰 updateCartTotalForSelected() called');
        
        const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
        let selectedTotal = 0;
        
        console.log('🔍 Processing', checkedBoxes.length, 'checked items');
        
        checkedBoxes.forEach((checkbox, index) => {
            const cartKey = checkbox.dataset.cartKey;
            console.log(`📋 Item ${index + 1}: cartKey = ${cartKey}`);
            
            const cartItem = document.querySelector(`[data-cart-key="${cartKey}"]`);
            if (cartItem) {
                const itemTotalElement = cartItem.querySelector('.item-total');
                if (itemTotalElement) {
                    const itemTotalText = itemTotalElement.textContent;
                    const itemTotal = parseFloat(itemTotalText.replace('₱', '').replace(',', ''));
                    console.log(`💵 Item ${index + 1}: ${itemTotalText} = ${itemTotal}`);
                    selectedTotal += itemTotal;
                } else {
                    console.log(`❌ Item ${index + 1}: No .item-total element found`);
                }
            } else {
                console.log(`❌ Item ${index + 1}: No cart item found for key ${cartKey}`);
            }
        });
        
        console.log('💯 Total calculated:', selectedTotal);
        
        // Update cart total display (only if element exists)
        const cartTotalElement = document.getElementById('cart-total-display');
        if (cartTotalElement) {
            cartTotalElement.textContent = '₱' + selectedTotal.toFixed(2);
            console.log('✅ Updated #cart-total-display:', cartTotalElement.textContent);
        } else {
            console.log('❌ #cart-total-display element not found');
        }
        
        // Update sliding cart footer totals
        const cartSubtotal = document.querySelector('.cart-subtotal');
        const cartTotal = document.querySelector('.cart-total');
        const cartFooter = document.getElementById('cartFooter');
        
        console.log('🔍 Footer elements found:');
        console.log('  - .cart-subtotal:', cartSubtotal ? 'YES' : 'NO');
        console.log('  - .cart-total:', cartTotal ? 'YES' : 'NO');
        console.log('  - #cartFooter:', cartFooter ? 'YES' : 'NO');
        
        if (cartSubtotal) {
            cartSubtotal.textContent = '₱' + selectedTotal.toFixed(2);
            console.log('✅ Updated .cart-subtotal:', cartSubtotal.textContent);
        }
        if (cartTotal) {
            cartTotal.textContent = '₱' + selectedTotal.toFixed(2);
            console.log('✅ Updated .cart-total:', cartTotal.textContent);
        }
        
        // Show/hide footer based on selection
        if (cartFooter) {
            if (selectedTotal > 0) {
                cartFooter.style.display = 'block';
                console.log('👁️ Footer shown (selectedTotal > 0)');
            } else {
                cartFooter.style.display = 'none';
                console.log('🙈 Footer hidden (selectedTotal = 0)');
            }
        }
        
        // Trigger custom event for cart.php to handle
        const event = new CustomEvent('cartSelectionChanged', {
            detail: { selectedTotal: selectedTotal, selectedCount: checkedBoxes.length }
        });
        document.dispatchEvent(event);
        console.log('📡 Dispatched cartSelectionChanged event:', { selectedTotal, selectedCount: checkedBoxes.length });
    }
    
    // Select all functionality
    if (selectAllCheckbox) {
        // Remove any existing event listeners to prevent duplicates
        selectAllCheckbox.removeEventListener('change', handleSelectAll);
        selectAllCheckbox.addEventListener('change', handleSelectAll);
        
        function handleSelectAll() {
            console.log('🔘 Select All checkbox changed:', this.checked);
            console.log('📍 Select All event triggered at:', new Date().toISOString());
            console.log('📍 Call stack:', new Error().stack);
            
            // Get fresh references to all checkboxes
            const currentItemCheckboxes = document.querySelectorAll('.cart-item-checkbox');
            console.log('🔍 DEBUG: Select All Action');
            console.log('  - Target state:', this.checked);
            console.log('  - Checkboxes found:', currentItemCheckboxes.length);
            
            // Debug: Show all checkboxes in DOM
            const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
            console.log('  - All checkboxes in DOM:', allCheckboxes.length);
            allCheckboxes.forEach((checkbox, index) => {
                console.log(`  📋 DOM Checkbox ${index + 1}:`, {
                    class: checkbox.className,
                    id: checkbox.id,
                    cartKey: checkbox.dataset.cartKey
                });
            });
            
            currentItemCheckboxes.forEach((checkbox, index) => {
                const previousState = checkbox.checked;
                checkbox.checked = this.checked;
                console.log(`  📋 Checkbox ${index + 1}: ${previousState} → ${checkbox.checked}`, {
                    cartKey: checkbox.dataset.cartKey,
                    element: checkbox
                });
            });
            
            console.log('🔄 Calling updateSelectedCount() from Select All handler');
            updateSelectedCount();
        }
    } else {
        console.log('❌ Select All checkbox not found');
    }
    
    // Individual checkbox functionality - use event delegation
    // Remove any existing listeners to prevent duplicates
    document.removeEventListener('change', handleIndividualCheckbox);
    document.addEventListener('change', handleIndividualCheckbox);
    
    function handleIndividualCheckbox(event) {
        if (event.target.classList.contains('cart-item-checkbox')) {
            console.log('☑️ Individual checkbox changed:', event.target.checked);
            console.log('📍 Individual checkbox event triggered at:', new Date().toISOString());
            console.log('📍 Call stack:', new Error().stack);
            console.log('🔍 DEBUG: Individual Checkbox Action');
            console.log('  - Checkbox state:', event.target.checked);
            console.log('  - Cart key:', event.target.dataset.cartKey);
            console.log('  - Element:', event.target);
            
            console.log('🔄 Calling updateSelectedCount() from Individual checkbox handler');
            updateSelectedCount();
        }
    }
    
    // Get initial count of checkboxes
    const initialCheckboxes = document.querySelectorAll('.cart-item-checkbox');
    console.log('📋 Found', initialCheckboxes.length, 'individual checkboxes');
    
    // Restore selection state from sessionStorage (if available)
    const savedSelectionState = sessionStorage.getItem('cart_selection_state');
    if (savedSelectionState) {
        console.log('🔄 Restoring selection state from sessionStorage:', savedSelectionState);
        try {
            const selectedItems = JSON.parse(savedSelectionState);
            selectedItems.forEach(cartKey => {
                const checkbox = document.querySelector(`.cart-item-checkbox[data-cart-key="${cartKey}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    console.log('✅ Restored selection for:', cartKey);
                }
            });
            // Clear the saved state after restoring
            sessionStorage.removeItem('cart_selection_state');
        } catch (error) {
            console.error('❌ Error parsing saved selection state:', error);
            sessionStorage.removeItem('cart_selection_state');
        }
    }
    
    // Initial count update
    console.log('🚀 Initial setup complete, calling updateSelectedCount()');
    console.log('📍 Initialization timestamp:', new Date().toISOString());
    console.log('📍 Current URL:', window.location.href);
    console.log('📍 Page pathname:', window.location.pathname);
    updateSelectedCount();
    
    // Initial footer update for sliding cart
    const cartFooter = document.getElementById('cartFooter');
    if (cartFooter && !window.location.pathname.includes('cart.php')) {
        // Only show footer if we're not on the cart page (i.e., in sliding cart)
        cartFooter.style.display = 'block';
    }
}

// Reset initialization flag for each script execution (when cart content is reloaded)
window.cartSelectionInitialized = false;

// Try to initialize immediately with DOM ready check
console.log('🚀 Attempting immediate initialization...');

// Function to safely initialize when DOM is ready
function safeInitialize() {
    const selectAllCheckbox = document.getElementById('select-all-items');
    const itemCheckboxes = document.querySelectorAll('.cart-item-checkbox');
    
    if (selectAllCheckbox && itemCheckboxes.length > 0) {
        if (!window.cartSelectionInitialized) {
            console.log('✅ DOM elements ready, initializing...');
            initializeCartSelection();
            window.cartSelectionInitialized = true;
            return true; // Success
        } else {
            console.log('🔄 Cart selection already initialized, skipping...');
            return true; // Already initialized
        }
    } else {
        console.log('⏳ DOM elements not ready yet, retrying...');
        return false; // Not ready
    }
}

// Try immediate initialization
if (!safeInitialize()) {
    // If immediate initialization failed, try multiple approaches
    
    // Approach 1: Retry with timeout
    setTimeout(() => {
        if (!safeInitialize()) {
            console.log('🔄 Still not ready, trying again...');
            setTimeout(safeInitialize, 100);
        }
    }, 50);
    
    // Approach 2: DOMContentLoaded backup
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', safeInitialize);
    }
    
    // Approach 3: MutationObserver to detect when cart content is inserted
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if cart elements were added
                        const hasCartElements = node.querySelector && (
                            node.querySelector('#select-all-items') || 
                            node.querySelector('.cart-item-checkbox')
                        );
                        if (hasCartElements) {
                            console.log('🔍 Cart elements detected via MutationObserver');
                            setTimeout(safeInitialize, 10);
                        }
                    }
                });
            }
        });
    });
    
    // Observe the document body for changes
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Stop observing after 5 seconds to prevent memory leaks
    setTimeout(() => {
        observer.disconnect();
        console.log('🛑 MutationObserver disconnected');
    }, 5000);
}

// Debug function to manually check cart selection state
function debugCartSelection() {
    console.log('🔍 === CART SELECTION DEBUG ===');
    console.log('📍 Debug timestamp:', new Date().toISOString());
    console.log('📍 Current URL:', window.location.href);
    
    const selectAllCheckbox = document.getElementById('select-all-items');
    const itemCheckboxes = document.querySelectorAll('.cart-item-checkbox');
    const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
    const selectedCountSpan = document.getElementById('selected-count');
    
    console.log('🔍 Element Status:');
    console.log('  - Select All checkbox:', selectAllCheckbox ? 'FOUND' : 'NOT FOUND');
    console.log('  - Total item checkboxes:', itemCheckboxes.length);
    console.log('  - Checked item checkboxes:', checkedBoxes.length);
    console.log('  - Selected count span:', selectedCountSpan ? 'FOUND' : 'NOT FOUND');
    
    if (selectAllCheckbox) {
        console.log('🔘 Select All checkbox state:', {
            checked: selectAllCheckbox.checked,
            indeterminate: selectAllCheckbox.indeterminate,
            element: selectAllCheckbox
        });
    }
    
    console.log('📋 Individual checkbox states:');
    itemCheckboxes.forEach((checkbox, index) => {
        console.log(`  ${index + 1}. Cart Key: ${checkbox.dataset.cartKey}, Checked: ${checkbox.checked}`, checkbox);
    });
    
    if (selectedCountSpan) {
        console.log('📊 Selected count span text:', selectedCountSpan.textContent);
    }
    
    console.log('🔍 === END DEBUG ===');
}

// Make debug function available globally
window.debugCartSelection = debugCartSelection;

// Function to get selected cart items (for checkout)
function getSelectedCartItems() {
    const selectedItems = [];
    const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
    
    checkedBoxes.forEach(checkbox => {
        const cartKey = checkbox.dataset.cartKey;
        const cartItem = document.querySelector(`[data-cart-key="${cartKey}"]`);
        if (cartItem) {
            selectedItems.push(cartKey);
        }
    });
    
    return selectedItems;
}
</script>