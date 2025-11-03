<?php
require_once '../../system_backend/php/system_config.php';
require_once '../../system_backend/php/system_admin_data.php';



// ✅ LOGIN CHECK
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';


?>


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
            <link rel="stylesheet" href="../css/adminbug.css">
            <link rel="stylesheet" href="../css/camera.css">
            <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>


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
                  <div class="chart-header">
                    <h3>Litter Overview</h3>
                  </div>

                  <!-- Charts row -->
                  <div class="dashboard-charts">

                    <!-- 📈 Total Litter Detections (Bar Chart) -->
                    <div class="chart-card">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;">Total Litter Detections</h3>
                        <select id="trendFilter" class="trend-view-dropdown">
                          <option value="day">By Day</option>
                          <option value="month" selected>By Month</option>
                          <option value="year">By Year</option>
                        </select>
                      </div>

                      <div class="chart-container" style="position: relative; height: 400px;">
                        <div id="trendChartLoader" class="chart-loader">Loading trend data...</div>
                        <canvas id="trendChartDash" style="display:none;"></canvas>
                      </div>
                    </div>

                    <!-- 🌍 Litter Hotspots (Map) -->
                    <div class="chart-card heatmap-card">
                      <h3>Litter Hotspots</h3>
                      <div id="hotspotMap"></div>
                      <div class="legend">
                        <span>Low</span>
                        <div class="gradient-bar"></div>
                        <span>High</span>
                      </div>
                    </div>
                  </div> <!-- end .dashboard-charts -->
                </div> <!-- end .chart-section -->


                <!-- ==============================================
                🕒 RECENT ACTIVITY (Full-width below)
                ============================================== -->
                <div class="dashboard-activity">
                  <div class="filter-bar">
                    <h3>Recent Activity</h3>
                    <div style="display: flex; gap: 10px;">
                      <select id="timeFilter" class="time_filter">
                        <option value="today" <?= ($_GET['time_filter'] ?? 'today') == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="last7" <?= ($_GET['time_filter'] ?? '') == 'last7' ? 'selected' : '' ?>>Last 7 Days</option>
                      </select>
                    </div>
                  </div>

                  <!-- Loading overlay -->
                  <div id="activityLoader" class="table-loader" style="display:none;">
                    <div class="loader-spinner"></div>
                    <span class="loader-text">Loading data...</span>
                  </div>

                  <table id="activityTable">
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
                              <img src="<?= getImageUrl($row['filename']) ?>" alt="Thumbnail" width="60" style="border-radius: 8px;">
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
                </div> <!-- end .dashboard-activity -->

              </div> <!-- end .dashboard-container -->
            </div> <!-- end #dashboard -->



            <!-- ==============================================                 
                          ✅ IMAGE AND DETECTION SECTION 
                          ============================================== -->
            <div id="image" class="tab-content image-detection">

              <!-- Tabs -->
              <div class="tabs">
                <button class="tab active" id="detections-tab">Detections</button>
                <button class="tab" id="results-tab">Real-Time Detections</button>
              </div>

              <!-- 🖼️ Detections Tab -->
              <div id="detections" class="tab-section active">
                <div class="upload-box">
                  <h3>Upload Image</h3>
                  <p>Choose an image to analyze litter presence in the selected photo</p>
                  <div class="upload-controls">
                    <input type="file" id="fileUpload">
                    <button class="analyze-btn">Analyze</button>
                  </div>
                </div>

                <!-- Detection History -->
                <div class="history-box">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Detection History (Admin Uploads)</h3>

                    <div class="action-buttons" style="display:flex; gap:10px;">
                      <button class="edit-btn"">
                        <i class="fa-solid fa-pen"></i> Edit
                      </button>
                      <button class="delete-btn" ">
                        <i class="fa-solid fa-trash"></i> Delete
                      </button>
                    </div>
                  </div>

                  <div class="table-container" style="margin-top:10px;">
                      <table>
                        <thead>
                          <tr>
                            <th><input type="checkbox" disabled></th>
                            <th>Date</th>
                            <th>Image</th>
                            <th>Litter Type</th>
                            <th>Accuracy</th>
                            <th>Uploaded By</th>
                            <th>Details</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if (empty($admin_detections)): ?>
                            <tr><td colspan="7" style="text-align:center;">No detection records found.</td></tr>
                          <?php else: ?>
                            <?php foreach ($admin_detections as $det): ?>
                              <tr>
                                <td><input type="checkbox" disabled></td>
                                <td><?= htmlspecialchars($det['date']) ?></td>
                                <td>
                                  <img src="<?= htmlspecialchars($det['image_url']) ?>" 
                                      width="60" height="60" 
                                      style="border-radius:8px;object-fit:cover;">
                                </td>
                                <td><?= htmlspecialchars($det['litter_name']) ?></td>
                                <td><?= htmlspecialchars($det['confidence_lvl']) ?>%</td>
                                <td><?= htmlspecialchars($det['uploaded_by']) ?></td>
                                <td>
                                  <a href="analytics.php?detection_id=<?= htmlspecialchars($det['detection_id']) ?>">View</a>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                
                <!-- 📊 Real-Time Detection Results Tab -->
                <div id="results" class="tab-section">
                  <h2 class="imgdet-section-title">Real-Time Detection Results</h2>
                  <p class="imgdet-section-subtitle">
                    View and manage all detections captured during live monitoring sessions
                  </p>

                  <div class="imgdet-realtime-dashboard">
                    <!-- Left Column -->
                    <div class="imgdet-left-column">
                      <div class="imgdet-stats-container">
                        <div class="imgdet-stat-card">
                          <h4>Total Detections Recorded</h4>
                          <p class="stat-value"><?= array_sum(array_column($realtime_detections, 'total_detections')) ?: 0; ?></p>
                        </div>
                        <div class="imgdet-stat-card">
                          <h4>Average Accuracy</h4>
                          <?php 
                            $avgAcc = count($realtime_detections) ? 
                              array_sum(array_column($realtime_detections, 'accuracy')) / count($realtime_detections) : 0;
                          ?>
                          <p class="stat-value"><?= number_format($avgAcc, 1) ?>%</p>
                        </div>
                      </div>

                      <div class="imgdet-table-box">
                        <div class="imgdet-search-bar">
                          <label for="date">Search by date</label>
                          <input type="date" id="date" value="<?= date('Y-m-d'); ?>">
                        </div>

                        <div class="imgdet-table-container">
                          <table>
                            <thead>
                              <tr>
                                <th>Date and Time</th>
                                <th>Status</th>
                                <th>Camera Location</th>
                                <th>Accuracy</th>
                                <th>Details</th>
                              </tr>
                            </thead>
                            <tbody id="realtimeTableBody">
                              <?php if (empty($realtime_detections)): ?>
                                <tr><td colspan="5" style="text-align:center;">No real-time detection records found.</td></tr>
                              <?php else: ?>
                                <?php foreach ($realtime_detections as $real): ?>
                                  <tr data-date="<?= date('Y-m-d', strtotime($real['timestamp'])) ?>">
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($real['timestamp']))) ?></td>
                                    <td><?= htmlspecialchars($real['camera_status']) ?></td>
                                    <td><?= htmlspecialchars($real['camera_name']) ?></td>
                                    <td><?= htmlspecialchars(number_format($real['accuracy'], 1)) ?>%</td>
                                    <td><a href="realtime_view.php?id=<?= htmlspecialchars($real['realtime_id']) ?>">View</a></td>
                                  </tr>
                                <?php endforeach; ?>
                              <?php endif; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>

                   <!-- Right Column -->
                  <div class="imgdet-right-column">

                   <div class="imgdet-chart-card"> 
                    <div class="imgdet-chart-header"> 
                      <h4>Litter Distribution</h4> 
                      <select> 
                        <option>Monthly</option> 
                        <option>Weekly</option> 
                        <option>Yearly</option> 
                      </select> </div> 
                      <!-- 🟩 New container for layout --> 
                       <div class="pie-wrapper"> 
                        <canvas id="litterDistributionChart">

                        </canvas> 
                      </div> 
                    </div>

                    <!-- 🗺️ HEATMAP -->
                    <div class="imgdet-chart-card">
                      <h4>Litter Hotspots</h4>
                      <div id="litterHotspotMap" style="width:100%; height:250px; border-radius:10px; overflow:hidden;"></div>
                      <p class="imgdet-chart-footer">
                        Real-Time Detection powered by <br />
                        <em>LitterLens AI Model v1</em>
                      </p>
                    </div>

                  </div>

                  </div>
                </div>
                <!-- ✅ End of Real-Time Detection Tab -->

            </div>
            <!-- ✅ End of IMAGE TAB -->


            <!-- ==============================================                 
                  ✅   ANALYTICS SECTION
            ============================================== -->
            <section id="analytics" class="tab-content">
              <h2 class="a-title">Analytics Overview</h2>

              <!-- KPI Cards -->
              <div class="a-overview">
                <div class="a-card">
                  <h3>Total Detections</h3>
                  <div class="a-value"><?= number_format($total_detections) ?></div>
                </div>
                <div class="a-card">
                  <h3>Users</h3>
                  <div class="a-value"><?= number_format($total_users_summary) ?></div>
                </div>
                <div class="a-card">
                  <h3>Reports Today</h3>
                  <div class="a-value"><?= number_format($reports_today_summary) ?></div>
                </div>
                <div class="a-card">
                  <h3>Accuracy</h3>
                  <div class="a-value"><?= number_format($accuracy_summary, 2) ?>%</div>
                </div>
              </div>

              <!-- Charts Grid -->
              <div class="a-grid">

                <!-- 🥧 PIE CHART: Detections by Category -->
                <div class="a-box">
                  <h3>Detections by Category</h3>
                  <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                  </div>
                </div>

                <!-- 📍 GEO MAP: Pinpoint Locations -->
                <div class="a-box">
                  <h3>Geospatial Mapping</h3>
                  <div id="a-map" style="width: 100%; height: 350px; border-radius: 10px;"></div>
                  <p style="text-align: center; font-size: 13px; color: #555; margin-top: 5px;">
                    Showing uploaded image coordinates
                  </p>
                </div>

                <!-- 📈 LINE CHART: Litter Trends -->
                <div class="a-box" style="grid-column: span 2;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Litter Trends</h3>
                    <select id="trendFilterAnalytics" class="a-dropdown">

                      <option value="day" selected>Daily</option>
                      <option value="month" >Monthly</option>
                      <option value="year">Yearly</option>
                    </select>
                  </div>

                  <div class="chart-container" style="position: relative; height: 400px;">
                    <div id="lineChartLoader" class="chart-loader">Loading data...</div>
                    <canvas id="lineChart" style="display:none;"></canvas>
                  </div>
                </div>

              </div>
            </section>


            <!-- ==============================================                 
                          ✅   TEAM SECTION    
                          ============================================== -->
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

                <div class="user-grid">
                  <p id="no-results" style="text-align:center; margin-top:15px; display:none;">No matching users found.</p>

                  
                  <?php if (!empty($admins_list)): ?>
                    <?php foreach ($admins_list as $user): 
                      $isCurrentAdmin = ($user['admin_id'] == $_SESSION['admin_id']);
                    ?>
                      <div class="user-card <?= $isCurrentAdmin ? 'disabled-admin' : '' ?>">
                        <div class="user-menu">
                          <button class="menu-btn" <?= $isCurrentAdmin ? 'disabled' : '' ?>>⋮</button>
                          <div class="menu-options">
                            <button <?= $isCurrentAdmin ? 'disabled' : '' ?>>View</button>
                            <button <?= $isCurrentAdmin ? 'disabled' : '' ?>>Edit</button>
                            <button class="danger" <?= $isCurrentAdmin ? 'disabled' : '' ?>>Delete</button>
                          </div>
                        </div>

                        <img src="<?= htmlspecialchars($user['profile_pic']) ?>" alt="User Avatar" class="user-avatar">

                        <h3><?= htmlspecialchars($user['name']) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                        <span class="role"><?= htmlspecialchars($user['role']) ?></span>

                        <div class="user-contact">
                          <button class="contact-btn email-btn" 
                            onclick="<?= $isCurrentAdmin ? 'return false;' : "window.location='mailto:" . htmlspecialchars($user['email']) . "'" ?>" 
                            <?= $isCurrentAdmin ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-envelope"></i> Email
                          </button>
                          <button class="contact-btn call-btn" <?= $isCurrentAdmin ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-phone"></i> Call
                          </button>
                        </div>

                        <?php if ($isCurrentAdmin): ?>
                          <div class="self-label">You</div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p style="text-align:center; width:100%; margin-top: 20px;">No users found.</p>
                  <?php endif; ?>
                </div>


            </div>



            <!-- ==============================================                 
                          ✅   LOGS SECTION    
                          ============================================== -->
            <div id="logs" class="tab-content">
                <h2 class="l-title">Activity Logs</h2>

                <!-- 🔍 SEARCH BAR -->
                <div class="logs-search">
                    <input type="text" placeholder="Search activity..." id="logSearchInput">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>

                <!-- 📊 SUMMARY CARD -->
                <div class="activity-card">
                    <div class="activity-section left">
                        <i class="fa-regular fa-clock"></i>
                        <span><strong><?= $actions_today ?></strong> Actions Today</span>
                    </div>
                    <div class="divider"></div>
                    <div class="activity-section right">
                        <i class="fa-regular fa-user"></i>
                        <div class="most-active">
                            <span class="label">Most Active:</span>
                            <span class="user"><?= htmlspecialchars($most_active_admin) ?></span>
                        </div>
                    </div>
                </div>

                <!-- 📅 FILTERS + DOWNLOAD -->
                <div class="logs-container">
                    <div class="logs-header">
                        <div class="logs-filters">
                            <!-- Date Range Picker -->
                            <div class="date-range">
                                <i class="fa-regular fa-calendar"></i>
                                <input type="text" name="daterange" value="<?= date('m/01/Y') ?> - <?= date('m/d/Y') ?>" />
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
                                  <li data-value="login">Login</li>
                                </ul>
                            </div>
                        </div>

                        <button class="download-btn">Download Logs (.pdf)</button>
                    </div>

                    <!-- 📋 LOGS TABLE -->
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
                            <?php if (!empty($activity_logs)): ?>
                                <?php foreach ($activity_logs as $log): ?>
                                    <tr>
                                        <td><?= date('m/d/Y H:i', strtotime($log['timestamp'])) ?></td>
                                        <td><?= htmlspecialchars($log['admin_name'] ?? 'Unknown') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($log['action'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars($log['affected_table'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                        <td class="status <?= strtolower($log['log_status']) === 'success' ? 'success' : 'fail' ?>">
                                            <?= htmlspecialchars($log['log_status'] ?? 'Success') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#999;">No activity logs available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- 🔢 PAGINATION -->
                     <div id="pagination" class="pagination-controls" style="margin-top:15px; text-align:center;">
                    <div class="pagination">
                        <span id="prevPage">&lt; Previous</span>
                        <span id="pageInfo">Page 1 of 1</span>
                        <span id="nextPage">Next &gt;</span>
                    </div>
                    </div>
                </div>
            </div>

    
            <!-- ==============================================                 
                          ✅ REAL-TIME DETECTION SECTION 
                        ============================================== -->
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



            <!-- ==============================================                 
                          ✅  SETTINGS SECTION 
                          ============================================== -->
          <div id="settings" class="tab-content">
              <h2 class="s-title">Settings</h2>
            

<div class="settings-card1">
  <h3>Profile</h3>

  <div class="profile-pic-container">
    <img src="<?= htmlspecialchars($admin_profile['profile_pic']) ?>" alt="Profile Picture" class="profile-pic" />
    <div class="overlay">
      <i class="fa-solid fa-camera"></i>
      <input type="file" id="upload" accept="image/*" style="opacity: 0;" />
    </div>
  </div>

  <!-- Crop Modal -->
  <div id="cropModal" class="modal">
    <div class="modal-content">
      <h3>Adjust your photo</h3>
      <div class="crop-container">
        <img id="cropImage" style="max-width: 100%; display: block;" />
      </div>
      <div class="modal-actions">
        <button id="cancelCrop">Cancel</button>
        <button id="confirmCrop">Confirm Crop</button>
      </div>
    </div>
  </div>

  <div class="detail-columns">
    <div class="details1">
      <div class="dt-group">
        <label for="name">Full Name</label>
        <input type="text" id="name" value="<?= htmlspecialchars($admin_profile['name']) ?>" />
      </div>

      <div class="dt-group">
        <label for="email">Email</label>
        <input type="email" id="email" value="<?= htmlspecialchars($admin_profile['email']) ?>" />
      </div>

      <div class="dt-group">
        <label for="password">Password</label>
        <input type="password" id="password" value="" />
      </div>
    </div>

    <div class="details2">
      <div class="dt-group">
        <label for="contact_number">Contact Number</label>
        <input type="text" id="contact_number" value="<?= htmlspecialchars($admin_profile['contact_number'] ?? '') ?>" />
        <p class="contact-description">
          <i class="fa-solid fa-triangle-exclamation warning-icon"></i>
          Please add your contact number for administrative verification and important updates.
        </p>
      </div>

      <div class="dt-group">
        <label for="role">Role</label>
        <input type="text" id="role" value="<?= htmlspecialchars($admin_profile['role']) ?>" />
      </div>

      <div class="dt-group">
        <label for="conpassword">Confirm Password</label>
        <input type="password" id="conpassword" value="" />
      </div>
    </div>
  </div>

  <div class="settings-btn-container">
    <button class="edit-profile-btn">Edit Profile</button>
    <button class="cancel-profile-btn">Cancel</button>
    <button class="settings-save-btn">Save Changes</button>
  </div>
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

    
        
    <script>
      const detections        = <?= $heatmap_json ?? '[]' ?>;
      const trendLabels       = <?= $trend_labels ?? '[]' ?>;
      const trendData         = <?= $trend_data_json ?? '[]' ?>;
      const realtimePoints    = <?= json_encode($realtime_coords, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE); ?>;

      // 🧾 Activity Logs
      const activityLogs      = <?= $logs_json ?>;
      const actionsToday      = <?= $actions_today_json ?>;
      const mostActiveAdmin   = <?= $most_active_admin_json ?>;

      const litterLabels      = <?= json_encode(json_decode($litter_labels, true), JSON_UNESCAPED_UNICODE); ?>;
      const litterValues      = <?= json_encode(json_decode($litter_data, true), JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE); ?>;

      const realtimeLabels    = <?= $realtime_labels ?>;
      const realtimeValues    = <?= $realtime_data ?>;

      const imageCoordinates = <?= $heatmap_json ?>; // From PHP

      
      // ✅ Use new names for the line chart data
      const lineChartLabels = <?= $trend_labels ?? '[]' ?>;
      const lineChartData = <?= $trend_data_json ?? '[]' ?>;

      const ADMIN_ID = "<?= htmlspecialchars($admin_profile['admin_id']) ?>";


    </script>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="../js/admin_realtime.js"></script>
    <script src="../js/admin_Dashboard.js"></script>
    <script src="../js/admin_IMGandDet.js"></script>
    <script src="../js/admin_analytics.js"></script>
    <script src="../js/admin_Team.js"></script>
    <script src="../js/admin_ActivityLogs.js"></script>
    <script src="../js/admin_settings.js"></script>
    


</body>

</html>