
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Include DB connection
include_once 'includes/db.php';

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Prepare cart items and total
$cart_items = [];
$cart_total = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $cart_item) {
        $sql = "SELECT * FROM products WHERE id = :product_id AND is_archived = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $quantity = $cart_item['quantity'] ?? 1;
            $item_total = $product['price'] * $quantity;
            $cart_total += $item_total;

            $cart_items[] = [
                'product' => [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image1' => $product['image1'],
                ],
                'quantity' => $quantity,
                'total' => $item_total
            ];
        }
    }
}
?>

<div class="cart-body">
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart-state">
            <i class="fas fa-shopping-cart"></i>
            <h5>Your cart is empty</h5>
            <p>Start adding products to your cart</p>
            <a href="product.php" class="btn btn-primary btn-sm">Shop Now</a>
        </div>
    <?php else: ?>
        <div class="cart-items">
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item" data-product-id="<?= $item['product']['id'] ?>">
                <div class="item-image">
                    <img src="admin/<?= htmlspecialchars($item['product']['image1']) ?>" alt="<?= htmlspecialchars($item['product']['name']) ?>">
                </div>
                <div class="item-details">
                    <h6 class="item-name"><?= htmlspecialchars($item['product']['name']) ?></h6>
                    <div class="item-price">₱<?= number_format($item['product']['price'], 2) ?></div>
                </div>
                <div class="item-controls">
                    <div class="quantity-controls">
                        <button class="qty-btn decrease-cart" data-product-id="<?= $item['product']['id'] ?>">-</button>
                        <span class="cart-item-quantity"><?= $item['quantity'] ?></span>
                        <button class="qty-btn increase-cart" data-product-id="<?= $item['product']['id'] ?>">+</button>
                    </div>
                    <div class="item-total-price">
                        <span class="cart-item-total">₱<?= number_format($item['total'], 2) ?></span>
                    </div>
                    <button class="remove-btn remove-cart-item" data-product-id="<?= $item['product']['id'] ?>">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($cart_items)): ?>
    <div class="cart-footer p-3 border-top bg-light">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="fw-bold">Total:</span>
            <span class="fw-bold text-primary cart-total">₱<?= number_format($cart_total, 2) ?></span>
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
<?php endif; ?>
