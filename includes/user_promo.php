 
<style>
   /* Promo Banner with Marquee Effect */
       .promo-banner {
           background: var(--bs-secondary);
           color: white;
           padding: 0.75rem 0;
           font-weight: 500;
           font-size: 0.9rem;
           overflow: hidden;
           position: relative;
       }

       .promo-marquee {
           display: flex;
           animation: marquee-scroll 20s linear infinite;
           white-space: nowrap;
           gap: 3rem;
       }

       .promo-marquee:hover {
           animation-play-state: paused;
       }

       .promo-item {
           display: flex;
           align-items: center;
           flex-shrink: 0;
           font-size: 0.9rem;
       }

       .promo-item i {
           margin-right: 0.5rem;
           color: #ffd700;
       }

       @keyframes marquee-scroll {
           0% {
               transform: translateX(100%);
           }
           100% {
               transform: translateX(-100%);
           }
       }

       @media (max-width: 768px) {
           .promo-marquee {
               gap: 2rem;
               font-size: 0.8rem;
           }
       }

       @media (max-width: 576px) {
           .promo-marquee {
               gap: 1.5rem;
               font-size: 0.75rem;
           }
       }
</style>
 
 
 
 
 
 
 
 
 
 
 
 
 <?php
// Fetch active promo messages from database
$promo_messages = [];
try {
    include_once __DIR__ . '/db.php';
    $stmt = $pdo->query("SELECT * FROM promo_messages WHERE is_active = 1 ORDER BY sort_order ASC, created_at ASC");
    $promo_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback to default messages if database error
    $promo_messages = [
        ['message_text' => '₱1,000 OFF on orders ₱10,000+', 'icon_class' => 'fas fa-gift'],
        ['message_text' => 'Free Nationwide Delivery on ₱7,000+', 'icon_class' => 'fas fa-truck'],
        ['message_text' => 'Sign up & get 10% OFF your first order', 'icon_class' => 'fas fa-user-plus'],
        ['message_text' => 'Premium Quality Products Guaranteed', 'icon_class' => 'fas fa-star'],
        ['message_text' => '90 Minutes Express Delivery', 'icon_class' => 'fas fa-clock']
    ];
}

// Don't display banner if no messages
if (empty($promo_messages)) {
    return;
}
?>

<!-- Promo Banner with Marquee Effect -->
<div class="promo-banner">
    <div class="promo-marquee">
        <!-- First set of messages -->
        <?php foreach ($promo_messages as $message): ?>
            <div class="promo-item">
                <i class="<?= htmlspecialchars($message['icon_class']) ?>"></i><?= htmlspecialchars($message['message_text']) ?>
            </div>
        <?php endforeach; ?>
        
        <!-- Duplicate for seamless loop -->
        <?php foreach ($promo_messages as $message): ?>
            <div class="promo-item">
                <i class="<?= htmlspecialchars($message['icon_class']) ?>"></i><?= htmlspecialchars($message['message_text']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>