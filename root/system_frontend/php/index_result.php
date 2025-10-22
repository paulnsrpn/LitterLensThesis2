<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>

  <link rel="stylesheet" href="../css/results.css">

  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
 
</head>

<body>

  <div class="nav">
    <div class="navbar">
      <img src="../imgs/logo.png" alt="litterlens logo">
      <div class="r-nav">
        <a href="main.html"> Home </a>
        <a href="#about-page"> About </a>
        <a href="#contact-page"> Contacts </a>
        <button> Upload </button>
        <a href="login_reg.html">
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
          <option value="0.25">Threshold: 0.25</option>
          <option value="0.50">Threshold: 0.50</option>
          <option value="0.75">Threshold: 0.75</option>
          <option value="1.00">Threshold: 1.00</option>
        </select>
      </div>
      <div class="r-cards">
        <div class="left-col">
          <div class="img-container">
            <button class="nav-btn prev-btn">&#10094;</button> <!-- left arrow -->
            <button class="nav-btn next-btn">&#10095;</button> <!-- right arrow -->
            <label class="switch">
              <input type="checkbox">
              <span class="slider"></span>
            </label>
          </div>
          <div class="r-buttons">
            <p>Go Back</p>
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
                <td>Plastic Bottle</td>
                <td>10</td>
              </tr>
              <tr>
                <td>Plastic Bag</td>
                <td>7</td>
              </tr>
              <tr>
                <td>Styrofoam</td>
                <td>5</td>
              </tr>
              <tr>
                <td>Other</td>
                <td>2</td>
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

<script src="../js/result.js"></script>   
</body>

</html>