<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login - MikeMadz</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 via-white to-gray-100">
  <div class="w-full max-w-md mx-auto rounded-2xl shadow-2xl bg-white p-0 overflow-hidden border border-gray-200">
    <div class="flex flex-col items-center p-8 border-b border-gray-200 bg-gradient-to-r from-red-800 to-red-900">
      <span class="text-3xl font-extrabold tracking-widest text-white drop-shadow-lg">MikeMadz</span>
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
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-red-600"><i class="fas fa-user"></i></span>
            <input type="text" name="username" id="username" placeholder="Username" required class="pl-10 pr-4 py-3 w-full rounded-lg border-2 border-gray-200 text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 placeholder-gray-400 transition-all">
          </div>
        </div>
        <div>
          <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-red-600"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" placeholder="Password" required class="pl-10 pr-10 py-3 w-full rounded-lg border-2 border-gray-200 text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 placeholder-gray-400 transition-all">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-red-600 cursor-pointer hover:text-red-800 transition-colors" id="togglePassword"><i class="fas fa-eye"></i></span>
          </div>
        </div>
        <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-red-700 to-red-800 text-white font-bold px-6 py-3 shadow-lg hover:from-red-800 hover:to-red-900 hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
          <i class="fas fa-sign-in-alt mr-2"></i>Login
        </button>
      </form>
      
      <div class="mt-6 text-center">
        <a href="../login.php" class="text-sm text-gray-600 hover:text-red-600 transition-colors">
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
        this.parentElement.classList.add('ring-2', 'ring-red-500');
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.classList.remove('ring-2', 'ring-red-500');
      });
    });
  </script>
</body>
</html>
