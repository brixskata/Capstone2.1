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
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body { font-family: 'Poppins', 'Arial', sans-serif; }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-950 to-gray-900">
  <div class="w-full max-w-md mx-auto rounded-2xl shadow-2xl bg-gradient-to-br from-cyan-700 to-blue-800 p-0 overflow-hidden">
    <div class="flex flex-col items-center p-8 border-b border-blue-900">
      <span class="text-3xl font-extrabold tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-400 to-pink-400 drop-shadow-lg">MikeMadz</span>
      <span class="mt-2 text-xs text-cyan-200 tracking-widest uppercase">Admin Login</span>
    </div>
    <div class="p-8">
      <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
        <div class="mb-6 bg-pink-100 text-pink-800 px-4 py-3 rounded-lg text-center text-sm font-semibold">
          <i class="fa fa-exclamation-triangle mr-2"></i>Incorrect username or password.
        </div>
      <?php endif; ?>
      <form action="login_process.php" method="POST" class="space-y-6">
        <div>
          <label for="username" class="block text-cyan-100 font-bold mb-2">Username</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-cyan-400"><i class="fas fa-user"></i></span>
            <input type="text" name="username" id="username" placeholder="Username" required class="pl-10 pr-4 py-2 w-full rounded-lg bg-gray-800 text-cyan-100 focus:outline-none focus:ring-2 focus:ring-cyan-400 placeholder-cyan-400">
          </div>
        </div>
        <div>
          <label for="password" class="block text-cyan-100 font-bold mb-2">Password</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-cyan-400"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" placeholder="Password" required class="pl-10 pr-10 py-2 w-full rounded-lg bg-gray-800 text-cyan-100 focus:outline-none focus:ring-2 focus:ring-cyan-400 placeholder-cyan-400">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-cyan-400 cursor-pointer" id="togglePassword"><i class="fas fa-eye"></i></span>
          </div>
        </div>
        <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white font-bold px-6 py-3 shadow hover:from-blue-500 hover:to-cyan-500 transition">Login</button>
      </form>
    </div>
  </div>
  <script>
    const toggle = document.getElementById("togglePassword");
    const password = document.getElementById("password");
    toggle.addEventListener("click", () => {
      const type = password.getAttribute("type") === "password" ? "text" : "password";
      password.setAttribute("type", type);
      toggle.querySelector('i').classList.toggle("fa-eye-slash");
    });
  </script>
</body>
</html>
