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

            </head>


<body>
              <!-- ================================================
                  🧭 NAVIGATION BAR SECTION
              ================================================= -->
              <div class="nav">
                <div class="navbar">

                  <!-- 🟢 Logo -->
                  <a href="../php/index.php" class="logo-link">
                    <img src="../imgs/logo.png" alt="LitterLens logo" class="logo-img">
                  </a>

                  <!-- 🟣 Right Navigation Links -->
                  <div class="r-nav">
                    <a href="#">Home</a>
                    <a href="#about-page">About</a>
                    <a href="#contact-page">Contacts</a>

                    <!-- Upload Button -->
                    <button class="upload-btn" onclick="location.href='#upload-page'">Upload</button>

                    <!-- User Icon -->
                    <a href="../php/index_login.php">
                      <i class="fa-solid fa-user" id="user-icon"></i>
                    </a>
                  </div>

                </div>
              </div>



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
                    <option value="0.10" selected>Confidence Threshold: 20% (Balanced Default)</option>
                    <option value="0.20">Confidence Threshold: 40% (Moderate)</option>
                    <option value="0.30">Confidence Threshold: 60% (Strict)</option>
                    <option value="0.40">Confidence Threshold: 80% (Very Strict)</option>
                  </select>


                    <!-- 🔹 Label Display Mode -->
                    <select class="label-mode-dropdown">
                      <option value="confidence">Label Display Mode: Draw Confidence</option>
                      <option value="labels">Label Display Mode: Draw Labels</option>
                      <option value="boxes">Label Display Mode: Draw Boxes</option>
                    </select>

                    <!-- 🔹 Opacity Control -->
                    <select id="opacityDropdown" class="label-opacity-dropdown">
                      <option value="0.25">Opacity Threshold: 25%</option>
                      <option value="0.50">Opacity Threshold: 50%</option>
                      <option value="0.75">Opacity Threshold: 75%</option>
                      <option value="1.00" selected>Opacity Threshold: 100%</option>
                    </select>
                  </div>

                  <!-- 🧠 RESULT CONTENT -->
                  <div class="r-cards">

                    <!-- 📷 LEFT COLUMN — Image Viewer -->
                    <div class="left-col">
                      <!-- Before/After Switch -->
                      <label class="switch">
                        <input type="checkbox" id="beforeAfterSwitch">
                        <span class="slider"></span>
                      </label>

                      <!-- Image Display -->
                      <div class="img-container">
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

                        <!-- Geolocation Data -->
                        <a href="#" target="_blank" class="location" id="location-link">
                          <div class="location-icon"></div>
                          <div>
                            <strong>Latitude:</strong> <span id="lat-value">0.0000</span><br>
                            <strong>Longitude:</strong> <span id="lng-value">0.0000</span>
                          </div>
                        </a>

                        <!-- Download Report -->
                        <a href="#" class="download" id="download-pdf">Download Report (PDF)</a>
                      </div>
                    </div>
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
      </script>

      <!-- 📦 Main Result Page Logic -->
      <script src="../js/gallery.js"></script>

</body>
</html>