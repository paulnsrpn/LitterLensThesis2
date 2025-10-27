<?php
require_once '../../system_backend/php/system_config.php';

// === Require login ===
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

$admin_name = $_SESSION['admin_name'] ?? '';

$total_images = 0;
$total_detections = 0;
$average_accuracy = 0; // 🆕 New
$debug_logs = [];

function debugLog(&$logs, $message) {
    $time = date("H:i:s");
    $logs[] = "[$time] $message";
}

// === Get total images ===
$imgCountResponse = supabaseRequest('GET', 'images', null, 'select=count');
if (is_array($imgCountResponse) && isset($imgCountResponse[0]['count'])) {
    $total_images = (int)$imgCountResponse[0]['count'];
    debugLog($debug_logs, "🟢 Total images retrieved: $total_images");
} else {
    debugLog($debug_logs, "❌ Failed to retrieve total images. Response: " . json_encode($imgCountResponse));
}

// === 🆕 Get total quantity of detections (not just detection_id count) ===
$total_quantity = 0;
$quantityResponse = supabaseRequest('GET', 'detections', null, 'select=quantity');

if (is_array($quantityResponse)) {
    foreach ($quantityResponse as $row) {
        $total_quantity += (int)$row['quantity'];
    }
    $total_detections = $total_quantity; // ✅ Use total quantity instead of row count
    debugLog($debug_logs, "🟢 Total quantity of detections retrieved: $total_detections");
} else {
    debugLog($debug_logs, "❌ Failed to retrieve detection quantities. Response: " . json_encode($quantityResponse));
}

// === 🆕 Calculate average accuracy ===
if ($total_detections > 0) {
    // Get all confidence levels
    $accuracyResponse = supabaseRequest('GET', 'detections', null, 'select=confidence_lvl');
    if (is_array($accuracyResponse)) {
        $total_confidence = 0;
        $count_confidence = 0;

        foreach ($accuracyResponse as $row) {
            $total_confidence += (float)$row['confidence_lvl'];
            $count_confidence++;
        }

        if ($count_confidence > 0) {
            $average_accuracy = $total_confidence / $count_confidence;
            debugLog($debug_logs, "🟢 Average accuracy calculated: $average_accuracy%");
        } else {
            debugLog($debug_logs, "ℹ️ No confidence levels found, accuracy is 0%");
        }
    } else {
        debugLog($debug_logs, "❌ Failed to retrieve confidence levels for accuracy.");
    }
} else {
    debugLog($debug_logs, "ℹ️ No detections yet, accuracy is 0%");
}

$debug_json = json_encode($debug_logs);
?>



<div id="php-debugger" style="
  position:fixed;
  bottom:10px;
  right:10px;
  width:350px;
  height:200px;
  background:#1e1e1e;
  color:#eee;
  font-family:monospace;
  font-size:12px;
  padding:8px;
  border-radius:8px;
  overflow-y:auto;
  z-index:9999;
  box-shadow:0 0 8px rgba(0,0,0,0.4);">
  <strong>🐞 Debugger Panel</strong>
  <hr>
</div>

<script>
  const phpDebugLogs = <?= $debug_json ?>;
  const debugContainer = document.getElementById("php-debugger");
  phpDebugLogs.forEach(log => {
    const el = document.createElement("div");
    el.textContent = log;
    if (log.includes("🟢")) el.style.color = "#4ade80";
    if (log.includes("❌")) el.style.color = "#f87171";
    debugContainer.appendChild(el);
  });
  console.log("[PHP DEBUG LOGS]", phpDebugLogs);
</script>






<!DOCTYPE html>
<html lang="en">

<head>
  <!-- ✅ Basic Meta -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>

  <link rel="stylesheet" href="../css/admin.css">>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
</head>


