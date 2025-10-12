<?php
/**
 * Cart Manager - Handles persistent cart storage in database
 * 
 * This class manages cart operations that sync between session and database
 * to ensure cart items persist across login/logout sessions.
 */

class CartManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Load user's cart from database into session
     * 
     * @param int $user_id The user ID
     * @return array The loaded cart items
     */
    public function loadCartFromDatabase($user_id) {
        try {
            // Get user's active cart
            $cart_stmt = $this->pdo->prepare("
                SELECT cart_id FROM cart 
                WHERE user_id = ? AND is_active = 1 
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            $cart_stmt->execute([$user_id]);
            $cart = $cart_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cart) {
                return [];
            }
            
            // Get cart items (only for non-archived products)
            $items_stmt = $this->pdo->prepare("
                SELECT ci.product_id, ci.brand_id, ci.batch_id, ci.quantity, ci.unit, ci.unit_price
                FROM cart_items ci
                INNER JOIN products p ON ci.product_id = p.product_id
                WHERE ci.cart_id = ? AND ci.user_id = ? AND p.is_archive = 0
                ORDER BY ci.created_at ASC
            ");
            $items_stmt->execute([$cart['cart_id'], $user_id]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert to session cart format
            $session_cart = [];
            foreach ($items as $item) {
                $cart_key = $this->generateCartKey(
                    $item['product_id'], 
                    $item['unit'], 
                    $item['brand_id'],
                    $item['batch_id']
                );
                
                $session_cart[$cart_key] = [
                    'product_id' => intval($item['product_id']),
                    'brand_id' => $item['brand_id'] ? strval($item['brand_id']) : null,
                    'batch_id' => $item['batch_id'] ? strval($item['batch_id']) : null,
                    'quantity' => floatval($item['quantity']),
                    'unit' => $item['unit']
                    // Note: unit_price is not stored to ensure fresh prices are always used
                ];
            }
            
            return $session_cart;
        } catch (Exception $e) {
            error_log("Error loading cart from database: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Save session cart to database
     * 
     * @param int $user_id The user ID
     * @param array $session_cart The session cart array
     * @return bool Success status
     */
    public function saveCartToDatabase($user_id, $session_cart) {
        if (empty($session_cart)) {
            return $this->clearCartFromDatabase($user_id);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Get or create active cart
            $cart_id = $this->getOrCreateCart($user_id);
            
            // Clear existing cart items
            $clear_stmt = $this->pdo->prepare("
                DELETE FROM cart_items WHERE cart_id = ? AND user_id = ?
            ");
            $clear_stmt->execute([$cart_id, $user_id]);
            
            // Insert new cart items
            $insert_stmt = $this->pdo->prepare("
                INSERT INTO cart_items (cart_id, user_id, product_id, brand_id, batch_id, quantity, unit, unit_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($session_cart as $cart_key => $item) {
                $insert_stmt->execute([
                    $cart_id,
                    $user_id,
                    $item['product_id'],
                    $item['brand_id'],
                    $item['batch_id'] ?? null,
                    $item['quantity'],
                    $item['unit'],
                    0.00 // unit_price is not stored, always fetch fresh prices
                ]);
            }
            
            // Update cart timestamp
            $update_stmt = $this->pdo->prepare("
                UPDATE cart SET updated_at = CURRENT_TIMESTAMP WHERE cart_id = ?
            ");
            $update_stmt->execute([$cart_id]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error saving cart to database: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Merge session cart with database cart
     * 
     * @param int $user_id The user ID
     * @param array $session_cart The session cart array
     * @return array The merged cart
     */
    public function mergeCarts($user_id, $session_cart) {
        $db_cart = $this->loadCartFromDatabase($user_id);
        
        // If session cart is empty, return database cart
        if (empty($session_cart)) {
            return $db_cart;
        }
        
        // If database cart is empty, return session cart
        if (empty($db_cart)) {
            return $session_cart;
        }
        
        // Start with database cart
        $merged_cart = $db_cart;
        
        // Merge session cart items
        foreach ($session_cart as $cart_key => $session_item) {
            // Validate session item data
            if (!isset($session_item['product_id']) || !isset($session_item['quantity'])) {
                continue; // Skip invalid items
            }
            
            // Safety check: reset corrupted quantities
            if ($session_item['quantity'] > 1000) {
                $session_item['quantity'] = 1;
            }
            
            if (isset($merged_cart[$cart_key])) {
                // Same product+brand+unit: use the larger quantity (avoid double counting)
                $merged_cart[$cart_key]['quantity'] = max($merged_cart[$cart_key]['quantity'], $session_item['quantity']);
            } else {
                // Different product/brand/unit: add as new item
                $merged_cart[$cart_key] = $session_item;
            }
        }
        
        return $merged_cart;
    }
    
    /**
     * Clear cart from database
     * 
     * @param int $user_id The user ID
     * @return bool Success status
     */
    public function clearCartFromDatabase($user_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Get user's active cart
            $cart_stmt = $this->pdo->prepare("
                SELECT cart_id FROM cart 
                WHERE user_id = ? AND is_active = 1
            ");
            $cart_stmt->execute([$user_id]);
            $cart = $cart_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cart) {
                // Delete cart items
                $items_stmt = $this->pdo->prepare("
                    DELETE FROM cart_items WHERE cart_id = ? AND user_id = ?
                ");
                $items_stmt->execute([$cart['cart_id'], $user_id]);
                
                // Deactivate cart
                $deactivate_stmt = $this->pdo->prepare("
                    UPDATE cart SET is_active = 0 WHERE cart_id = ?
                ");
                $deactivate_stmt->execute([$cart['cart_id']]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error clearing cart from database: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get or create active cart for user
     * 
     * @param int $user_id The user ID
     * @return int The cart ID
     */
    private function getOrCreateCart($user_id) {
        // Try to get existing active cart
        $stmt = $this->pdo->prepare("
            SELECT cart_id FROM cart 
            WHERE user_id = ? AND is_active = 1 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cart) {
            return $cart['cart_id'];
        }
        
        // Create new cart
        $insert_stmt = $this->pdo->prepare("
            INSERT INTO cart (user_id, is_active) VALUES (?, 1)
        ");
        $insert_stmt->execute([$user_id]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Generate cart key for session storage
     * 
     * @param int $product_id Product ID
     * @param string $unit Unit (kilo, piece, box)
     * @param int|null $brand_id Brand ID
     * @param int|null $batch_id Batch ID
     * @return string Cart key
     */
    private function generateCartKey($product_id, $unit, $brand_id = null, $batch_id = null) {
        $key = $product_id . '_' . $unit;
        if ($brand_id) {
            $key .= '_brand_' . $brand_id;
        }
        if ($batch_id) {
            $key .= '_batch_' . $batch_id;
        }
        return $key;
    }
    
    /**
     * Sync single cart item to database
     * 
     * @param int $user_id The user ID
     * @param string $cart_key The cart key
     * @param array $item The cart item data
     * @return bool Success status
     */
    public function syncCartItem($user_id, $cart_key, $item) {
        try {
            $this->pdo->beginTransaction();
            
            // Get or create active cart
            $cart_id = $this->getOrCreateCart($user_id);
            
            // Check if item exists
            $check_stmt = $this->pdo->prepare("
                SELECT cartitem_id FROM cart_items 
                WHERE cart_id = ? AND user_id = ? AND product_id = ? AND brand_id = ? AND batch_id = ? AND unit = ?
            ");
            $check_stmt->execute([
                $cart_id, 
                $user_id, 
                $item['product_id'], 
                $item['brand_id'], 
                $item['batch_id'] ?? null,
                $item['unit']
            ]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing item
                $update_stmt = $this->pdo->prepare("
                    UPDATE cart_items 
                    SET quantity = ?, unit_price = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE cartitem_id = ?
                ");
                $update_stmt->execute([
                    $item['quantity'], 
                    0.00, // unit_price is not stored, always fetch fresh prices
                    $existing['cartitem_id']
                ]);
            } else {
                // Insert new item
                $insert_stmt = $this->pdo->prepare("
                    INSERT INTO cart_items (cart_id, user_id, product_id, brand_id, batch_id, quantity, unit, unit_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([
                    $cart_id,
                    $user_id,
                    $item['product_id'],
                    $item['brand_id'],
                    $item['batch_id'] ?? null,
                    $item['quantity'],
                    $item['unit'],
                    0.00 // unit_price is not stored, always fetch fresh prices
                ]);
            }
            
            // Update cart timestamp
            $update_cart_stmt = $this->pdo->prepare("
                UPDATE cart SET updated_at = CURRENT_TIMESTAMP WHERE cart_id = ?
            ");
            $update_cart_stmt->execute([$cart_id]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error syncing cart item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove cart item from database
     * 
     * @param int $user_id The user ID
     * @param int $product_id Product ID
     * @param string $unit Unit
     * @param int|null $brand_id Brand ID
     * @return bool Success status
     */
    public function removeCartItem($user_id, $product_id, $unit, $brand_id = null, $batch_id = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get user's active cart
            $cart_stmt = $this->pdo->prepare("
                SELECT cart_id FROM cart 
                WHERE user_id = ? AND is_active = 1
            ");
            $cart_stmt->execute([$user_id]);
            $cart = $cart_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cart) {
                // Build WHERE clause based on provided parameters
                $where_conditions = ["cart_id = ?", "user_id = ?", "product_id = ?", "unit = ?"];
                $params = [$cart['cart_id'], $user_id, $product_id, $unit];
                
                if ($brand_id !== null) {
                    $where_conditions[] = "brand_id = ?";
                    $params[] = $brand_id;
                } else {
                    $where_conditions[] = "brand_id IS NULL";
                }
                
                if ($batch_id !== null) {
                    $where_conditions[] = "batch_id = ?";
                    $params[] = $batch_id;
                } else {
                    $where_conditions[] = "batch_id IS NULL";
                }
                
                $delete_stmt = $this->pdo->prepare("
                    DELETE FROM cart_items 
                    WHERE " . implode(" AND ", $where_conditions)
                );
                $delete_stmt->execute($params);
                
                // Update cart timestamp
                $update_stmt = $this->pdo->prepare("
                    UPDATE cart SET updated_at = CURRENT_TIMESTAMP WHERE cart_id = ?
                ");
                $update_stmt->execute([$cart['cart_id']]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error removing cart item: " . $e->getMessage());
            return false;
        }
    }
}
