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
          <option value="0.01">Threshold: 0.05</option>
          <option value="0.03">Threshold: 0.10</option>
          <option value="0.05">Threshold: 0.15</option>
          <option value="0.09">Threshold: 0.30</option>
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
          </div>

          <div class="r-buttons">
            <button id="go-back-btn" class="upload-photo-btn">Go Back</button>
            <button> Analyze Photo</button>
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

            <a href="https://www.google.com/maps?q=14.5869,121.0921" target="_blank" class="location">
              <div class="location-icon"></div>
              <div>
                <strong>Latitude:</strong> 14.5869<br>
                <strong>Longitude:</strong> 121.0921
              </div>
            </a>
            
            <a href="#" class="download">Download Report (PDF)</a>
          </div>
        </div>
      </div>

    </div>

<script>
const imagePaths = <?php echo json_encode($files); ?>;
const uploadedFolder = "<?php echo $folderName; ?>";
</script>

<script src="../js/result.js"></script>   
<script src="../js/gallery.js"></script>
</body>

</html> 