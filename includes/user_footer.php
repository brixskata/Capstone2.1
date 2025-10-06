
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/db.php';

// Fetch categories for footer
$footer_categories = [];
try {
    $stmt = $pdo->query("
        SELECT 
            c.category_id,
            c.category_name,
            COUNT(p.product_id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.category_id = p.category_id AND p.is_archive = 0
        GROUP BY c.category_id, c.category_name
        HAVING product_count > 0
        ORDER BY product_count DESC, c.category_name
        LIMIT 6
    ");
    $footer_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $footer_categories = [];
}
?>

<footer class="footer">
    <div class="container">
        <!-- Main Footer Content -->
        <div class="row g-4 py-5">
            <!-- Brand Section -->
            <div class="col-lg-3 col-md-6">
                <div class="footer-brand mb-4">
                    <h2 class="brand-name">MikeMadz</h2>
                    <div class="brand-tagline">Premium Quality Products</div>
                </div>
                <p class="footer-description">
                    Your trusted online store for quality products and excellent service. We deliver fresh, premium goods right to your doorstep.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/profile.php?id=100080633373415&rdid=aUMAHJjLqc8iyS6F&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1CWfcWaM36#" class="social-link" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://www.instagram.com/_mikemadz/?fbclid=IwY2xjawM7tJJleHRuA2FlbQIxMABicmlkETFNUnlFdHZhb0tQWnhDczRpAR74YXn5w3HVbILSwEbqrBNsAsWVgDSIG4BL0me2649S0H7rix1rIb6-suU8_w_aem_j99nmp4VFY9UePLoWrYFAQ" class="social-link" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://x.com/_mikemadz?fbclid=IwY2xjawM7szVleHRuA2FlbQIxMABicmlkETFFTHNRNExsVkN3b1g3TWpSAR4sjkp4ynBgeF98L9eiaM7HqoEMJ7kjMJIOYx23vFsrSbIgbxpcitbH9C9flA_aem_5ghteVEc9M4UPXDqpM1lTg" class="social-link" aria-label="Twitter" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://www.tiktok.com/@_mikemadz?fbclid=IwY2xjawM7tQ5leHRuA2FlbQIxMABicmlkETFNUnlFdHZhb0tQWnhDczRpAR7nONSLtD8m1pJow_8yumrTfhu0UjsEyQuNOxZKZt3CMSiGnwhAnSVfevi09g_aem_Wgz8xno5jClfgS5QhZpHaA" class="social-link" aria-label="TikTok" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-tiktok"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Shop</h5>
                <ul class="footer-links">
                    <li><a href="product.php">All Products</a></li>
                    <?php if (!empty($footer_categories)): ?>
                        <?php foreach ($footer_categories as $category): ?>
                            <li><a href="product.php?category=<?= urlencode(strtolower($category['category_name'])) ?>">
                                <?= htmlspecialchars(ucfirst($category['category_name'])) ?>
                            </a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback categories if database is empty -->
                        <li><a href="product.php?category=meat">Fresh Meat</a></li>
                        <li><a href="product.php?category=seafood">Seafood</a></li>
                        <li><a href="product.php?category=poultry">Poultry</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Account Links -->
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Account</h5>
                <ul class="footer-links">
                    <li><a href="orders.php">My Orders</a></li>
                    <li><a href="cart.php">Shopping Cart</a></li>
                    <li><a href="favorite.php">Favorites</a></li>
                    <li><a href="notifications.php">Notifications</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Support</h5>
                <ul class="footer-links">
                    <li><a href="#" data-bs-toggle="modal" data-bs-target="#contactModal">Contact Us</a></li>
                    <li><a href="#" data-bs-toggle="modal" data-bs-target="#faqModal">FAQ</a></li>
                    <li><a href="#" data-bs-toggle="modal" data-bs-target="#shippingModal">Shipping Info</a></li>
                    <li><a href="#" data-bs-toggle="modal" data-bs-target="#returnsModal">Returns</a></li>
                </ul>
            </div>

            <!-- Newsletter -->
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Stay Updated</h5>
                <p class="newsletter-text">Get exclusive deals and fresh product updates delivered to your inbox.</p>
                <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                    <div class="input-group">
                        <input type="email" class="form-control newsletter-input" placeholder="Enter your email" required>
                        <button type="submit" class="btn newsletter-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
                <div class="newsletter-features">
                    <div class="feature-item">
                        <i class="fas fa-truck"></i>
                        <span>Free delivery on ₱7,000+</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure payments</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="copyright-text">
                        &copy; <?php echo date('Y'); ?> MikeMadz. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="footer-bottom-links">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#cookiesModal">Cookies</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </button>
</footer>

<style>
.footer {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    color: var(--bs-dark);
    margin-top: auto;
    position: relative;
    overflow: hidden;
}

.footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, var(--bs-secondary) 50%, transparent 100%);
}

