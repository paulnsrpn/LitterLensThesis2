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
                    <!-- 🟩 Custom File Button -->
                    <input type="file" id="admin-upload-input" accept="image/*" hidden multiple>

                    <!-- 🟩 Analyze Button -->
                    <button id="admin-upload-btn" class="btn-analyze" disabled>
                      <i class="fa-solid fa-magnifying-glass"></i> Analyze
                    </button>
                  </div>
                </div>

                <!-- ============================== -->
                <!-- 🔹 Detection History Section -->
                <!-- ============================== -->
                <div class="history-box">
                  <div class="history-header">
                    <h3>Detection History (Admin Uploads)</h3>
                    <button class="delete-btn">
                      <i class="fa-solid fa-trash"></i> Delete
                    </button>
                  </div>

                  <div class="table-container">
                    <table>
                      <thead>
                        <tr>
                          <th><input type="checkbox" disabled></th>
                          <th>Date</th>
                          <th>Image</th>
                          <th>Litter Type</th>
                          <th>Accuracy</th>
                          <th>Uploaded By</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (empty($admin_detections)): ?>
                          <tr>
                            <td colspan="6" style="text-align:center;">No detection records found.</td>
                          </tr>
                        <?php else: ?>
                          <?php foreach ($admin_detections as $det): ?>
                            <tr data-detection-id="<?= htmlspecialchars($det['detection_id']) ?>">
                              <td><input type="checkbox"></td>
                              <td><?= htmlspecialchars($det['date']) ?></td>
                              <td>
                                <img src="<?= htmlspecialchars($det['image_url']) ?>"
                                    width="70" height="70"
                                    style="border-radius:8px;object-fit:cover;">
                              </td>
                              <td><?= htmlspecialchars($det['litter_name']) ?></td>
                              <td><?= htmlspecialchars($det['confidence_lvl']) ?>%</td>
                              <td><?= htmlspecialchars($det['uploaded_by']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <!-- ✅ End of Detections Tab -->


              <!-- 📊 Real-Time Detection Results Tab -->
              <div id="results" class="tab-section">
                <h2 class="imgdet-section-title">Real-Time Detection Results</h2>
                <p class="imgdet-section-subtitle">
                  View and manage all detections captured during live monitoring sessions
                </p>

                <div class="imgdet-realtime-dashboard">

                  <!-- 🧭 Left Column -->
                  <div class="imgdet-left-column">

                    <!-- 📊 Stats -->
                    <div class="imgdet-stats-container">
                      <div class="imgdet-stat-card">
                        <h4>Total Detections Recorded</h4>
                        <p class="stat-value">
                          <?= array_sum(array_column($realtime_detections, 'total_detections')) ?: 0; ?>
                        </p>
                      </div>

                      <div class="imgdet-stat-card">
                        <h4>Average Accuracy</h4>
                        <?php 
                          $avgAcc = count($realtime_detections)
                            ? array_sum(array_column($realtime_detections, 'accuracy')) / count($realtime_detections)
                            : 0;
                        ?>
                        <p class="stat-value"><?= number_format($avgAcc, 1) ?>%</p>
                      </div>
                    </div>

                    <!-- 🗓️ Table -->
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
                              <!-- <th>Details</th> -->
                            </tr>
                          </thead>
                          <tbody id="realtimeTableBody">
                            <?php if (empty($realtime_detections)): ?>
                              <tr>
                                <td colspan="5" style="text-align:center;">No real-time detection records found.</td>
                              </tr>
                            <?php else: ?>
                              <?php foreach ($realtime_detections as $real): ?>
                                <tr data-date="<?= date('Y-m-d', strtotime($real['timestamp'])) ?>">
                                  <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($real['timestamp']))) ?></td>
                                  <td><?= htmlspecialchars($real['camera_status']) ?></td>
                                  <td><?= htmlspecialchars($real['camera_name']) ?></td>
                                  <td><?= htmlspecialchars(number_format($real['accuracy'], 1)) ?>%</td>
                                  <!-- <td><a href="realtime_view.php?id=<?= htmlspecialchars($real['realtime_id']) ?>">View</a></td> -->
                                </tr>
                              <?php endforeach; ?>
                            <?php endif; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                  <!-- ✅ End Left Column -->

                  <!-- 📈 Right Column -->
                  <div class="imgdet-right-column">

                    <!-- 📊 LINE CHART -->
                    <div class="imgdet-chart-card">
                      <div class="imgdet-chart-header">
                        <h4>Litter Type Trends</h4>
                        <select id="litterTrendFilter" class="a-dropdown">
                          <option value="day" selected>Daily</option>
                          <option value="month">Monthly</option>
                          <option value="year">Yearly</option>
                        </select>
                      </div>

                      <div class="chart-container" style="position: relative; height: 300px;">
                        <div id="litterTrendLoader" class="chart-loader">Loading data...</div>
                        <canvas id="litterTrendChart" style="display:none;"></canvas>
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
                  <!-- ✅ End Right Column -->

                </div>
                <!-- ✅ End of imgdet-realtime-dashboard -->
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
                                <option value="month">Monthly</option>
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
                        TEAM SECTION    
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
                <p id="no-results" style="text-align:center; margin-top:15px; display:none;">
                  No matching users found.
                </p>

                <?php if (!empty($admins_list)): ?>
                  <?php foreach ($admins_list as $user): 
                    $isCurrentAdmin = ($user['admin_id'] == $_SESSION['admin_id']);
                    $isSuperAdmin = ($_SESSION['admin_id'] == 1 || strtolower($_SESSION['role']) === 'admin'); 
                  ?>
                    <div class="user-card <?= $isCurrentAdmin ? 'disabled-admin' : '' ?>">

                      <!-- ⋮ Menu -->
                      <div class="user-menu">
                        <div class="tooltip-wrapper">
                          <button class="menu-btn" <?= !$isSuperAdmin ? 'disabled' : '' ?>>⋮</button>
                          <?php if (!$isSuperAdmin): ?>
                            <span class="no-access-tooltip">No access</span>
                          <?php endif; ?>
                        </div>

                        <div class="menu-options">
                          <button class="btn-edit"
                                  data-id="<?= $user['admin_id'] ?>"
                                  data-name="<?= htmlspecialchars($user['name']) ?>"
                                  data-email="<?= htmlspecialchars($user['email']) ?>"
                                  data-role="<?= htmlspecialchars($user['role']) ?>"
                                  <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                            Edit
                          </button>

                          <button class="btn-delete danger"
                                  data-id="<?= $user['admin_id'] ?>"
                                  data-name="<?= htmlspecialchars($user['name']) ?>"
                                  <?= (!$isSuperAdmin || $isCurrentAdmin) ? 'disabled' : '' ?>>
                            Delete
                          </button>
                        </div>
                      </div>

                      <!-- Avatar + Info -->
                      <img src="<?= htmlspecialchars($user['profile_pic']) ?>" 
                          alt="User Avatar" 
                          class="user-avatar">

                      <h3><?= htmlspecialchars($user['name']) ?></h3>
                      <p><?= htmlspecialchars($user['email']) ?></p>
                      <span class="role"><?= htmlspecialchars($user['role']) ?></span>

                      <!-- Email + Call buttons -->
                      <div class="user-contact">
                        <button class="contact-btn email-btn"
                                data-email="<?= htmlspecialchars($user['email']) ?>"
                                data-name="<?= htmlspecialchars($user['name']) ?>"
                                <?= empty($user['email']) ? 'disabled' : '' ?>>
                          <i class="fa-solid fa-envelope"></i> Email
                        </button>

                        <div class="tooltip-wrapper">
                          <button class="contact-btn call-btn"
                                  data-phone="<?= htmlspecialchars($user['contact_number'] ?? '') ?>"
                                  <?= empty($user['contact_number']) ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-phone"></i> Call
                          </button>
                          <?php if (empty($user['contact_number'])): ?>
                            <span class="no-access-tooltip">No contact number</span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <?php if ($isCurrentAdmin): ?>
                        <div class="self-label">You</div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p style="text-align:center; width:100%; margin-top: 20px;">
                    No users found.
                  </p>
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
                                <li data-value="Added">Added</li>
                                <li data-value="Updated">Updated</li>
                                <li data-value="Deleted">Deleted</li>
                                <li data-value="Login">Login</li>
                              </ul>
                            </div>
                        </div>

                       <button class="download-btn" id="exportExcelBtn">
                        <i class="fa-solid fa-file-excel"></i> Export to Excel
                       </button>


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
                                       <td class="log-status <?= strtolower($log['log_status']) === 'success' ? 'success' : 'fail' ?>">
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
                      <div id="realtimeStatus" class="realtime-status idle">
                        <i class="fa-solid fa-circle"></i> Idle
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
            ✅ SETTINGS SECTION 
            ============================================== -->
            <div id="settings" class="tab-content">

                <h2 class="s-title">Settings</h2>

                <!-- =====================
                    PROFILE SECTION
                ====================== -->
                <div class="settings-card1">
                    <h3>Profile</h3>

                    <!-- Profile Image -->
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

                    <!-- Profile Fields -->
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

                            <?php
                                $is_super_admin = ($_SESSION['admin_id'] == 1 || strtolower($_SESSION['role']) === 'admin');
                                $disabled_attr = $is_super_admin ? '' : 'disabled';
                            ?>

                            <div class="dt-group">
                                <label for="role">Role</label>
                                <input type="text" id="role" value="<?= htmlspecialchars($admin_profile['role']) ?>" <?= $disabled_attr ?> />
                            </div>

                            <div class="dt-group">
                                <label for="conpassword">Confirm Password</label>
                                <input type="password" id="conpassword" value="" />
                            </div>
                        </div>
                    </div>

                    <!-- Profile Buttons -->
                    <div class="settings-btn-container">
                        <button class="edit-profile-btn">Edit Profile</button>
                        <button class="cancel-profile-btn">Cancel</button>
                        <button class="settings-save-btn">Save Changes</button>
                    </div>
                </div>

                <!-- =====================
                    MODEL MANAGEMENT
                ====================== -->
                <div class="settings-card2">
                    <h3>Model Management</h3>

                    <!-- Active Model -->
                    <div class="model-section">
                        <label class="section-title">Active Model</label>
                        <input type="text" id="activeModel" class="model-input" placeholder="e.g. LitterLens_CNN v1" />
                        <p class="field-description">Select which model should be used for detection.</p>
                    </div>

                    <!-- Upload Model -->
                    <div class="model-section upload-model-section">
                        <label class="section-title">Upload New Model (.pt)</label>
                        <div class="upload-inline">
                            <input type="file" id="modelFile" accept=".pt" class="file-input" />
                            <button id="uploadModelBtn" class="model-upload-btn">Upload Model</button>
                        </div>
                        <p class="field-description">Upload a new trained model file.</p>
                    </div>

                    <!-- Models Table -->
                    <div class="table-container">
                        <table class="model-table" id="modelTable">
                            <thead>
                                <tr>
                                    <th>Model Name</th>
                                    <th>Version</th>
                                    <th>Accuracy</th>
                                    <th>Uploaded on</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <!-- Buttons -->
                    <div class="settings-btn-container">
                        <button id="editModelBtn" class="model-edit-btn">Edit</button>
                        <button id="saveModelBtn" class="model-save-btn">Save</button>
                        <button id="cancelModelBtn" class="model-cancel-btn">Cancel</button>
                    </div>
                </div>

            </div>


                                      
            <!-- 📝 EDIT MEMBER MODAL -->
            <div id="editMemberModal" class="modal-overlay" style="display:none;">
              <div class="modal-content" style="text-align: left;">
                <h3 style="text-align: center;">Edit Member</h3>
                <label>Name</label>
                <input type="text" id="editName" class="modal-input">
                <label>Email</label>
                <input type="email" id="editEmail" class="modal-input">
                <label>Role</label>
                <input type="text" id="editRole" class="modal-input">
                <div class="modal-actions">
                  <button id="saveEditBtn" class="btn-primary">Save Changes</button>
                  <button id="cancelEditBtn" class="btn-secondary">Cancel</button>
                </div>
              </div>
            </div>

            <!-- 🗑️ DELETE CONFIRMATION MODAL -->
            <div id="deleteConfirmModal" class="modal-overlay" style="display:none;">
              <div class="modal-content">
                <h3>Delete Member</h3>
                <p>Are you sure you want to delete <strong id="deleteName"></strong>?</p>
                <div class="modal-actions">
                  <button id="confirmDeleteBtn" class="btn-danger">Yes, Delete</button>
                  <button id="cancelDeleteBtn" class="btn-secondary">Cancel</button>
                </div>
              </div>
            </div>


            <script>
            document.addEventListener("DOMContentLoaded", () => {
              // ===============================
              // 🎛️ ELEMENT SELECTION
              // ===============================
              const links = document.querySelectorAll(".tab-link");
              const contents = document.querySelectorAll(".tab-content");
              const settingsLink = document.querySelector(".a-menu a[href='#']");
              const dashboard = document.getElementById("dashboard");
              const imageDetection = document.getElementById("image-detection");

              // ===============================
              // ⚙️ FUNCTION REGISTRY
              // ===============================
              const tabActions = {
                dashboard: {
                  onEnter: () => {
                    console.log("📊 Dashboard initialized");
                    if (window.initDashboard) window.initDashboard(); // Optional hook
                  },
                  onExit: () => console.log("📊 Dashboard stopped"),
                },
                realtime: {
                  onEnter: () => {
                    console.log("🎥 Starting Realtime Detection...");
                    if (window.startRealtime) window.startRealtime();
                  },
                  onExit: () => {
                    console.log("🛑 Stopping Realtime Detection...");
                    if (window.stopRealtime) window.stopRealtime();
                  },
                },
                "image-detection": {
                  onEnter: () => {
                    console.log("🖼️ Image & Detection initialized");
                    if (window.initImageDetection) window.initImageDetection();
                  },
                  onExit: () => {
                    console.log("🖼️ Image & Detection kept active (not stopped)");
                    // intentionally not stopping
                  },
                },
                analytics: {
                  onEnter: () => console.log("📈 Analytics loaded"),
                  onExit: () => console.log("📈 Analytics closed"),
                },
                settings: {
                  onEnter: () => console.log("⚙️ Settings opened"),
                  onExit: () => console.log("⚙️ Settings closed"),
                },
              };

              // ===============================
              // 🏠 INITIAL LOAD → DASHBOARD DEFAULT
              // ===============================
              contents.forEach(content => (content.style.display = "none"));

              if (dashboard) {
                dashboard.style.display = "block"; // ✅ Show Dashboard first
                if (tabActions.dashboard?.onEnter) tabActions.dashboard.onEnter();
              }

              let activeTab = "dashboard"; // ✅ Default active tab

              // ===============================
              // 🧭 SIDEBAR TAB HANDLING
              // ===============================
              links.forEach(link => {
                link.addEventListener("click", () => {
                  const targetId = link.getAttribute("data-tab");
                  if (targetId === activeTab) return; // avoid redundant reload

                  // Run exit function of current tab
                  if (tabActions[activeTab]?.onExit) tabActions[activeTab].onExit();

                  // Reset visuals
                  links.forEach(l => l.classList.remove("active"));
                  contents.forEach(c => (c.style.display = "none"));

                  // Activate clicked tab
                  link.classList.add("active");
                  const targetSection = document.getElementById(targetId);
                  if (targetSection) targetSection.style.display = "block";

                  // Run new tab’s onEnter
                  if (tabActions[targetId]?.onEnter) tabActions[targetId].onEnter();

                  activeTab = targetId;
                });
              });

              // ===============================
              // ⚙️ SETTINGS LINK HANDLING
              // ===============================
              if (settingsLink) {
                settingsLink.addEventListener("click", e => {
                  e.preventDefault();

                  // Run exit of previous tab
                  if (tabActions[activeTab]?.onExit) tabActions[activeTab].onExit();

                  // Reset active visuals
                  links.forEach(l => l.classList.remove("active"));
                  contents.forEach(c => (c.style.display = "none"));

                  // Show settings
                  const settingsSection = document.getElementById("settings");
                  if (settingsSection) settingsSection.style.display = "block";

                  if (tabActions.settings?.onEnter) tabActions.settings.onEnter();
                  activeTab = "settings";
                });
              }
            });
            </script>


        
            <script>
              // ===============================
              // 🧠 GLOBAL ADMIN SESSION DATA
              // ===============================
              window.currentAdminId   = <?= json_encode($_SESSION['admin_id'] ?? null) ?>;
              window.currentAdminName = <?= json_encode($_SESSION['admin_name'] ?? 'Admin') ?>;
              window.currentAdminRole = <?= json_encode($_SESSION['role'] ?? 'admin') ?>;

              // ✅ Also sync with localStorage for external JS
              if (window.currentAdminId) {
                localStorage.setItem("admin_id", window.currentAdminId);
                localStorage.setItem("admin_name", window.currentAdminName);
                localStorage.setItem("role", window.currentAdminRole);
                console.log(`🔐 Admin session synced: ${window.currentAdminName} (ID: ${window.currentAdminId})`);
              } else {
                console.warn("⚠️ No active admin session found.");
              }

                
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
              // ✅ Single, correct definition
              const ADMIN_ID = Number(<?= json_encode($_SESSION['admin_id'] ?? 0) ?>);
              const ADMIN_ROLE = <?= json_encode(strtolower($_SESSION['role'] ?? '')); ?>;


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
    
            <!-- 🌀 EXPORT LOADING OVERLAY -->
            <div id="exportLoading">
              <div class="export-spinner"></div>
              <div class="export-message">Exporting logs... Please wait</div>
            </div>

</body>

</html>