<body>
    <div class="a-nav">
        <div class="a-header">
            <img src="../imgs/a-logo.png" alt="LitterLens logo">
            <p><?php echo htmlspecialchars($admin_name); ?></p>
        </div>

        <div class="a-menu">
            <a class="tab-link active" data-tab="dashboard">Dashboard</a>
            <a class="tab-link" data-tab="image">Image and Detection</a>
            <a class="tab-link" data-tab="analytics">Analytics</a>
            <a class="tab-link" data-tab="users">Users</a>
            <a class="tab-link" data-tab="logs">Activity Logs</a>
            <a class="tab-link" data-tab="realtime">Real-Time Detection</a>

            <div class="menu-bottom">
                <a href="#">Settings</a>
                <div class="log-out">
                    <a href="/LITTERLENSTHESIS2/root/system_backend/php/system_logout.php">Log out</a>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-button">
        <span class="admin-circle"></span>
        <span class="admin-divider"></span>
        <span class="admin-label">Admin</span>
    </div>


    <!--                                            DASHBOARD SECTION                                     -->
    <!-- DASHBOARD SECTION -->
    <div id="dashboard" class="tab-content active">
        <div class="dashboard-container">

            <!-- Top Stats -->
            <div class="dashboard-stats">
                    <div class="stat-card">
                    <h3>Images analyzed</h3>
                    <p><?= number_format($total_images) ?></p>
                    </div>

                    <div class="stat-card">
                    <h3>Litter Detected</h3>
                    <p><?= number_format($total_detections) ?></p>
                    </div>

                    <div class="stat-card">
                    <h3>Accuracy</h3>
                    <p><?= number_format($average_accuracy, 2) ?>%</p>
                    </div>
            </div>

            <!-- Charts -->
            <div class="dashboard-charts">
                <div class="chart-card">
                    <h3>Litter Trends</h3>
                    <canvas id="trendChartDash"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Litter Hotspots</h3>
                    <canvas id="hotspotChartDash"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="dashboard-activity">
                <h3>Recent Activity</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Image</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>September 1</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>September 5</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>September 17</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>September 29</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>October 5</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>October 19</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>October 30</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>


    <!--                                            IMAGE AND DETECTION SECTION                                     -->
    <div id="image" class="tab-content image-detection">
        <!-- UPLOAD SECTION -->
        <div class="upload-card">
            <p>Upload Image</p>
            <p class="upload-desc">
                Choose an image to analyze litter presence in the selected photo.
            </p>
            <form>
                <input type="file" id="upload-file" accept="image/*" />
                <button type="submit">Upload & Detect</button>
            </form>
        </div>

        <!-- DETECTION HISTORY SECTION -->
        <div class="history-card">
            <p>Detection History</p>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Image</th>
                        <th>Litter Detected</th>
                        <th>Accuracy</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Sept 29, 2025</td>
                        <td><img src="" alt="thumbnail" /></td>
                        <td>Plastic Bag</td>
                        <td>88%</td>
                        <td><a href="#">View</a></td>
                    </tr>
                    <tr>
                        <td>Sept 28, 2025</td>
                        <td><img src="" alt="thumbnail" /></td>
                        <td>Can, Bottle</td>
                        <td>91%</td>
                        <td><a href="#">View</a></td>
                    </tr>
                    <tr>
                        <td>Sept 27, 2025</td>
                        <td><img src="" alt="thumbnail" /></td>
                        <td>Styrofoam</td>
                        <td>86%</td>
                        <td><a href="#">View</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!--                                            ANALYTICS SECTION                                     -->
    <section id="analytics" class="tab-content">
        <h2 class="a-title">Analytics Overview</h2>

        <!-- KPI Cards -->
        <div class="a-overview">
            <div class="a-card">
                <h3>Total Detections</h3>
                <div class="a-value">1,000</div>
            </div>
            <div class="a-card">
                <h3>Users</h3>
                <div class="a-value">25</div>
            </div>
            <div class="a-card">
                <h3>Reports Today</h3>
                <div class="a-value">26</div>
            </div>
            <div class="a-card">
                <h3>Accuracy</h3>
                <div class="a-value">92%</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="a-grid">
            <!-- Pie Chart -->
            <div class="a-box">
                <h3>Detections by Category</h3>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>

            <!-- Map -->
            <div class="a-box">
                <h3>Geospatial Mapping</h3>
                <div id="a-map"></div>
            </div>

            <!-- Line Chart -->
            <div class="a-box" style="grid-column: span 2;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Litter Trends</h3>
                    <button class="a-dropdown">Monthly ⟳</button>
                </div>
                <div class="chart-container">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>
    </section>



    <!--                                            USER SECTION                                   -->
    <!-- Header -->
    <div id="users" class="tab-content">
        <div class="user-header">
            <h2>The Team</h2>

            <div class="user-controls">
                <button class="add-member-btn" onclick="window.location.href='../php/index_register.php'">
                <i class="fa-solid fa-plus"></i> Add Member
                </button>

                <div class="search-wrapper">
                    <input type="text" placeholder="Search users..." class="user-search">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                </div>
            </div>

        </div>


        <!-- User Grid -->
        <div class="user-grid">

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="../imgs/avatar2.jpg" alt="User Avatar" class="user-avatar">
                <h3>Jervie Alentajan</h3>
                <p>jervie@gmail.com</p>
                <span class="role">User</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="../imgs/avatar1.jpg" alt="User Avatar" class="user-avatar">
                <h3>Vico Sotto</h3>
                <p>sotto_vico@plpasig.edu.ph</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="../imgs/avatar3.jpg" alt="User Avatar" class="user-avatar">
                <h3>Mark Sison</h3>
                <p>mars@gmail.com</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="../imgs/avatar4.jpg" alt="User Avatar" class="user-avatar">
                <h3>Emanuel Florida</h3>
                <p>eman@gmail.com</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="../imgs/avatar5.jpg" alt="User Avatar" class="user-avatar">
                <h3>Pauline Serapion</h3>
                <p>pauln@gmail.com</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>


        </div>

    </div>

    <!--                                            LOGS SECTION                                   -->

    <div id="logs" class="tab-content">
        <h2 class="l-title">Activity Logs</h2>
        <div class="logs-search">
            <input type="text" placeholder="Search activty...">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <div class="activity-card">
            <div class="activity-section left">
                <i class="fa-regular fa-clock"></i>
                <span><strong>31</strong> Actions Today</span>
            </div>
            <div class="divider"></div>
            <div class="activity-section right">
                <i class="fa-regular fa-user"></i>
                <div class="most-active">
                    <span class="label">Most Active:</span>
                    <span class="user">admin [#]</span>
                </div>
            </div>
        </div>

        <div class="logs-container">
            <div class="logs-header">
                <div class="logs-filters">
                    <!-- Date Range Picker -->
                    <div class="date-range">
                        <i class="fa-regular fa-calendar"></i>
                        <input type="text" name="daterange" value="01/01/2018 - 01/15/2018" />
                    </div>

                    <!-- Dropdown Filter -->
                    <div class="action-filter">
                        <button class="dropdown-btn" id="actionDropdown">
                            All Actions <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu" id="dropdownMenu">
                            <li data-value="all">All Actions</li>
                            <li data-value="added">Added</li>
                            <li data-value="updated">Updated</li>
                            <li data-value="deleted">Deleted</li>
                        </ul>
                    </div>
                </div>

                <button class="download-btn">Download Logs (.pdf)</button>
            </div>

            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Affected Record</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="logsBody">
                    <!-- Example data -->
                    <tr>
                        <td>10/01/2025</td>
                        <td>Jervie Alentajan</td>
                        <td>Updated</td>
                        <td>admin</td>
                        <td>Changed Email</td>
                        <td class="status success">Success</td>
                    </tr>
                    <tr>
                        <td>10/02/2025</td>
                        <td>Pauline Serapion</td>
                        <td>Added</td>
                        <td>admin</td>
                        <td>New Contact No.</td>
                        <td class="status success">Success</td>
                    </tr>
                    <tr>
                        <td>10/03/2025</td>
                        <td>Emanuel Florida</td>
                        <td>Deleted</td>
                        <td>detections</td>
                        <td>Removed Image</td>
                        <td class="status success">Success</td>
                    </tr>
                </tbody>
            </table>

            <div class="pagination">
                <span id="prevPage">&lt; Previous</span>
                <span id="pageInfo">Page 1 of 10</span>
                <span id="nextPage">Next &gt;</span>
            </div>
        </div>


    </div>

    <!--                                            SETTINGS SECTION                                   -->
    <div id="settings" class="tab-content">
        <h2 class="s-title">Settings</h2>
        <div class="settings-search">
            <input type="text" placeholder="Search settings...">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <div class="settings-card1">
            <h3>Profile</h3>
            <div class="profile-pic-container">
                <img src="../imgs/avatar2.jpg" alt="Profile Picture" class="profile-pic">
                <div class="overlay">
                    <i class="fa-solid fa-camera"></i>
                    <input type="file" id="upload" accept="image/*">
                </div>
            </div>

            <!-- Modal for cropping -->
            <div id="cropModal" class="modal">
                <div class="modal-content">
                    <h3>Adjust your photo</h3>
                    <div class="crop-container">
                        <img id="cropImage" style="max-width: 100%; display: block;">
                    </div>
                    <div class="modal-actions">
                        <button id="cancelCrop">Cancel</button>
                        <button id="confirmCrop">Upload Photo</button>
                    </div>
                </div>
            </div>

            <div class="detail-columns">
                <div class="details1">
                    <div class="dt-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" value=" ">
                    </div>

                    <div class="dt-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="">
                    </div>

                    <div class="dt-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" value="">
                    </div>
                </div>
                <div class="details2">
                    <div class="dt-group">
                        <label for="phone">Contact Number</label>
                        <input type="text" id="phone" value="">
                        <p class="contact-description">
                            <i class="fa-solid fa-triangle-exclamation warning-icon"></i>
                            Please add your contact number for administrative verification and important updates.
                        </p>
                    </div>

                    <div class="dt-group">
                        <label for="conpassword">Confirm Password</label>
                        <input type="password" id="conpassword" value=" ">
                    </div>
                </div>
            </div>
            <button class="settings-save-btn">Save Changes</button>
        </div>
        <div class="settings-card2">
            <h3> Model Management </h3>
            <div class="model-section">
                <label class="section-title">Active Model</label>
                <input type="text" class="model-input" placeholder="">
                <p class="field-description">Select which model should be used for the detection</p>
            </div>

            <div class="model-section">
                <label class="section-title">Upload New Model (.pt)</label>
                <input type="file" class="file-input">
                <p class="field-description">Upload a new trained model file</p>
            </div>
            <h3> Model Version Control </h3>
            <div class="table-container">
                <table class="model-table">
                    <thead>
                        <tr>
                            <th>Model Name</th>
                            <th>Version</th>
                            <th>Accuracy</th>
                            <th>Uploaded on</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>LitterLens_CNN</td>
                            <td>v1</td>
                            <td>98%</td>
                            <td>August 2025</td>
                            <td class="status active">Active</td>
                        </tr>
                        <tr>
                            <td>Las Vegas Mowdels</td>
                            <td>v2</td>
                            <td>69%</td>
                            <td>June 2025</td>
                            <td class="status inactive">Inactive</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button class="model-save-btn">Save Changes</button>
        </div>
    </div>


    <!--                                            REAL-TIME DETECTION SECTION                                   -->
    <div id="realtime" class="tab-content realtime">
        <div class="realtime-header">
            <h2 class="r-title">Real-Time Detection</h2>
            <p>Monitor Live Litter Detection</p>
        </div>
        <div class="realtime-content">
            <div class="realtime-upload">
                <div class="cam-container">
                    <h2>Live Feed: Sumilang Bridge</h2>
                    <div class="camera-header">
                        <div class="location-details">
                            <h3>📍 Location Details</h3>
                            <p>Latitude: <span id="latitude">--</span></p>
                            <p>Longitude: <span id="longitude">--</span></p>
                        </div>
                        <div class="cam-controls">
                            <button id="start-btn"><i class="fa-solid fa-play"></i> Start</button>
                            <button id="stop-btn"><i class="fa-solid fa-stop"></i> Stop</button>
                            <button id="refresh-btn"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
                        </div>
                    </div>
                    <img src="" alt="Live Camera Feed" id="liveFeed">
                    <div class="detection-status">
                        <p>Time: <span class="time">1hr 10mins 30s</span></p>
                        <p>Status: <span class="status">Active</span></p>
                    </div>
                </div>
                <p class="realtime-footer">
                    Real-Time Detection powered by <br>
                    <span>LitterLens AI Model v1</span>
                </p>
            </div>

            <div class="stats-container">
                <div class="stats-card">
                    <h3>Detection Stats</h3>
                    <div class="stats-item">
                        <p class="label">Total Detections</p>
                        <p class="value">9,549</p>
                    </div>
                    <div class="stats-item">
                        <p class="label">Top Detected Litter</p>
                        <p class="value">58% <br><em>Organic Debris</em></p>
                    </div>
                    <div class="stats-item">
                        <p class="label">Detection Speed</p>
                        <p class="value">1.4s/frame</p>
                    </div>
                    <div class="stats-item">
                        <p class="label">Camera Status</p>
                        <p class="value active">Active</p>
                    </div>
                    <div class="stats-item">
                        <p class="label">Detection Accuracy</p>
                        <p class="value">99%</p>
                    </div>
                </div>

                <div class="stats-card">
                    <h3>Top Detected Litter:</h3>
                    <table class="litter-table">
                        <thead>
                            <tr>
                                <th>Classification</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Plastic</td>
                                <td>2,622</td>
                            </tr>
                            <tr>
                                <td>Organic Debris</td>
                                <td>6,439</td>
                            </tr>
                            <tr>
                                <td>Foamed Plastic</td>
                                <td>160</td>
                            </tr>
                            <tr>
                                <td>Paper and</td>
                                <td>60</td>
                            </tr>
                            <tr>
                                <td>Cardboard</td>
                                <td>57</td>
                            </tr>
                            <tr>
                                <td>Rubber</td>
                                <td>25</td>
                            </tr>
                            <tr>
                                <td>Fabric and Textiles</td>
                                <td>33</td>
                            </tr>
                            <tr>
                                <td>Metal</td>
                                <td>10</td>
                            </tr>
                            <tr>
                                <td>Glass and Ceramic</td>
                                <td>80</td>
                            </tr>
                            <tr>
                                <td>Biological Debris</td>
                                <td>45</td>
                            </tr>
                            <tr>
                                <td>Sanitary Waste</td>
                                <td>13</td>
                            </tr>
                            <tr>
                                <td>Electronic Waste</td>
                                <td>5</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>





    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Category Chart
        const ctx1 = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Plastic', 'Metal', 'Paper', 'Glass', 'Others'],
                datasets: [{
                    data: [40, 20, 15, 10, 15],
                    backgroundColor: ['#40916c', '#74c69d', '#95d5b2', '#d8f3dc', '#1b4332']
                }]
            }
        });

        // Trend Chart
        const ctx2 = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Detections',
                    data: [500, 700, 800, 650, 900, 1200],
                    borderColor: '#2d6a4f',
                    backgroundColor: 'rgba(45,106,79,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            }
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const links = document.querySelectorAll(".tab-link");
            const contents = document.querySelectorAll(".tab-content");
            const settingsLink = document.querySelector(".a-menu a[href='#']"); // the Settings link in bottom menu

            // Show only dashboard on page load
            contents.forEach(c => c.style.display = "none");
            document.getElementById("dashboard").style.display = "block";

            // Sidebar tab click handling
            links.forEach(link => {
                link.addEventListener("click", () => {
                    // Remove active states
                    links.forEach(l => l.classList.remove("active"));
                    contents.forEach(c => c.style.display = "none");

                    // Activate current tab
                    link.classList.add("active");
                    const target = link.getAttribute("data-tab");
                    const section = document.getElementById(target);
                    if (section) {
                        section.style.display = "block";
                    }
                });
            });

            // Settings link handling (bottom of sidebar)
            if (settingsLink) {
                settingsLink.addEventListener("click", (e) => {
                    e.preventDefault();
                    links.forEach(l => l.classList.remove("active"));
                    contents.forEach(c => c.style.display = "none");
                    document.getElementById("settings").style.display = "block";
                });
            }
        });
    </script>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Litter Trends Bar Chart
        const ctxT = document.getElementById('trendChartDash').getContext('2d');
        new Chart(ctxT, {
            type: 'bar',
            data: {
                labels: ['September 1', 'September 5', 'September 17', 'September 29', 'October 5', 'October 19'],
                datasets: [{
                    label: 'Litter Count',
                    data: [20, 30, 10, 20, 25, 18],
                    backgroundColor: '#74c69d'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Litter Hotspots Line Chart
        const ctxH = document.getElementById('hotspotChartDash').getContext('2d');
        new Chart(ctxH, {
            type: 'line',
            data: {
                labels: ['P1', 'O1', 'R1', 'B1', 'M1', 'X1'],
                datasets: [{
                    label: 'Hotspot Activity',
                    data: [50, 20, 35, 18, 12, 17],
                    borderColor: '#40916c',
                    backgroundColor: 'rgba(64,145,108,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <script>
        // === PIE CHART ===
        new Chart(document.getElementById("pieChart"), {
            type: "pie",
            data: {
                labels: [
                    "Biological Debris", "Electronic Waste", "Fabric and Textiles",
                    "Foamed Plastic", "Glass and Ceramic", "Metal", "Organic Debris",
                    "Paper and Cardboard", "Plastic", "Rubber", "Sanitary Waste"
                ],
                datasets: [{
                    data: [15, 12, 11, 10, 9, 10, 11, 10, 11, 10, 20],
                    backgroundColor: [
                        "#2d6a4f", "#40916c", "#52b788", "#74c69d",
                        "#95d5b2", "#b7e4c7", "#d8f3dc", "#74c69d",
                        "#52b788", "#40916c", "#2d6a4f"
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                plugins: {
                    legend: {
                        position: "right",
                        labels: {
                            color: "#1b4332",
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                }
            }
        });

        // === LINE CHART ===
        new Chart(document.getElementById("lineChart"), {
            type: "line",
            data: {
                labels: ["Jan 2025", "Feb 2025", "Mar 2025", "Apr 2025", "May 2025", "Jun 2025"],
                datasets: [{
                        label: "Biological Debris",
                        data: [55, 45, 52, 40, 49, 47],
                        borderColor: "#2d6a4f",
                        tension: 0.4,
                        fill: false
                    },
                    {
                        label: "Plastic",
                        data: [50, 42, 48, 41, 45, 44],
                        borderColor: "#52b788",
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                plugins: {
                    legend: {
                        position: "right",
                        labels: {
                            color: "#1b4332",
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: "#eee"
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // === LEAFLET MAP ===
        const map = L.map("a-map").setView([14.5896, 121.0370], 13); // Center on Pasig River, Manila

        // Tile layer
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>'
        }).addTo(map);

        // Hardcoded sample detections (lat, lng)
        const detections = [
            [14.5995, 120.9842], // Manila
            [14.5800, 121.0000],
            [14.5830, 121.0200],
            [14.5900, 121.0300],
            [14.5950, 121.0400],
            [14.6000, 121.0500],
            [14.6050, 121.0600],
            [14.6100, 121.0700]
        ];

        detections.forEach(coords => {
            L.marker(coords, {
                icon: L.icon({
                    iconUrl: "https://cdn-icons-png.flaticon.com/512/484/484167.png",
                    iconSize: [25, 35],
                    iconAnchor: [12, 35]
                })
            }).addTo(map);
        });
    </script>

    <!--                                                 Cropper JS                                  -->
    <script>
        let cropper;
        const uploadInput = document.getElementById('upload');
        const cropModal = document.getElementById('cropModal');
        const cropImage = document.getElementById('cropImage');
        const profilePic = document.getElementById('profilePic');
        const cancelCrop = document.getElementById('cancelCrop');
        const confirmCrop = document.getElementById('confirmCrop');

        // When image is selected
        uploadInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                cropImage.src = event.target.result;
                cropModal.style.display = 'flex';

                // Initialize cropper after image loads
                cropImage.onload = () => {
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(cropImage, {
                        aspectRatio: 1, // square
                        viewMode: 1,
                        dragMode: 'move',
                        background: false
                    });
                };
            };
            reader.readAsDataURL(file);
        });

        // Cancel button
        cancelCrop.addEventListener('click', () => {
            cropModal.style.display = 'none';
            uploadInput.value = ''; // reset file input
            if (cropper) cropper.destroy();
        });

        // Confirm crop and update profile
        confirmCrop.addEventListener('click', () => {
            const canvas = cropper.getCroppedCanvas({
                width: 200,
                height: 200,
            });

            profilePic.src = canvas.toDataURL('image/png');
            cropModal.style.display = 'none';
            if (cropper) cropper.destroy();
        });
    </script>

    // LOGS SECTION SCRIPTS

    <script>
        $(function() {
            $('input[name="daterange"]').daterangepicker({
                opens: 'left'
            }, function(start, end, label) {
                console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
            });
        });

        // === DROPDOWN ===
        const dropdownBtn = document.getElementById("actionDropdown");
        const dropdownMenu = document.getElementById("dropdownMenu");

        dropdownBtn.addEventListener("click", () => {
            dropdownMenu.style.display =
                dropdownMenu.style.display === "block" ? "none" : "block";
        });

        dropdownMenu.querySelectorAll("li").forEach((item) => {
            item.addEventListener("click", () => {
                dropdownBtn.innerHTML = `${item.textContent} <i class="fa-solid fa-chevron-down"></i>`;
                dropdownMenu.style.display = "none";
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.style.display = "none";
            }
        });


        // ===== Pagination Simulation =====
        let currentPage = 1;
        const totalPages = 10;
        const pageInfo = document.getElementById('pageInfo');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        function updatePagination() {
            pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }

        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                updatePagination();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                updatePagination();
            }
        });

        updatePagination();
    </script>


</body>

</html>