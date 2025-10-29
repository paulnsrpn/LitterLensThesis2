
<?php
require_once '../../system_backend/php/system_config.php';
require_once '../../system_backend/php/system_admin_data.php';

// ✅ LOGIN CHECK
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!-- 
<div id="php-debugger" style="
  position:fixed;
  bottom:10px;
  right:10px;
  width:350px;
  height:240px;
  background:#1e1e1e;
  color:#eee;
  font-family:monospace;
  font-size:12px;
  padding:8px;
  border-radius:8px;
  overflow-y:auto;
  z-index:9999;
  box-shadow:0 0 8px rgba(0,0,0,0.4);
">
  <strong>🐞 Debugger Panel</strong>
  <hr>
  <strong>📜 PHP Logs:</strong><br>
  <pre style="max-height:120px;overflow-y:auto;">
<?= htmlspecialchars(implode("\n", $debug_logs)) ?>
  </pre>
  <hr>
  <strong>📍 Location Debug:</strong><br>
  <?php if (!empty($heatmap_points)) : ?>
    <pre style="max-height:100px;overflow-y:auto;color:#8ef;">
<?= htmlspecialchars(print_r(array_slice($heatmap_points, 0, 5), true)) ?>
<?= count($heatmap_points) > 5 ? "...\nTotal: " . count($heatmap_points) . " points" : "" ?>
    </pre>
  <?php else : ?>
    <span style="color:#faa;">⚠️ No coordinates found.</span>
  <?php endif; ?>
</div> 
 -->

