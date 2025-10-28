<?php
$files = [];
$folderName = '';
if (isset($_GET['files'])) {
    $files = json_decode($_GET['files'], true);
    if (!empty($files)) {
        $parts = explode('/', $files[0]);
        if (count($parts) > 1) {
            $folderName = $parts[1];
        }
    }
}
?>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="../css/index_results.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>



</head>

<body>

 <div class="nav">
    <div class="navbar">
        <a href="../php/index.php" class="logo-link">
        <img src="../imgs/logo.png" alt="LitterLens logo" class="logo-img">
      </a>
        <div class="r-nav">
          <a href="#"> Home </a>
          <a href="#about-page"> About </a>
          <a href="#contact-page"> Contacts </a>
          <button> Upload </button>
          <a href="../php/index_login.php">
            <i class="fa-solid fa-user" id="user-icon"></i>
          </a>
        </div>
    </div>
  </div>
 
  <div class="content-container">
    <div class="r-content">
      <div class="r-header">
        <h1> Detection Results </h1>

        
          <select class="threshold-dropdown">
            <option value="0.25">Confidence Threshold: 25%</option>
            <option value="0.50">Confidence Threshold: 50%</option>
            <option value="0.75">Confidence Threshold: 75%</option>
            <option value="1.00">Confidence Threshold: 100%</option>
          </select>

          <select class="label-mode-dropdown">
            <option value="confidence">Label Display Mode: Draw Confidence</option>
            <option value="labels">Label Display Mode: Draw Labels</option>
            <option value="boxes">Label Display Mode: Draw Boxes</option>
          </select>


          <select id="opacityDropdown" class="label-opacity-dropdown">
            <option value="0.25">Opacity Threshold: 25%</option>
            <option value="0.50">Opacity Threshold: 50%</option>
            <option value="0.75">Opacity Threshold: 75%</option>
            <option value="1.00" selected>Opacity Threshold: 100%</option>

          </select>

      </div>
      
      <div class="r-cards">
        <div class="left-col">
           <label class="switch">
            <input type="checkbox" id="beforeAfterSwitch">
            <span class="slider"></span>
          </label>
          <div class="img-container">
            <img src="" alt="imagealt" class="main-img">
            <span id="file-name-display" class="file-name"></span>
            <button class="nav-btn prev-btn">&#10094;</button>
            <button class="nav-btn next-btn">&#10095;</button>

              <div class="zoom-controls">
                <button id="zoom-out">−</button>
                <span id="zoom-level">100%</span>
                <button id="zoom-in">+</button>
                <button id="zoom-reset" title="Reset Zoom">&#x21bb;</button>
              </div>
          </div>

          <div class="r-buttons">

            <button id="go-back-btn" class="back-button">
              ⬅ Go Back
            </button>
            <button id="upload-btn" class="upload-button">
              📤 Upload to Database
            </button>


          </div>
        </div>
        <div class="receipt">
          <div class="receipt-card">
            <h2>24 Items detected</h2>

            <table>
              <tr>
                <th>Classification</th>
                <th>Count</th>
              </tr>
              <tr>
                <td>item</td>
                <td>0</td>
              </tr>
              <tr>
                <td>item2</td>
                <td>0</td>
              </tr>
              <tr>
                <td>item3</td>
                <td>0</td>
              </tr>
              <tr>
                <td>Other</td>
                <td>0</td>
              </tr>
            </table>

            <p class="accuracy">Detection Accuracy: 97%</p>
            <div class="divider"></div>

            <a href="#" target="_blank" class="location" id="location-link">
              <div class="location-icon"></div>
              <div>
                <strong>Latitude:</strong> <span id="lat-value">0.0000</span><br>
                <strong>Longitude:</strong> <span id="lng-value">0.0000</span>
              </div>
            </a>

            <a href="#" class="download" id="download-pdf">Download Report (PDF)</a>
          </div>
        </div>
      </div>
    </div>
</div>

<script>
const imagePaths = <?php echo json_encode($files); ?>;
const uploadedFolder = "<?php echo $folderName; ?>";
</script>

<script src="../js/gallery.js"></script>
</body>

</html>