.footer-brand {
    position: relative;
}

.brand-name {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
}

.brand-tagline {
    font-size: 0.875rem;
    color: var(--bs-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 1rem;
}

.footer-description {
    font-size: 0.9rem;
    line-height: 1.6;
    color: #6c757d;
    margin-bottom: 2rem;
}

.footer-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--bs-secondary);
    margin-bottom: 1.5rem;
    position: relative;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.footer-title::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 30px;
    height: 2px;
    background: linear-gradient(90deg, var(--bs-secondary), #a91d42);
    border-radius: 1px;
}

.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 0.75rem;
}

.footer-links a {
    color: #6c757d;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
    display: inline-block;
}

.footer-links a:hover {
    color: var(--bs-secondary);
    transform: translateX(5px);
}

.footer-links a::before {
    content: '';
    position: absolute;
    left: -15px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 2px;
    background: var(--bs-secondary);
    transition: width 0.3s ease;
}

.footer-links a:hover::before {
    width: 10px;
}

.social-links {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.social-link {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: var(--bs-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    font-size: 1.1rem;
}

.social-link:hover {
    background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(127, 23, 52, 0.25);
}

.newsletter-form {
    margin-bottom: 1.5rem;
}

.newsletter-input {
    border: 2px solid #e9ecef;
    border-radius: 12px 0 0 12px;
    padding: 0.75rem 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.newsletter-input:focus {
    border-color: var(--bs-secondary);
    box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
}

.newsletter-btn {
    background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
    border: none;
    color: white;
    border-radius: 0 12px 12px 0;
    padding: 0.75rem 1.25rem;
    transition: all 0.3s ease;
    font-weight: 600;
}

.newsletter-btn:hover {
    background: linear-gradient(135deg, #6d1429 0%, #8f1937 100%);
    transform: scale(1.02);
}

.newsletter-text {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.newsletter-features {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
}

.feature-item i {
    color: var(--bs-secondary);
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

.footer-bottom {
    border-top: 1px solid #e9ecef;
    padding: 2rem 0 1.5rem;
    margin-top: 2rem;
}

.copyright-text {
    margin: 0;
    color: #6c757d;
    font-weight: 500;
    font-size: 0.9rem;
}

.footer-bottom-links {
    display: flex;
    justify-content: flex-end;
    gap: 2rem;
}

.footer-bottom-links a {
    color: #6c757d;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.footer-bottom-links a:hover {
    color: var(--bs-secondary);
}

.back-to-top {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
}

.back-to-top:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(127, 23, 52, 0.4);
}

/* Responsive Design */
@media (max-width: 768px) {
    .footer .container {
        padding: 0 1rem;
    }
    
    .brand-name {
        font-size: 1.75rem;
    }
    
    .footer-bottom-links {
        justify-content: center;
        margin-top: 1rem;
        gap: 1.5rem;
    }
    
    .back-to-top {
        bottom: 1.5rem;
        right: 1.5rem;
        width: 45px;
        height: 45px;
    }
    
    .social-links {
        justify-content: center;
        margin-top: 1rem;
    }
    
    .newsletter-features {
        align-items: center;
    }
}

@media (max-width: 576px) {
    .footer-bottom-links {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .copyright-text {
        text-align: center;
        margin-bottom: 1rem;
    }
}

/* Animation for newsletter success */
@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.newsletter-success {
    animation: successPulse 0.6s ease-out;
}
</style>

<script>
// Newsletter form handler
function handleNewsletter(event) {
    event.preventDefault();
    const form = event.target;
    const input = form.querySelector('.newsletter-input');
    const btn = form.querySelector('.newsletter-btn');
    
    // Add loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.add('newsletter-success');
        input.value = '';
        
        // Reset after 2 seconds
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            btn.disabled = false;
            btn.classList.remove('newsletter-success');
        }, 2000);
    }, 1000);
}

// Back to top functionality
document.addEventListener('DOMContentLoaded', function() {
    const backToTop = document.getElementById('backToTop');
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    });
    
    backToTop.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});

// Smooth scroll for footer links
document.querySelectorAll('.footer-links a[href^="#"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>
