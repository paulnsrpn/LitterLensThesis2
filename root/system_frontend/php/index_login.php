<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Login/Register</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../css/index_login.css">

  <style>
    .error-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 100%;
    }
    .error-message {
      background-color: #ffe5e5;
      color: #b00020;
      border: 1px solid #ffb3b3;
      border-radius: 10px;
      padding: 12px 20px;
      margin: 8px auto;
      max-width: 400px;
      font-family: 'Afacad', sans-serif;
      font-size: 15px;
      font-weight: 500;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(176, 0, 32, 0.1);
      animation: fadeIn 0.4s ease-in-out;
      transition: opacity 0.6s ease, transform 0.6s ease;
    }
    .error-message.hide {
      opacity: 0;
      transform: translateY(-5px);
    }
    .error-message::before {
      content: "⚠️";
      margin-right: 8px;
      font-size: 18px;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-5px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>

<body>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$showRegister = !empty($_SESSION['show_register']);
unset($_SESSION['show_register']);
?>

  <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>

  <div class="login-wrapper">
    <!-- LEFT SIDE -->
    <div class="login-left">
      <img src="../imgs/logo2.png" alt="LitterLens Logo" class="login-logo">
      <p class="tagline">Track. Detect. Protect.</p>
      <h1>Welcome Back,<br>Administrator</h1>
      <p class="subtext">Manage reports and keep our waterways clean</p>
    </div>

    <!-- RIGHT SIDE -->
    <div class="login-right">
      <h2>Admin Login</h2>
      <p class="desc">Access your administrator dashboard</p>

      <?php
      if (!empty($_SESSION['login_errors'])) {
          foreach ($_SESSION['login_errors'] as $e) {
              echo '<div class="error-message">' . htmlspecialchars($e) . '</div>';
          }
          unset($_SESSION['login_errors']);
      }
      ?>

      <form action="/LITTERLENSTHESIS2/root/system_backend/php/system_login.php" method="post" class="login-form">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Log In</button>
      </form>

      <div class="admin-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Administrator Access Only
      </div>

      <footer>© LitterLens.2025. All rights reserved.</footer>
    </div>
  </div>

  <script>
    // Auto-fade-out error messages
    window.addEventListener("load", () => {
      const errors = document.querySelectorAll(".error-message");
      if (errors.length) {
        setTimeout(() => errors.forEach(err => err.classList.add("hide")), 4000);
        setTimeout(() => errors.forEach(err => err.remove()), 4600);
      }
    });
  </script>
</body>
</html>