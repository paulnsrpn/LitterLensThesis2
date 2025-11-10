<?php
    // ================================================
    // 📂 FILE HANDLER — Parse Uploaded File Paths
    // ================================================

    $files = [];
    $folderName = '';

    // ✅ Decode the "files" parameter from GET request
    if (isset($_GET['files'])) {
        $files = json_decode($_GET['files'], true);

        // ✅ Extract folder name from the first file path
        if (!empty($files)) {
            $parts = explode('/', $files[0]);
            if (count($parts) > 1) {
                $folderName = $parts[1];
            }
        }
    }
 
    // If not logged in, leave session empty (guest)
    $admin_id = $_SESSION['admin_id'] ?? null;
    $admin_name = $_SESSION['admin_name'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

            <head>
              <!-- ================================================
                  🧭 META & DOCUMENT CONFIGURATION
              ================================================= -->
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <title>Litter Detection Results</title>

              <!-- ================================================
                  🎨 STYLESHEETS
              ================================================= -->
              <link rel="stylesheet" href="../css/index_results.css">
              <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

              <!-- ================================================
                  📄 PDF + MAP LIBRARIES
              ================================================= -->
              <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
              <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
              
              <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

            </head>


<body>
       <!-- ================================================
     🧭 NAVIGATION BAR SECTION (Responsive)
================================================ -->
<nav class="navbar">
  <!-- 🟢 Logo -->
  <a href="index.php" class="logo-link">
    <img src="../imgs/logo.png" alt="LitterLens Logo" class="logo-img">
  </a>

  <!-- 🍔 Hamburger (visible on mobile) -->
  <div class="hamburger" id="hamburger">
    <span></span>
    <span></span>
    <span></span>
  </div>

  <!-- 🟣 Navigation Links (desktop view) -->
  <div class="r-nav">
    <a href="index.php">Home</a>
    <a href="index.php#about-page">About</a>
    <a href="index.php#contact-page">Contacts</a>
    <a href="../php/index_login.php" title="Login">
      <i class="fa-solid fa-user" id="user-icon"></i>
    </a>
  </div>
</nav>

<!-- 📱 MOBILE MENU -->
<div class="mobile-menu" id="mobile-menu">
  <div class="menu-header">
    <div class="menu-info">
      <img src="../imgs/logo.png" class="menu-logo" alt="Logo">
      <span class="menu-title">LitterLens</span>
    </div>
    <button class="menu-close" id="menu-close">&times;</button>
  </div>

  <!-- 📋 Menu Links -->
  <a href="index.php">Home</a>
  <a href="index.php#about-page">About</a>
  <a href="index.php#contact-page">Contacts</a>
  <a href="../php/index_login.php">Login</a>
</div>

<!-- 🌑 Overlay -->
<div class="mobile-overlay" id="mobile-overlay"></div>



              <!-- ================================================
                  📊 DETECTION RESULTS SECTION
              ================================================= -->
              <div class="content-container">
                <div class="r-content">

                  <!-- 🧾 HEADER CONTROLS -->
                  <div class="r-header">
                    <h1>Detection Results</h1>

                    <!-- 🔹 Confidence Threshold -->
                  <select class="threshold-dropdown">
                    <option value="0.05">Confidence Threshold: 10% (Very Sensitive)</option>
                    <option value="0.10">Confidence Threshold: 20% (Balanced Default)</option>
                    <option value="0.20">Confidence Threshold: 40% (Moderate)</option>
                    <option value="0.30" selected>Confidence Threshold: 60% (Strict)</option>
                    <option value="0.40">Confidence Threshold: 80% (Very Strict)</option>
                  </select>

 
                    <!-- 🔹 Label Display Mode -->
                    <select class="label-mode-dropdown">
                      <option value="confidence" selected>Label Display Mode: Draw Confidence</option>
                      <option value="labels">Label Display Mode: Draw Labels</option>
                      <option value="boxes">Label Display Mode: Draw Boxes</option>
                    </select>

                    <!-- 🔹 Opacity Control -->
                    <select id="opacityDropdown" class="label-opacity-dropdown">
                      <option value="0.25">Bounding Box Opacity: 25%</option>
                      <option value="0.50">Bounding Box Opacity: 50%</option>
                      <option value="0.75">Bounding Box Opacity: 75%</option>
                      <option value="1.00" selected>Bounding Box Opacity: 100%</option>
                    </select>
                  </div>

                  <!-- 🧠 RESULT CONTENT -->
                  <div class="r-cards">

                 <!-- 📷 LEFT COLUMN — Image Viewer -->
                  <div class="left-col">
                    <!-- 🖼️ Image Display -->
                    <div class="img-container">

                      <!-- Before/After Switch (inside container) -->
                      <label class="switch">
                        <input type="checkbox" id="beforeAfterSwitch">
                        <span class="slider"></span>
                      </label>

                      <!-- Image -->
                      <img src="" alt="Detected Image" class="main-img">
                      <span id="file-name-display" class="file-name"></span>

                      <!-- Navigation Buttons -->
                      <button class="nav-btn prev-btn">&#10094;</button>
                      <button class="nav-btn next-btn">&#10095;</button>

                      <!-- Zoom Controls -->
                      <div class="zoom-controls">
                        <button id="zoom-out">−</button>
                        <span id="zoom-level">100%</span>
                        <button id="zoom-in">+</button>
                        <button id="zoom-reset" title="Reset Zoom">&#x21bb;</button>
                      </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="r-buttons">
                      <button id="go-back-btn" class="back-button">⬅ Go Back</button>
                      <button id="upload-btn" class="upload-button">📤 Upload to Database</button>
                    </div>
                  </div>


                    <!-- 🧾 RIGHT COLUMN — Summary / Receipt -->
                    <div class="receipt">
                      <div class="receipt-card">
                        <h2>24 Items detected</h2>

                        <!-- Detection Summary Table -->
                        <table>
                          <tr>
                            <th>Classification</th>
                            <th>Count</th>
                          </tr>
                          <tr><td>Item</td><td>0</td></tr>
                          <tr><td>Item2</td><td>0</td></tr>
                          <tr><td>Item3</td><td>0</td></tr>
                          <tr><td>Other</td><td>0</td></tr>
                        </table>

                        <!-- Accuracy Display -->
                        <p class="accuracy">Detection Accuracy: 97%</p>
                        <div class="divider"></div>

                 <!-- 🌍 Geolocation Data -->
                        <div class="location-icon">
                          <div id="coords" style="display: none;">
                            <strong>Latitude:</strong> <span id="lat-value">0.0000</span><br>
                            <strong>Longitude:</strong> <span id="lng-value">0.0000</span><br>
                          </div>

                          <strong>Location:</strong>
                          <a id="location-link" href="#" target="_blank" class="location" style="color: #007bff; text-decoration: underline;">
                            <span id="location-name">Detecting...</span>
                          </a>
                        </div>

                        <!-- Download Report -->
                        <a href="#" class="download" id="download-pdf">Download Report (PDF)</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>






              <!-- 🌊 Creek Selection Toast -->
<div id="creek-toast" class="creek-toast">
  <div class="toast-content">
    <h3>Select Creek or River</h3>
    <p>Please select or type where this litter was detected.</p>
    <select id="creek-select">
      <option value="" disabled selected>Select creek/river...</option>
      <option value="Buli">Buli Creek</option>
      <option value="Pasig River">Pasig River</option>
      <option value="Other">Other (Type below)</option>
    </select>
    <input type="text" id="creek-input" placeholder="Type creek/river name..." style="display:none;">
    <div class="toast-actions">
      <button id="toast-cancel">Cancel</button>
      <button id="toast-confirm">Confirm</button>
    </div>
  </div>
</div>

      <!-- ================================================
          ⚙️ SCRIPT INITIALIZATION
      ================================================= -->

      <!-- 🧩 Pass PHP data to JavaScript -->
      <script>
        const imagePaths = <?php echo json_encode($files); ?>;
        const uploadedFolder = "<?php echo $folderName; ?>";
     
        document.addEventListener("DOMContentLoaded", () => {
          const goBackBtn = document.getElementById("go-back-btn");

          if (goBackBtn) {
            goBackBtn.addEventListener("click", () => {
              const detectionSource = localStorage.getItem("detectionSource");

              // 🟢 If this result came from the Admin side
              if (detectionSource === "admin") {
                // Remove temp data
                localStorage.removeItem("detectionResult");
                localStorage.removeItem("detectionSource");
                // ✅ Redirect back to the Admin Panel
                window.location.href = "http://localhost/LitterLensThesis2/root/system_frontend/php/admin.php";
              } 
              // 🟣 Otherwise (User side)
              else {
                localStorage.removeItem("detectionResult");
                localStorage.removeItem("detectionSource");
                // ✅ Redirect back to User Home
                window.location.href = "index.php";
              }
            });
          }
        });
</script>

<script>
  const currentAdminId = "<?php echo $admin_id ?? ''; ?>";
  const currentAdminName = "<?php echo $admin_name ?? ''; ?>";
</script>


<script>

document.addEventListener("DOMContentLoaded", () => {
  const hamburger = document.getElementById("hamburger");
  const mobileMenu = document.getElementById("mobile-menu");
  const overlay = document.getElementById("mobile-overlay");
  const closeBtn = document.getElementById("menu-close");

  if (hamburger && mobileMenu && overlay && closeBtn) {
    // 🍔 Open menu
    hamburger.addEventListener("click", () => {
      hamburger.classList.toggle("active");
      mobileMenu.classList.toggle("active");
      overlay.classList.toggle("active");
    });

    // ❌ Close when pressing the X
    closeBtn.addEventListener("click", () => {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    });

    // 🌑 Close when clicking the overlay
    overlay.addEventListener("click", () => {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    });

    // 📱 Auto-close when clicking a link
    mobileMenu.querySelectorAll("a").forEach(link => {
      link.addEventListener("click", () => {
        hamburger.classList.remove("active");
        mobileMenu.classList.remove("active");
        overlay.classList.remove("active");
      });
    });
  }
});





// ================================================
// 🧠 AUTO-CLOSE MOBILE MENU ON WINDOW RESIZE
// ================================================
window.addEventListener("resize", () => {
  const hamburger = document.getElementById("hamburger");
  const mobileMenu = document.getElementById("mobile-menu");
  const overlay = document.getElementById("mobile-overlay");

  // If window width is greater than 992px (desktop view)
  if (window.innerWidth > 992) {
    if (hamburger && mobileMenu && overlay) {
      hamburger.classList.remove("active");
      mobileMenu.classList.remove("active");
      overlay.classList.remove("active");
    }
  }
});
</script>


<script src="../js/gallery.js"></script>
</body>


</html>