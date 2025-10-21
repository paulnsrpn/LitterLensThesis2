<?php 
include 'php/config.php';

 ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Login/Register</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="css/login_reg.css">

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

  <a href="main.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>

  <div class="container <?php echo $showRegister ? 'active' : ''; ?>" id="container">

    <div class="side-panel">
      <div class="line1">
        <img src="imgs/logo2.png" alt="litterlens logo">
        <p>Track. Detect. Protect.</p>
      </div>
      <p class="line2" id="side-heading">Good Day, Welcome!</p>
      <p id="side-text">Don't have an account yet?</p>
      <button id="toggleBtn">Register</button>
    </div>

    <!-- Login -->
    <div class="form-panel login-panel">
      <h2>Log In</h2>
      <p>Access your dashboard to monitor and manage litter data efficiently.</p>
  
      <?php
      if (!empty($_SESSION['login_errors'])) {
          foreach ($_SESSION['login_errors'] as $e) {
              echo '<div class="error-message">' . htmlspecialchars($e) . '</div>';
          }
          unset($_SESSION['login_errors']);
      }
      ?>

      <form action="php/login.php" method="post" class="inputs">
        <input type="text" name="username" placeholder="Username or Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <a href="#">Forgot Password?</a>
        <button type="submit">Log In</button>
      </form>
    </div>

    <!-- Register -->
    <div class="form-panel register-panel">
      <h2>Register</h2>
      <p>Create your account to start tracking litter and making an impact.</p>

      <?php
      if (!empty($_SESSION['register_errors'])) {
          echo '<div class="error-container">';
          foreach ($_SESSION['register_errors'] as $e) {
              echo '<div class="error-message">' . htmlspecialchars($e) . '</div>';
          }
          echo '</div>';
          unset($_SESSION['register_errors']);
      }
      ?>

      <form action="php/register.php" method="post" class="inputs">
        <input type="text" name="fullname" placeholder="Full Name" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit">Register</button>
      </form>
    </div>
  </div>

<script>
  const container = document.getElementById("container");
  const toggleBtn = document.getElementById("toggleBtn");
  const sideHeading = document.getElementById("side-heading");
  const sideText = document.getElementById("side-text");
  let isLogin = true;

  toggleBtn.addEventListener("click", () => {
    container.classList.toggle("active");
    if (isLogin) {
      sideHeading.textContent = "Welcome Back, Admin!";
      sideText.textContent = "Already have an account?";
      toggleBtn.textContent = "Log In";
    } else {
      sideHeading.textContent = "Good Day, Welcome!";
      sideText.textContent = "Don't have an account yet?";
      toggleBtn.textContent = "Register";
    }
    isLogin = !isLogin;
  });

  // Fade-in animation on page load
  window.addEventListener("load", () => {
    document.body.classList.add("fade-in");

    // Auto-fade-out error messages
    const errors = document.querySelectorAll(".error-message");
    if (errors.length) {
      setTimeout(() => {
        errors.forEach(err => err.classList.add("hide"));
      }, 4000); // hide after 4s
      setTimeout(() => {
        errors.forEach(err => err.remove());
      }, 4600);
    }
  });

  // Sync correct side text if PHP shows register panel
  if (<?php echo $showRegister ? 'true' : 'false'; ?>) {
    isLogin = false;
    sideHeading.textContent = "Welcome Back, Admin!";
    sideText.textContent = "Already have an account?";
    toggleBtn.textContent = "Log In";
  }
</script>
</body>
</html>
