<?php
include '../includes/db.php';
include_once '../includes/batch_manager.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Initialize batch manager
$batchManager = new BatchManager($pdo);

$batch_id = (int)($_GET['batch_id'] ?? 0);

if (!$batch_id) {
    echo '<div class="alert alert-danger">Invalid batch ID</div>';
    exit;
}

// Get batch details
$batch = $batchManager->getBatchDetails($batch_id);
if (!$batch) {
    echo '<div class="alert alert-danger">Batch not found</div>';
    exit;
}

// Get batch movements
$movements = $batchManager->getBatchMovements($batch_id);
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="fw-bold mb-3">Batch Information</h6>
        <table class="table table-sm">
            <tr>
                <td class="fw-semibold">Batch Number:</td>
                <td><?= htmlspecialchars($batch['batch_number']) ?></td>
            </tr>
            <tr>
                <td class="fw-semibold">Product:</td>
                <td><?= htmlspecialchars($batch['product_name']) ?></td>
            </tr>
            <tr>
                <td class="fw-semibold">Supplier:</td>
                <td><?= htmlspecialchars($batch['supplier_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <td class="fw-semibold">Quantity Received:</td>
                <td><?= number_format((float)$batch['quantity_received'], 1) ?> <?= htmlspecialchars($batch['uom_name']) ?></td>
            </tr>
            <tr>
                <td class="fw-semibold">Quantity Remaining:</td>
                <td>
                    <span class="fw-bold"><?= number_format((float)$batch['quantity_remaining'], 1) ?> <?= htmlspecialchars($batch['uom_name']) ?></span>
                    <div class="progress mt-1" style="height: 8px;">
                        <?php 
                        $percentage = ((float)$batch['quantity_remaining'] / (float)$batch['quantity_received']) * 100;
                        $bar_class = $percentage > 50 ? 'bg-success' : ($percentage > 20 ? 'bg-warning' : 'bg-danger');
                        ?>
                        <div class="progress-bar <?= $bar_class ?>" style="width: <?= $percentage ?>%"></div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="fw-semibold">Unit Cost:</td>
                <td><?= $batch['unit_cost'] ? '₱' . number_format($batch['unit_cost'], 2) : 'N/A' ?></td>
            </tr>
            <tr>
                <td class="fw-semibold">Received Date:</td>
                <td><?= date('M d, Y', strtotime($batch['received_date'])) ?></td>
            </tr>
            <tr>
                <td class="fw-semibold">Expiration Date:</td>
                <td>
                    <?php if ($batch['expiration_date']): ?>
                        <?php 
                        $days_until_expiry = (strtotime($batch['expiration_date']) - time()) / (60 * 60 * 24);
                        if ($days_until_expiry < 0) {
                            echo '<span class="badge bg-danger">Expired ' . abs(round($days_until_expiry)) . ' days ago</span><br>';
                            echo '<small class="text-muted">' . date('M d, Y', strtotime($batch['expiration_date'])) . '</small>';
                        } elseif ($days_until_expiry <= 7) {
                            echo '<span class="badge bg-warning">Expires in ' . round($days_until_expiry) . ' days</span><br>';
                            echo '<small class="text-muted">' . date('M d, Y', strtotime($batch['expiration_date'])) . '</small>';
                        } else {
                            echo '<span class="badge bg-success">Valid</span><br>';
                            echo '<small class="text-muted">' . date('M d, Y', strtotime($batch['expiration_date'])) . '</small>';
                        }
                        ?>
                    <?php else: ?>
                        <span class="text-muted">No expiration date</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="fw-semibold">Status:</td>
                <td>
                    <?php if ($batch['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="fw-semibold">Reference:</td>
                <td>
                    <?= ucfirst($batch['reference_type']) ?>
                    <?php if ($batch['reference_id']): ?>
                        (ID: <?= $batch['reference_id'] ?>)
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($batch['notes']): ?>
            <tr>
                <td class="fw-semibold">Notes:</td>
                <td><?= htmlspecialchars($batch['notes']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="fw-bold mb-3">Batch Movements</h6>
        <?php if (empty($movements)): ?>
            <div class="text-center text-muted py-3">
                <i class="fa fa-info-circle fa-2x mb-2"></i><br>
                No movements recorded for this batch
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td>
                                    <small><?= date('M d, Y', strtotime($movement['created_at'])) ?></small><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($movement['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'sale' => 'success',
                                        'adjustment' => 'warning',
                                        'waste' => 'danger',
                                        'transfer' => 'info'
                                    ];
                                    $color = $type_colors[$movement['movement_type']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($movement['movement_type']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= number_format((float)$movement['quantity'], 1) ?></span>
                                </td>
                                <td>
                                    <?php if ($movement['reference_type'] && $movement['reference_id']): ?>
                                        <small><?= ucfirst($movement['reference_type']) ?> #<?= $movement['reference_id'] ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($batch['notes']): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6 class="fw-bold mb-2">Notes</h6>
        <div class="alert alert-light">
            <?= nl2br(htmlspecialchars($batch['notes'])) ?>
        </div>
    </div>
</div>
<?php endif; ?>




