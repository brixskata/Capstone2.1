<div class="table-card">
  <div class="card-header bg-transparent border-0 p-4">
    <div class="d-flex justify-content-between align-items-center">
      <h5 class="fw-bold mb-0 text-dark">
        <i class="fas fa-list-alt me-2"></i>Order Transactions
      </h5>
      <div class="text-muted small d-flex align-items-center">
        <i class="fas fa-search me-1"></i>
        <div class="position-relative" style="width: 300px;">
          <input type="text" id="orderSearchInput" class="form-control form-control-sm" placeholder="Search orders by ID, customer, items, status..." autocomplete="off">
          <button type="button" class="btn btn-sm position-absolute end-0 top-50 translate-middle-y me-1 search-clear-btn" style="background: none; border: none; color: #6c757d;" onclick="clearSearch()" title="Clear search">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th class="fw-semibold text-dark">Order ID</th>
          <th class="fw-semibold text-dark">Customer</th>
          <th class="fw-semibold text-dark">Contact</th>
          <th class="fw-semibold text-dark">Items</th>
          <th class="fw-semibold text-dark">Total</th>
          <th class="fw-semibold text-dark">Payment</th>
          <th class="fw-semibold text-dark">Reference Number</th>
          <th class="fw-semibold text-dark">Status</th>
          <th class="fw-semibold text-dark">Delivery</th>
          <th class="fw-semibold text-dark">Date</th>
          <th class="fw-semibold text-dark">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="11" class="text-center py-5">
              <div class="empty-state">
                <i class="fas fa-search text-muted mb-3" style="font-size: 4rem;"></i>
                <h4 class="text-muted">No Orders Found</h4>
                <p class="text-muted">There are currently no orders to display.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $order): ?>
           <tr class="order-row" style="transition: all 0.2s ease;">
            <td class="fw-semibold text-dark">
              #<?= $order['id'] ?>
            </td>
            <td>
              <div class="d-flex align-items-center">
                <div class="user-avatar me-2" style="width: 32px; height: 32px; border-radius: 50%; background: #6c757d; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.8rem;">
                  <?= strtoupper(substr($order['username'], 0, 1)) ?>
                </div>
                <span class="fw-semibold text-dark"><?= htmlspecialchars($order['username']) ?></span>
              </div>
            </td>
            <td>
              <div class="contact-info">
                <small class="text-muted"><?= htmlspecialchars($order['email'] ?? '') ?></small>
              </div>
            </td>
            <td>
              <div class="items-preview">
                <small class="text-dark"><?= htmlspecialchars(substr($order['items'], 0, 30)) ?><?= strlen($order['items']) > 30 ? '...' : '' ?></small>
              </div>
            </td>
            <td class="fw-bold text-dark">
              ₱<?= number_format($order['total_amount'], 2) ?>
            </td>
            <td>
              <div class="d-flex flex-column gap-1">
                <?php if ($order['payment_method']): ?>
                  <span class="badge" style="background: #f0f8ff; color: #4a90e2; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                    <?= htmlspecialchars($order['payment_method']) ?>
                  </span>
                  <?php if (strtolower($order['payment_method']) == 'gcash' && $order['payment_proof']): ?>
                    <button class="btn btn-sm" style="background: #e6f3ff; color: #0066cc; border-radius: 15px; padding: 2px 8px; font-size: 0.7rem;" onclick="event.stopPropagation(); viewPaymentProof(<?= $order['id'] ?>, '<?= htmlspecialchars($order['payment_proof']) ?>')">
                      View Proof
                    </button>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge" style="background: #f5f5f5; color: #8b8b8b; border: 1px solid #e0e0e0; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                    Cash On Delivery
                  </span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if (strtolower($order['payment_method']) == 'gcash' && !empty($order['gcash_transaction_id'])): ?>
                <span style="color: #2d5a2d; font-size: 0.7rem; font-family: 'Courier New', monospace; font-weight: bold;">
                  <?= htmlspecialchars($order['gcash_transaction_id']) ?>
                </span>
              <?php else: ?>
                <span class="text-muted" style="font-size: 0.7rem;">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $status = strtolower($order['status']);
                $badgeClass = 'text-dark';
                $badgeStyle = 'background: #f8f9fa;';
                if ($status === 'pending') {
                  $badgeStyle = 'background: #fff3cd; color: #856404;';
                } elseif ($status === 'to ship') {
                  $badgeStyle = 'background: #e3f2fd; color: #1976d2;';
                } elseif ($status === 'ready for pick up') {
                  $badgeStyle = 'background: #fce4ec; color: #c2185b;';
                } elseif ($status === 'out for delivery') {
                  $badgeStyle = 'background: #e8f5e8; color: #388e3c;';
                } elseif ($status === 'cancelled') {
                  $badgeStyle = 'background: #ffebee; color: #d32f2f;';
                } elseif ($status === 'delivered' || $status === 'completed') {
                  $badgeStyle = 'background: #f1f8e9; color: #689f38;';
                }
              ?>
              <span class="badge" style="<?= $badgeStyle ?> border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                <?= htmlspecialchars($order['status']) ?>
              </span>
            </td>
            <td>
              <?php if (!empty($order['delivery_option'])): ?>
                <span class="badge" style="background: #f0f4f8; color: #5a6c7d; border: 1px solid #d1d9e0; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                  <?= htmlspecialchars(ucfirst($order['delivery_option'])) ?>
                </span>
              <?php else: ?>
                <span class="text-muted">N/A</span>
              <?php endif; ?>
            </td>
            <td class="text-muted">
              <?= date('M d, Y', strtotime($order['created_at'])) ?>
              <br><small><?= date('H:i', strtotime($order['created_at'])) ?></small>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <!-- View Details Button -->
                <button type="button" class="action-btn" style="background: #6c757d; color: white;" onclick="event.stopPropagation(); viewOrderDetails(<?= $order['id'] ?>, '<?= addslashes($order['username']) ?>', '<?= addslashes($order['email'] ?? '') ?>', '<?= addslashes($order['phone'] ?? '') ?>', '<?= addslashes($order['address_line'] ?? '') ?>', '<?= addslashes($order['address_line2'] ?? '') ?>', '<?= addslashes($order['city'] ?? '') ?>', '<?= addslashes($order['state'] ?? '') ?>', '<?= addslashes($order['postal_code'] ?? '') ?>', '<?= addslashes($order['country'] ?? '') ?>', '<?= addslashes($order['items']) ?>', '<?= $order['total_amount'] ?>', '<?= addslashes($order['payment_method'] ?? '') ?>', '<?= addslashes($order['payment_proof'] ?? '') ?>', '<?= addslashes($order['gcash_transaction_id'] ?? '') ?>', '<?= addslashes($order['status']) ?>', '<?= addslashes($order['delivery_option'] ?? '') ?>', '<?= $order['created_at'] ?>')">
                  <i class="fas fa-eye me-1"></i>View Details
                </button>
                
                 <?php if ($order['status'] == 'Pending'): ?>
                   <?php if (strtolower($order['delivery_option']) === 'pickup'): ?>
                     <button type="button" class="action-btn btn-process" onclick="event.stopPropagation(); processOrder(<?= $order['id'] ?>, 'Ready for Pick Up')">
                       <i class="fas fa-cog me-1"></i>Process
                     </button>
                  <?php else: ?>
                     <button type="button" class="action-btn btn-process" onclick="event.stopPropagation(); processOrder(<?= $order['id'] ?>, 'To Ship')">
                       <i class="fas fa-cog me-1"></i>Process
                     </button>
                  <?php endif; ?>
                   <button type="button" class="action-btn" style="background: #f5c6cb; color: #721c24;" onclick="event.stopPropagation(); cancelOrder(<?= $order['id'] ?>)">
                     <i class="fas fa-ban me-1"></i>Cancel
                   </button>
                 <?php endif; ?>
                 <?php if ($order['status'] == 'To Ship'): ?>
                   <button type="button" class="action-btn btn-ship" onclick="event.stopPropagation(); shipOrder(<?= $order['id'] ?>)">
                     <i class="fas fa-truck me-1"></i>Ship
                   </button>
                 <?php endif; ?>
                 <?php if ($order['status'] == 'Ready for Pick Up'): ?>
                   <?php if ((isset($_SESSION['usertype_id']) && $_SESSION['usertype_id'] == 1) || (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin')): ?>
                     <button type="button" class="action-btn btn-deliver" onclick="event.stopPropagation(); completePickup(<?= $order['id'] ?>)">
                       <i class="fas fa-check me-1"></i>Complete
                     </button>
                   <?php else: ?>
                     <span class="action-btn btn-waiting" title="Waiting for Super Admin confirmation">
                       <i class="fas fa-clock me-1"></i>Waiting
                     </span>
                   <?php endif; ?>
                 <?php endif; ?>
                <?php if ($order['status'] == 'Out for delivery'): ?>
                  <span class="action-btn btn-waiting" title="Waiting for customer to confirm receipt">
                    <i class="fas fa-clock me-1"></i>Waiting
                  </span>
                <?php endif; ?>
                <?php if ($order['status'] == 'Completed'): ?>
                  <span class="action-btn btn-completed">
                    <i class="fas fa-check-circle me-1"></i>Done
                  </span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
              <?php endforeach; ?>
                          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