<!DOCTYPE html>
<html lang="en">

    <head>
            <!-- ==============================================
                🧭 BASIC META
                ============================================== -->
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Dashboard</title>

            <!-- ==============================================
                🎨 STYLESHEETS
                ============================================== -->
            <link rel="stylesheet" href="../css/admin.css">
            <link rel="stylesheet" href="../css/camera.css">
            <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

            <!-- ==============================================
                📊 SCRIPTS & LIBRARIES
                ============================================== -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
            <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
            <script src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
            <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
            <link href="https://unpkg.com/maplibre-gl@3.3.1/dist/maplibre-gl.css" rel="stylesheet" />
            <script src="https://unpkg.com/maplibre-gl@3.3.1/dist/maplibre-gl.js"></script>
    </head>

  <body>
            <!-- ==============================================
                🧭 ADMIN NAVIGATION PANEL
                ============================================== -->
            <div class="a-nav">
            <div class="a-header">
                <img src="../imgs/a-logo.png" alt="LitterLens logo">
                <p>Admin</p>
            </div>

            <div class="a-menu">
                <a class="tab-link active" data-tab="dashboard">Dashboard</a>
                <a class="tab-link" data-tab="image">Image and Detection</a>
                <a class="tab-link" data-tab="analytics">Analytics</a>
                <a class="tab-link" data-tab="users">Team</a>
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

            <!-- ==============================================
                👤 ADMIN BUTTON (TOP-RIGHT)
                ============================================== -->
            <div class="admin-button">
            <span class="admin-circle"></span>
            <span class="admin-divider"></span>
            <span class="admin-label">
                <p><?php echo htmlspecialchars($admin_name); ?></p>
            </span>
            </div>


            <!-- ==============================================
            📊 DASHBOARD SECTION
            ============================================== -->
            <div id="dashboard" class="tab-content active">
              <div class="dashboard-container">

                <!-- 🔢 TOP STATISTICS -->
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

                <!-- ==========================
                📊 LITTER OVERVIEW SECTION
                =========================== -->
                <div class="chart-section">
                  <!-- Header row -->
                  <div class="chart-header">
                    <h3>Litter Overview</h3>
                    <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                      <select name="trend_filter" id="trendFilter" class="trend-view-dropdown" onchange="this.form.submit()">
                        <option value="day"   <?= ($_GET['trend_filter'] ?? 'month') == 'day' ? 'selected' : '' ?>>By Day</option>
                        <option value="month" <?= ($_GET['trend_filter'] ?? 'month') == 'month' ? 'selected' : '' ?>>By Month</option>
                        <option value="year"  <?= ($_GET['trend_filter'] ?? 'month') == 'year' ? 'selected' : '' ?>>By Year</option>
                      </select>
                    </form>
                  </div>

                  <!-- Charts row -->
                  <div class="dashboard-charts">
                    <div class="chart-card">
                      <h3>Total Litter Detections</h3>
                      <canvas id="trendChartDash"></canvas>
                    </div>

                    <div class="chart-card heatmap-card">
                      <h3>Litter Hotspots</h3>
                      <div id="hotspotMap"></div>
                      <div class="legend">
                        <span>Low</span>
                        <div class="gradient-bar"></div>
                        <span>High</span>
                      </div>
                    </div>
                  </div>
                </div>


                <!-- ==============================================
                🕒 RECENT ACTIVITY (Full-width below)
                ============================================== -->
                <div class="dashboard-activity">
                  <div class="filter-bar">
                    <h3>Recent Activity</h3>
                  <form method="GET" style="display: flex; gap: 10px;">
                      <select name="time_filter" class="time_filter" onchange="this.form.submit()">
                        <option value="today" <?= ($_GET['time_filter'] ?? 'today') == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="last7" <?= ($_GET['time_filter'] ?? '') == 'last7' ? 'selected' : '' ?>>Last 7 Days</option>
                      </select>
                  </form>
                  </div>

                  <table>
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Image</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($recent_activity)): ?>
                        <?php foreach ($recent_activity as $row): ?>
                          <tr>
                            <td>
                              <?= date("F j, Y", strtotime($row['date'])) ?><br>
                              <small><?= date("g:i A", strtotime($row['time'])) ?></small>
                            </td>
                            <td>
                              <img src="<?= getImageUrl($row['filename']) ?>" 
                                  alt="Thumbnail" width="60" style="border-radius: 8px;">
                            </td>
                            <td>New Detection</td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="3" style="text-align:center;">No recent activity</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

              </div> <!-- end of .dashboard-container -->
            </div> <!-- end of #dashboard -->


            <!-- ✅ REAL-TIME DETECTION SECTION -->
            <div id="realtime" class="tab-content realtime">
              <div class="realtime-header">
                <h2 class="r-title">Real-Time Detection</h2>
                <p>Monitor Live Litter Detection</p>
              </div>

              <div class="realtime-content">
                <!-- === MAIN GRID: CAMERA LEFT | STATS RIGHT === -->
                <div class="realtime-grid">

                  <!-- 🎥 LEFT: CAMERA CONTAINER -->
                  <div class="cam-container">
                    <h2>Live Feed:
                      <select id="camera-select">
                      <option value="local" selected>Local Camera</option>
                      </select>
                    </h2>
                    
                    <!-- Header Controls -->
                    <div class="camera-header">
                      <div class="location-details">
                        <h3>📍 Location Details</h3>
                        <p>Latitude: <span id="latitude">--</span></p>
                        <p>Longitude: <span id="longitude">--</span></p>
                      </div>

                      <div class="cam-controls">
                        <select id="threshold-select">
                          <option value="0.25">Threshold: 25%</option>
                          <option value="0.50">Threshold: 50%</option>
                          <option value="0.75">Threshold: 75%</option>
                          <option value="1.00">Threshold: 100%</option>
                        </select>

                        <button id="startBtn"><i class="fa-solid fa-play"></i> Start</button>
                        <button id="stopBtn"><i class="fa-solid fa-stop"></i> Stop</button>
                        <button id="refresh-btn"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
                      </div>
                    </div>

                    <!-- 📸 Live Feed Area -->
                    <div class="cam-preview">
                      <img id="liveFeed" alt="Live Detection Preview">
                      <div class="noDisplay">
                        <i class="fa-solid fa-triangle-exclamation"></i> No camera detected
                      </div>
                    </div>

                    <!-- Detection Status -->
                    <div class="detection-status">
                      <p>Time: <span class="time">0hr 0mins 0s</span></p>
                      <div class="status idle">
                        <i class="fa-solid fa-circle-notch"></i> Idle
                      </div>
                    </div>

                    <!-- Footer -->
                    <p class="realtime-footer">
                      Real-Time Detection powered by <br>
                      <span>LitterLens AI Model v1</span>
                    </p>
                  </div>

                  <!-- 📊 RIGHT: STATS PANEL -->
                  <div class="stats-side">

                  

                    <!-- Detection Stats Card -->
                    <div class="stats-card">
                     <h3 class="detection-header">
                        Detection Stats
                        <button id="uploadDb-btn">
                           <i class="fa-solid fa-upload"></i> Upload to Database
                        </button>
                      </h3>
                      <div class="stats-item">
                        <p class="label">Total Detections</p>
                        <p class="value" id="totalDetections">0</p>
                      </div>
                      <div class="stats-item">
                        <p class="label">Top Detected Litter</p>
                        <p class="value" id="topLitter">--</p>
                      </div>
                      <div class="stats-item">
                        <p class="label">Detection Speed</p>
                        <p class="value" id="detectionSpeed">0s/frame</p>
                      </div>
                      <div class="stats-item">
                        <p class="label">Camera Status</p>
                        <p class="value" id="cameraStatus">Idle</p>
                      </div>
                      <div class="stats-item">
                        <p class="label">Detection Accuracy</p>
                        <p class="value" id="detectionAccuracy">0%</p>
                      </div>
                    </div>

                    <!-- Top Detected Litter Table -->
                    <div class="stats-card">
                      <h3>Top Detected Litter</h3>
                      <table class="litter-table">
                        <thead>
                          <tr>
                            <th>Classification</th>
                            <th>Count</th>
                          </tr>
                        </thead>
                        <tbody id="litterTableBody">
                          <!-- Dynamically filled -->
                        </tbody>
                      </table>
                    </div>

                  </div> <!-- End of Stats Side -->

                </div> <!-- End of Grid -->
              </div> <!-- End of Realtime Content -->
            </div> <!-- End of Realtime Section -->



        <script>
        document.addEventListener("DOMContentLoaded", () => {
            // ===============================
            // 🎛️ ELEMENT SELECTION
            // ===============================
            const links = document.querySelectorAll(".tab-link");
            const contents = document.querySelectorAll(".tab-content");
            const settingsLink = document.querySelector(".a-menu a[href='#']"); // ⚙️ Settings link (bottom)

            // ===============================
            // 🏠 INITIAL LOAD
            // ===============================
            // Hide all sections, show Dashboard by default
            contents.forEach(content => content.style.display = "none");
            const dashboard = document.getElementById("dashboard");
            if (dashboard) dashboard.style.display = "block";

            // ===============================
            // 🧭 SIDEBAR TAB HANDLING
            // ===============================
            links.forEach(link => {
                link.addEventListener("click", () => {
                    // Remove all active classes
                    links.forEach(l => l.classList.remove("active"));
                    contents.forEach(c => c.style.display = "none");

                    // Activate clicked link
                    link.classList.add("active");

                    // Show the matching tab content
                    const targetId = link.getAttribute("data-tab");
                    const targetSection = document.getElementById(targetId);
                    if (targetSection) targetSection.style.display = "block";
                });
            });

            // ===============================
            // ⚙️ SETTINGS LINK HANDLING
            // ===============================
            if (settingsLink) {
                settingsLink.addEventListener("click", e => {
                    e.preventDefault();

                    // Reset active states
                    links.forEach(l => l.classList.remove("active"));
                    contents.forEach(c => c.style.display = "none");

                    // Show settings page
                    const settingsSection = document.getElementById("settings");
                    if (settingsSection) settingsSection.style.display = "block";
                });
            }
        });
        </script>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <script src="../js/admin_realtime.js"></script>
    <script src="../js/admin_Dashboard.js"></script>
     <script src="../js/upload_realtime.js"></script>
    
  <script>
    const detections = <?= $heatmap_json ?? '[]' ?>;
    const trendLabels = <?= $trend_labels ?? '[]' ?>;
    const trendData = <?= $trend_data_json ?? '[]' ?>;
  </script>
   

   

</body>

</html>