<?php
// includes/promo_banner.php
// Reusable promo banner component

// Default promo messages - can be customized per page
$default_promos = [
    "₱1,000 OFF on orders ₱10,000+",
    "Free Nationwide Delivery on ₱7,000+", 
    "Sign up & get 10% OFF your first order"
];

// Allow custom promos to be passed in
$promos = $custom_promos ?? $default_promos;
?>

<!-- Promo Banner -->
<div class="promo-banner">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-center align-items-center gap-3">
            <?php foreach ($promos as $index => $promo): ?>
                <span><?= htmlspecialchars($promo) ?></span>
                <?php if ($index < count($promos) - 1): ?>
                    <span class="d-none d-md-inline">|</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.promo-banner {
    background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
    color: white;
    padding: 0.75rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    text-align: center;
    position: relative;
    z-index: 1000;
}

.promo-banner .container {
    position: relative;
}

.promo-banner span {
    display: inline-block;
    transition: all 0.3s ease;
}

.promo-banner span:hover {
    transform: translateY(-1px);
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .promo-banner {
        font-size: 0.8rem;
        padding: 0.5rem 0;
    }
    
    .promo-banner .d-flex {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    
    .promo-banner .d-none.d-md-inline {
        display: none !important;
    }
}

@media (max-width: 576px) {
    .promo-banner {
        font-size: 0.75rem;
    }
}
</style>
