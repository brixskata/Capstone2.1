
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - MikeMadz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #0dcaf0;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 2rem 0;
        }

        .verification-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 0 1rem;
            position: relative;
        }

        .verification-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--bs-secondary), #a91d42);
        }

        .verification-header {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .logo-section {
            margin-bottom: 1rem;
        }

        .logo-section img {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px;
            filter: brightness(0) invert(1);
        }

        .brand-logo {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .brand-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        .verification-content {
            padding: 2.5rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e9ecef;
            transition: all 0.3s ease;
            position: relative;
        }

        .step-dot.active {
            background: var(--bs-secondary);
            transform: scale(1.2);
        }

        .step-dot.completed {
            background: var(--bs-success);
        }

        .step-dot::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 30px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: -1;
        }

        .step-dot:last-child::after {
            display: none;
        }

        .step {
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }

        .step.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .step-title {
            color: var(--bs-secondary);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .step-description {
            color: #6c757d;
            text-align: center;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .email-display {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            margin: 0 auto 2rem auto;
            text-align: center;
            max-width: 300px;
            color: var(--bs-secondary);
            font-size: 1rem;
        }

        .email-display i {
            color: var(--bs-secondary);
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-control {
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            background-color: white;
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1.1rem;
        }

        .otp-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 2rem 0;
        }

        .otp-input {
            width: 50px;
            height: 55px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            outline: none;
            border-color: var(--bs-secondary);
            background: white;
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
            transform: scale(1.05);
        }

        .verification-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .verification-btn:hover {
            background: linear-gradient(135deg, #6d1429 0%, #8f1937 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(127, 23, 52, 0.3);
        }

        .verification-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .message {
            padding: 1rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            display: none;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success {
            background: linear-gradient(135deg, #d1e7dd 0%, #badbcc 100%);
            color: #0f5132;
            border: 1px solid #a3cfbb;
        }

        .error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .countdown {
            color: #6c757d;
            font-size: 0.875rem;
            text-align: center;
            margin: 1rem 0;
            font-weight: 500;
        }

        .resend-btn {
            background: none;
            border: none;
            color: var(--bs-secondary);
            font-weight: 600;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            width: 100%;
        }

        .resend-btn:hover {
            background: rgba(127, 23, 52, 0.1);
            transform: translateY(-1px);
        }

        .success-icon {
            font-size: 4rem;
            color: var(--bs-success);
            margin-bottom: 1.5rem;
            text-align: center;
            animation: successPulse 2s ease infinite;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff40;
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-loading .loading-spinner {
            display: inline-block;
        }

        .back-to-login {
            text-align: center;
            margin-top: 2rem;
        }

        .back-to-login a {
            color: var(--bs-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-to-login a:hover {
            color: #6d1429;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .verification-container {
                margin: 0 0.5rem;
            }
            
            .verification-content {
                padding: 2rem 1.5rem;
            }
            
            .otp-input {
                width: 42px;
                height: 48px;
                font-size: 18px;
            }
            
            .otp-container {
                gap: 8px;
            }

            .step-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-header">
            <div class="logo-section">
                <img src="images/logo.png" alt="MikeMadz Logo" class="logo-img">
                <div class="brand-logo">
                    MikeMadz
                </div>
                <p class="brand-subtitle">Secure Email Verification</p>
            </div>
        </div>
        
        <div class="verification-content">
            <div class="step-indicator">
                <div class="step-dot active" id="dot1"></div>
                <div class="step-dot" id="dot2"></div>
                <div class="step-dot" id="dot3"></div>
            </div>
            
            <div id="message" class="message"></div>
            
            <!-- Step 1: Enter Email -->
            <div id="step1" class="step active">
                <h2 class="step-title">Verify Your Email</h2>
                <p class="step-description">Enter your email address to receive a secure verification code</p>
                
                <form id="emailForm">
                    <div class="form-group">
                        <i class="fas fa-envelope form-icon"></i>
                        <input type="email" id="email" class="form-control" placeholder="Enter your email address" required>
                    </div>
                    <button type="submit" class="verification-btn">
                        <span class="loading-spinner"></span>
                        <span class="btn-text">Send Verification Code</span>
                    </button>
                </form>
            </div>
            
            <!-- Step 2: Enter OTP -->
            <div id="step2" class="step">
                <h2 class="step-title">Enter Verification Code</h2>
                <p class="step-description">We've sent a 6-digit verification code to:</p>
                <div class="email-display">
                    <i class="fas fa-envelope me-2"></i>
                    <strong id="displayEmail"></strong>
                </div>
                
                <div class="otp-container">
                    <input type="text" class="otp-input" maxlength="1" id="otp1" data-index="0">
                    <input type="text" class="otp-input" maxlength="1" id="otp2" data-index="1">
                    <input type="text" class="otp-input" maxlength="1" id="otp3" data-index="2">
                    <input type="text" class="otp-input" maxlength="1" id="otp4" data-index="3">
                    <input type="text" class="otp-input" maxlength="1" id="otp5" data-index="4">
                    <input type="text" class="otp-input" maxlength="1" id="otp6" data-index="5">
                </div>
                
                <button id="verifyBtn" class="verification-btn">
                    <span class="loading-spinner"></span>
                    <span class="btn-text">Verify Code</span>
                </button>
                
                <div class="countdown" id="countdown"></div>
                
                <button class="resend-btn" id="resendBtn" style="display: none;">
                    <i class="fas fa-redo me-2"></i>Resend Verification Code
                </button>
            </div>
            
            <!-- Step 3: Success -->
            <div id="step3" class="step">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="step-title" style="color: var(--bs-success);">Email Verified Successfully!</h2>
                <p class="step-description">Your email has been verified. You can now proceed to login to your account.</p>
                
                <button class="verification-btn" onclick="window.location.href='login.php'">
                    <i class="fas fa-sign-in-alt me-2"></i>Continue to Login
                </button>
            </div>

            <div class="back-to-login">
                <a href="login.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        let currentEmail = '';
        let countdownInterval;
        
        // Email form submission
        document.getElementById('emailForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const submitBtn = this.querySelector('.verification-btn');
            currentEmail = email;
            
            // Show loading state
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            fetch('send_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    document.getElementById('displayEmail').textContent = email;
                    showStep(2);
                    startCountdown(600); // 10 minutes
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                showMessage('An error occurred. Please try again.', 'error');
            });
        });
        
        // Enhanced OTP input handling
        const otpInputs = document.querySelectorAll('.otp-input');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                if (this.value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                // Auto-verify when all inputs are filled
                if (index === otpInputs.length - 1 && this.value.length === 1) {
                    const otp = Array.from(otpInputs).map(input => input.value).join('');
                    if (otp.length === 6) {
                        setTimeout(() => verifyOTP(), 500);
                    }
                }
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    otpInputs[index - 1].focus();
                }
                
                if (e.key === 'ArrowLeft' && index > 0) {
                    otpInputs[index - 1].focus();
                }
                
                if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });
            
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                if (pastedData.length === 6) {
                    otpInputs.forEach((inp, i) => {
                        inp.value = pastedData[i] || '';
                    });
                    setTimeout(() => verifyOTP(), 500);
                }
            });
        });
        
        // Verify OTP function
        function verifyOTP() {
            const otp = Array.from(otpInputs).map(input => input.value).join('');
            const verifyBtn = document.getElementById('verifyBtn');
            
            if (otp.length !== 6) {
                showMessage('Please enter a complete 6-digit code', 'error');
                return;
            }
            
            verifyBtn.classList.add('btn-loading');
            verifyBtn.disabled = true;
            
            fetch('verify_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(currentEmail) + '&otp=' + encodeURIComponent(otp)
            })
            .then(response => response.json())
            .then(data => {
                verifyBtn.classList.remove('btn-loading');
                verifyBtn.disabled = false;
                
                if (data.success) {
                    showStep(3);
                    clearInterval(countdownInterval);
                } else {
                    showMessage(data.message, 'error');
                    // Clear OTP inputs with animation
                    otpInputs.forEach((input, index) => {
                        setTimeout(() => {
                            input.value = '';
                            input.style.transform = 'scale(0.9)';
                            setTimeout(() => {
                                input.style.transform = 'scale(1)';
                            }, 100);
                        }, index * 50);
                    });
                    setTimeout(() => otpInputs[0].focus(), 300);
                }
            })
            .catch(error => {
                verifyBtn.classList.remove('btn-loading');
                verifyBtn.disabled = false;
                showMessage('An error occurred. Please try again.', 'error');
            });
        }
        
        // Manual verify button click
        document.getElementById('verifyBtn').addEventListener('click', verifyOTP);
        
        // Resend OTP
        document.getElementById('resendBtn').addEventListener('click', function() {
            const resendBtn = this;
            resendBtn.disabled = true;
            
            fetch('send_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(currentEmail)
            })
            .then(response => response.json())
            .then(data => {
                resendBtn.disabled = false;
                if (data.success) {
                    showMessage('Verification code resent successfully', 'success');
                    startCountdown(600);
                    this.style.display = 'none';
                } else {
                    showMessage(data.message, 'error');
                }
            });
        });
        
        function showStep(stepNumber) {
            // Update step indicators
            document.querySelectorAll('.step-dot').forEach((dot, index) => {
                dot.classList.remove('active', 'completed');
                if (index + 1 < stepNumber) {
                    dot.classList.add('completed');
                } else if (index + 1 === stepNumber) {
                    dot.classList.add('active');
                }
            });
            
            // Show step with animation
            document.querySelectorAll('.step').forEach(step => {
                step.classList.remove('active');
            });
            
            setTimeout(() => {
                document.getElementById('step' + stepNumber).classList.add('active');
            }, 150);
        }
        
        function showMessage(message, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = message;
            messageDiv.className = 'message ' + type;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        }
        
        function startCountdown(seconds) {
            const countdownDiv = document.getElementById('countdown');
            const resendBtn = document.getElementById('resendBtn');
            
            countdownInterval = setInterval(() => {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                countdownDiv.innerHTML = `<i class="fas fa-clock me-2"></i>Code expires in ${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    countdownDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Code expired';
                    resendBtn.style.display = 'inline-block';
                }
                
                seconds--;
            }, 1000);
        }
        
        // Auto-focus first input when step 2 is shown
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.id === 'step2' && mutation.target.classList.contains('active')) {
                    setTimeout(() => otpInputs[0].focus(), 300);
                }
            });
        });
        
        observer.observe(document.getElementById('step2'), {
            attributes: true,
            attributeFilter: ['class']
        });

        // Check if email is provided in URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const email = urlParams.get('email');
            if (email) {
                document.getElementById('email').value = email;
                // Auto-submit the form
                document.getElementById('emailForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>
