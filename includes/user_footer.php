
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
                    <li><a href="favorites.php">Favorites</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Support</h5>
                <ul class="footer-links">
                    <li><a href="https://www.facebook.com/profile.php?id=100080633373415" target="_blank" rel="noopener noreferrer">Contact Us</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms &amp; Condition</a></li>
                </ul>
            </div>

            <!-- Newsletter -->
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Stay Updated</h5>
                <p class="newsletter-text">Get exclusive deals and fresh product updates delivered to your inbox.</p>
                <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                    <div class="input-group">
                        <label for="newsletter-email" class="visually-hidden">Email address for newsletter</label>
                        <input type="email" id="newsletter-email" name="newsletter-email" class="form-control newsletter-input" placeholder="Enter your email" required>
                        <button type="submit" class="btn newsletter-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
                <div class="newsletter-features">
                
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="copyright-text">
                        &copy; <?php echo date('2021'); ?> MikeMadz. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="footer-bottom-links">
                        <a href="mailto:mikemadzstore021@gmail.com">mikemadzstore021@gmail.com</a>
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

<!-- Terms & Condition Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">📜 Terms &amp; Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="terms-content">
                    <p>Welcome to MikeMadz Frozen Product Store! By using our online meat ordering system ("Service"), you agree to the following terms:</p>

                    <h6>1. Ordering &amp; Payments</h6>
                    <p>Orders can be placed through our website/app.</p>
                    <p>Cash on Delivery (COD) is only available for orders up to ₱2,000.00. For higher amounts, payments must be made through [Credit/Debit Card, Bank Transfer, or E-Wallet].</p>
                    <p>A minimum order amount of ₱300.00 applies for COD transactions.</p>
                    <p>All prices are in [PHP] and may change without prior notice.</p>

                    <h6>2. Delivery &amp; Pick-up</h6>
                    <h6>Pickup</h6>
                    <p>Customers can select Pickup and collect their orders directly from our store.</p>
                    <p>Customers may also arrange their own delivery by booking a third-party courier (e.g., Grab, Lalamove) to pick up the order on their behalf.</p>
                    <p>Once the order is released to the customer or their chosen courier, responsibility for the product transfers to the customer.</p>

                    <h6>Delivery</h6>
                    <p>We arrange nationwide delivery through trusted third-party courier partners for customer convenience.</p>
                    <p>Delivery fees, timelines, and conditions will follow the policies of the assigned courier.</p>
                    <p>While we coordinate the shipment, we are not responsible for courier delays, damages, or failed deliveries caused by factors beyond our control (e.g., weather, traffic, incomplete addresses).</p>
                    <p>Customers must provide accurate delivery details to avoid delays or additional charges.</p>

                    <h6>3. Cancellations &amp; Refunds</h6>
                    <p>Customers may cancel their orders only if the order has not yet been shipped or dispatched.</p>
                    <p>Once an order is shipped, it can no longer be canceled.</p>
                    <p>Perishable goods cannot be refunded once delivered, unless proven defective or spoiled upon receipt.</p>
                    <p>The admin reserves the right to cancel or not proceed with an order if the submitted proof of payment (via Bank Transfer or GCash) is not verified or invalid.</p>
                    <p>In case of admin-initiated cancellation, the customer will be notified immediately, and any verified payments already received will be refunded.</p>

                    <h6>4. Product Quality</h6>
                    <p>We guarantee that our meats are fresh and handled in compliance with food safety standards.</p>
                    <p>All products are properly packed and stored in well-insulated styrofoam containers to ensure freshness during handling and delivery.</p>
                    <p>When properly frozen and stored, our meats can maintain quality for an extended period, even up to years, depending on storage conditions.</p>
                    <p>Customers are advised to refrigerate or freeze products immediately upon receipt to maximize shelf life.</p>
                    <p>We are not responsible for spoilage due to improper storage or delayed receipt after successful delivery.</p>

                    <h6>5. Limitation of Liability</h6>
                    <p>We are not liable for any indirect, incidental, or consequential damages, including but not limited to product spoilage caused by delayed receipt, courier delays, or improper storage after delivery.</p>
                    <p>Once an order is handed over to a third-party courier, responsibility for the handling, timeliness, and condition of the shipment lies with the courier and the customer.</p>
                    <p>Our maximum liability, in any case, is limited to the total amount paid for the specific order in question.</p>

                    <hr>

                    <h6>🔒 Privacy Policy</h6>
                    <p>Your privacy is important to us. This Privacy Policy explains how we collect, use, and protect your personal information when you use our online ordering system.</p>

                    <h6>1. Information We Collect</h6>
                    <p>Personal details such as name, delivery address, contact number, and email address.</p>
                    <p>Payment details, processed securely via trusted third-party payment providers (we do not store your full payment information).</p>
                    <p>Order history and preferences to help us improve your experience.</p>

                    <h6>2. How We Use Your Information</h6>
                    <p>To process and deliver your orders.</p>
                    <p>To contact you regarding your order status or, with your consent, send promotions and updates.</p>
                    <p>To improve our services, website/app features, and customer experience.</p>

                    <h6>3. Data Sharing</h6>
                    <p>We may share limited personal information (such as name, delivery address, and contact number) with third-party couriers or service providers solely for the purpose of fulfilling your order.</p>
                    <p>These partners are only authorized to use your data to complete delivery and are required to keep it secure.</p>
                    <p>We do not sell, trade, or rent your personal information to unrelated third parties.</p>

                    <h6>4. Data Protection</h6>
                    <p>We comply with the Philippine Data Privacy Act of 2012 (RA 10173).</p>
                    <p>We use secure servers, encryption, and industry-standard practices to safeguard your information.</p>
                    <p>Access to your personal data is limited to authorized personnel only.</p>

                    <h6>5. Your Rights</h6>
                    <p>You may access and update or correct your personal information in your account.</p>
                    <p>You may opt out of marketing communications anytime by following the unsubscribe instructions or contacting us directly.</p>
                    <p>Please note: Account deletion is not currently available in our system, but we comply with the Philippine Data Privacy Act of 2012 regarding the proper handling and retention of your data.</p>

                    <hr>

                    <h6>📱 End-User License Agreement (EULA)</h6>
                    <p>This End-User License Agreement ("Agreement") is a legal contract between you ("User") and MikeMadz Frozen Product Store ("Company") regarding the use of our online ordering platform and related services ("Service").</p>

                    <h6>1. License Grant</h6>
                    <p>We grant you a limited, non-exclusive, non-transferable license to access and use the Service solely for personal, non-commercial purposes (e.g., browsing, placing orders, and managing your account).</p>

                    <h6>2. Restrictions</h6>
                    <p>You may not copy, modify, distribute, sell, lease, or reverse-engineer any part of the Service.</p>
                    <p>You may not use the Service for any fraudulent, abusive, or unlawful purposes.</p>
                    <p>You must not interfere with or disrupt the Service, servers, or networks connected to it.</p>

                    <h6>3. Ownership</h6>
                    <p>All content, software, trademarks, logos, and intellectual property within the Service remain the sole property of MikeMadz Frozen Product Store.</p>
                    <p>Use of the Service does not grant you ownership rights in any part of it.</p>

                    <h6>4. Payments</h6>
                    <p>By placing an order, you agree to provide accurate payment information through the available payment methods (Cash on Delivery, Bank Transfer, GCash).</p>
                    <p>Orders may be canceled by the admin if proof of payment is invalid, unverified, or fraudulent.</p>

                    <h6>5. Termination</h6>
                    <p>We may suspend or terminate your access immediately if you violate this Agreement or applicable laws.</p>
                    <p>Upon termination, your license to use the Service will end, but you remain responsible for any outstanding payments.</p>

                    <h6>6. Disclaimer of Warranties</h6>
                    <p>The Service is provided “as is” and “as available,” without warranties of any kind.</p>
                    <p>We do not guarantee uninterrupted access, error-free operation, or that the Service will always be secure.</p>

                    <h6>7. Limitation of Liability</h6>
                    <p>To the maximum extent permitted by law, our liability is limited to the amount you paid for the order in question.</p>
                    <p>We are not responsible for indirect damages (e.g., spoilage due to late receipt, third-party courier delays, or technical failures).</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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
