<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LitterLens Register</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/index_register.css" />
</head>
<body>
    <!-- LEFT SECTION -->
    <div class="register-section">
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

        <h1>Register</h1>
        <p>Be part of our mission to keep our waterways clean</p>

        <form action="/LITTERLENSTHESIS2/root/system_backend/php/system_register.php" method="post" class="form-container">
            <input 
                type="text" 
                name="fullname" 
                placeholder="Full Name" 
                autocomplete="name" 
                required
            >

            <input 
                type="email" 
                name="email" 
                placeholder="Email" 
                autocomplete="email" 
                required
            >

            <input 
                type="password" 
                name="password" 
                placeholder="Password (min. 8 characters)" 
                minlength="8" 
                required
            >

            <input 
                type="password" 
                name="confirm_password" 
                placeholder="Confirm Password" 
                minlength="8" 
                required
            >

            <button type="submit">Register</button>
        </form>

        <small>(Admin registration only)</small>
    </div>

    <!-- RIGHT SECTION -->
    <div class="access-section">
        <div class="brand">
            <h2>LitterLens</h2>
            <p>Track. Detect. Protect.</p>
        </div>

        <h2>Administrator Access</h2>
        <p>Existing administrator?</p>
        <button onclick="window.location.href='index_login.php'">Sign In</button>

        <div class="footer-text">
            <p>LitterLens. 2025. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
