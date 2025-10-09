<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<?php
$page_title = 'Admin Login - MikeMadz';
$page_description = 'Admin login page for MikeMadz frozen product store';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/admin_head.php'; ?>
  <title><?= htmlspecialchars($page_title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { 
      font-family: 'Inter', 'Arial', sans-serif; 
    }
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
  </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
  <div class="w-full max-w-md mx-auto rounded-2xl shadow-2xl bg-white p-0 overflow-hidden border border-gray-200">
    <div class="flex flex-col items-center p-8 border-b border-gray-200" style="background-color: #7F1734;">
      <img src="../images/logo.png" alt="MikeMadz Logo" class="h-16 w-auto mb-3" style="filter: brightness(0) invert(1);">
      <span class="text-2xl font-extrabold tracking-widest text-white drop-shadow-lg">MikeMadz</span>
      <span class="mt-2 text-xs text-red-200 tracking-widest uppercase">Admin Login</span>
    </div>
    <div class="p-8">
      <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
        <div class="mb-6 bg-red-50 text-red-800 px-4 py-3 rounded-lg text-center text-sm font-semibold border border-red-200">
          <i class="fa fa-exclamation-triangle mr-2"></i>Incorrect username or password.
        </div>
      <?php endif; ?>
      <form action="login_process.php" method="POST" class="space-y-6">
        <div>
          <label for="username" class="block text-gray-700 font-bold mb-2">Username</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2" style="color: #7F1734;"><i class="fas fa-user"></i></span>
            <input type="text" name="username" id="username" placeholder="Username" required class="pl-10 pr-4 py-3 w-full rounded-lg border-2 border-gray-200 text-gray-700 focus:outline-none placeholder-gray-400 transition-all focus:ring-2 focus:ring-red-800 focus:border-red-800">
          </div>
        </div>
        <div>
          <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2" style="color: #7F1734;"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" placeholder="Password" required class="pl-10 pr-10 py-3 w-full rounded-lg border-2 border-gray-200 text-gray-700 focus:outline-none placeholder-gray-400 transition-all focus:ring-2 focus:ring-red-800 focus:border-red-800">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer transition-colors" style="color: #7F1734;" onmouseover="this.style.color='#a91d42'" onmouseout="this.style.color='#7F1734'" id="togglePassword"><i class="fas fa-eye"></i></span>
          </div>
        </div>
        <button type="submit" class="w-full rounded-lg text-white font-bold px-6 py-3 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200" style="background-color: #7F1734;" onmouseover="this.style.backgroundColor='#a91d42'" onmouseout="this.style.backgroundColor='#7F1734'">
          <i class="fas fa-sign-in-alt mr-2"></i>Login
        </button>
      </form>
      
      <div class="mt-6 text-center">
        <a href="../login.php" class="text-sm text-gray-600 transition-colors hover:text-red-800" onmouseover="this.style.color='#7F1734'" onmouseout="this.style.color='#6b7280'">
          <i class="fas fa-arrow-left mr-1"></i>Back to User Login
        </a>
      </div>
    </div>
  </div>
  
  <script>
    const toggle = document.getElementById("togglePassword");
    const password = document.getElementById("password");
    
    toggle.addEventListener("click", () => {
      const type = password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);
      const icon = toggle.querySelector('i');
      icon.classList.toggle("fa-eye");
      icon.classList.toggle("fa-eye-slash");
    });
    
    // Add focus effects
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        this.style.borderColor = '#7F1734';
        this.style.boxShadow = '0 0 0 2px rgba(127, 23, 52, 0.2)';
      });
      
      input.addEventListener('blur', function() {
        this.style.borderColor = '#d1d5db';
        this.style.boxShadow = 'none';
      });
    });
  </script>
</body>
</html>
