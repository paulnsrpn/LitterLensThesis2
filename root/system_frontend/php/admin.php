<?php
require_once '../../system_backend/php/system_config.php';

// ===============================
// ✅ LOGIN CHECK
// ===============================
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

$admin_name = $_SESSION['admin_name'] ?? '';
$debug_logs = [];
$total_images = 0;
$total_detections = 0;
$average_accuracy = 0.0;
$recent_activity = [];


// ===============================
// 🛠️ HELPER FUNCTIONS
// ===============================

/**
 * 🪵 Log debug messages with timestamp
 */
function debugLog(&$logs, $message) {
    $logs[] = "[" . date("H:i:s") . "] " . $message;
}

/**
 * 📸 Get total number of images
 */
function getTotalImages(&$logs) {
    $response = supabaseRequest('GET', 'images', null, 'select=count');
    if (is_array($response) && isset($response[0]['count'])) {
        debugLog($logs, "🟢 Total images retrieved: {$response[0]['count']}");
        return (int)$response[0]['count'];
    }
    debugLog($logs, "❌ Failed to retrieve total images. Response: " . json_encode($response));
    return 0;
}

/**
 * 📊 Get total detections (sum of quantity)
 */
function getTotalDetections(&$logs) {
    $response = supabaseRequest('GET', 'detections', null, 'select=quantity');
    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve detections. Response: " . json_encode($response));
        return 0;
    }

    $total = 0;
    foreach ($response as $row) {
        $total += (int)$row['quantity'];
    }

    debugLog($logs, "🟢 Total detections quantity retrieved: $total");
    return $total;
}

/**
 * 📈 Get average accuracy (mean of confidence_lvl)
 */
function getAverageAccuracy(&$logs) {
    $response = supabaseRequest('GET', 'detections', null, 'select=confidence_lvl');
    if (!is_array($response) || count($response) === 0) {
        debugLog($logs, "ℹ️ No confidence levels found, accuracy is 0%");
        return 0.0;
    }

    $total_confidence = 0;
    foreach ($response as $row) {
        $total_confidence += (float)$row['confidence_lvl'];
    }

    $average = $total_confidence / count($response);
    debugLog($logs, "🟢 Average accuracy calculated: {$average}%");
    return $average;
}

/**
 * 🖼 Generate full URL of image from Supabase bucket
 */
function getImageUrl($filename) {
    $baseUrl = 'https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/images/';
    return $baseUrl . rawurlencode($filename);
}
/**
 * 🕒 Get recent activity (last 7 detections with image)
 */
function getRecentActivity(&$logs, $timeFilter = 'today') {
    $today = date('Y-m-d');
    $filter = '';

    switch ($timeFilter) {
        case 'today':
            $filter = '&date=eq.' . $today;
            break;
        case 'last7':
            $start = date('Y-m-d', strtotime('-7 days'));
            $filter = '&date=gte.' . $start;
            break;
    }

    $response = supabaseRequest(
        'GET',
        'detections',
        null,
        'select=detection_id,date,detection_time,image_id,images:image_id(image_id,imagefile_name)&order=date.desc,detection_time.desc&limit=100' . $filter
    );

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve recent activity. Response: " . json_encode($response));
        return [];
    }

    // 🧮 Keep only the latest detection per image_id
    $unique = [];
    foreach ($response as $row) {
        $imgId = $row['images']['image_id'] ?? null;
        if ($imgId && !isset($unique[$imgId])) {
            $unique[$imgId] = [
                'date' => $row['date'],
                'time' => $row['detection_time'],
                'filename' => $row['images']['imagefile_name'],
                'image_id' => $imgId,
                'detection_id' => $row['detection_id']
            ];
        }
    }

    // ⬆️ Sort by date & time
    usort($unique, function ($a, $b) {
        $aDT = strtotime($a['date'] . ' ' . $a['time']);
        $bDT = strtotime($b['date'] . ' ' . $b['time']);
        return $bDT <=> $aDT;
    });

    $cleaned = array_slice(array_values($unique), 0, 7);
    debugLog($logs, "🟢 Unique recent activity retrieved (filter '$timeFilter'): " . count($cleaned) . " rows");
    return $cleaned;
}


/**
 * 📊 Get litter detection counts per type
 */
function getLitterSummary(&$logs) {
    $response = supabaseRequest(
        'GET',
        'detections',
        null,
        'select=quantity,litter_types:littertype_id(littertype_name)'
    );

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve litter summary. Response: " . json_encode($response));
        return [];
    }

    $summary = [];
    foreach ($response as $row) {
        $type = $row['litter_types']['littertype_name'] ?? 'Unknown';
        $qty = (int) $row['quantity'];
        if (!isset($summary[$type])) {
            $summary[$type] = 0;
        }
        $summary[$type] += $qty;
    }

    debugLog($logs, "🟢 Litter summary retrieved: " . json_encode($summary));
    return $summary;
}

/**
 * 📊 Total detections (sum of quantity)
 */
function getTotalDetectionsQty(&$logs) {
    $response = supabaseRequest('GET', 'detections', null, 'select=quantity');
    if (!is_array($response)) return 0;

    $total = 0;
    foreach ($response as $row) {
        $total += (int)$row['quantity'];
    }
    debugLog($logs, "🟢 Total detections: $total");
    return $total;
}

/**
 * 👥 Total users
 */
function getTotalUsers(&$logs) {
    $response = supabaseRequest('GET', 'users', null, 'select=count');
    if (is_array($response) && isset($response[0]['count'])) {
        debugLog($logs, "🟢 Total users: {$response[0]['count']}");
        return (int)$response[0]['count'];
    }
    return 0;
}

/**
 * 📝 Reports Today (detections created today)
 */
function getReportsToday(&$logs) {
    $today = date('Y-m-d');
    $response = supabaseRequest('GET', 'detections', null, 'select=count&date=eq.' . $today);
    if (is_array($response) && isset($response[0]['count'])) {
        debugLog($logs, "🟢 Reports today: {$response[0]['count']}");
        return (int)$response[0]['count'];
    }
    return 0;
}

/**
 * 📈 Average Accuracy (mean confidence_lvl)
 */
function getAverageAccuracyValue(&$logs) {
    $response = supabaseRequest('GET', 'detections', null, 'select=confidence_lvl');
    if (!is_array($response) || count($response) === 0) return 0;

    $total = 0;
    foreach ($response as $row) {
        $total += (float)$row['confidence_lvl'];
    }
    $avg = $total / count($response);
    debugLog($logs, "🟢 Average accuracy: $avg%");
    return $avg;
}


/**
 * 📈 Get litter detection trends grouped by month and litter type
 */
function getLitterTrends(&$logs) {
    // Get all detections with litter type
    $response = supabaseRequest(
        'GET',
        'detections',
        null,
        'select=quantity,date,litter_types:littertype_id(littertype_name)'
    );

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve trends. Response: " . json_encode($response));
        return [];
    }

    $trends = [];

    foreach ($response as $row) {
        $type = $row['litter_types']['littertype_name'] ?? 'Unknown';
        $month = date('M Y', strtotime($row['date']));
        $qty = (int) $row['quantity'];

        if (!isset($trends[$type])) $trends[$type] = [];
        if (!isset($trends[$type][$month])) $trends[$type][$month] = 0;

        $trends[$type][$month] += $qty;
    }

    debugLog($logs, "🟢 Trends data retrieved: " . json_encode($trends));
    return $trends;
}

/**
 * 👥 Fetch all users from Supabase
 */
// $logs = supabaseRequest(
//     'GET',
//     'activity_logs',
//     null,
//     'select=log_id,action,timestamp,admin:admin_id(admin_name,email)&order=timestamp.desc'
// );


// ===============================
// 🚀 EXECUTION
// ===============================
$litter_trends = getLitterTrends($debug_logs);

$all_months = [];
foreach ($litter_trends as $type => $months) {
    foreach (array_keys($months) as $m) {
        if (!in_array($m, $all_months)) $all_months[] = $m;
    }
}
sort($all_months); // chronological order

$trend_labels = json_encode($all_months);
$trend_data = [];

foreach ($litter_trends as $type => $months) {
    $row = [
        'label' => $type,
        'data' => [],
    ];
    foreach ($all_months as $m) {
        $row['data'][] = $months[$m] ?? 0;
    }
    $trend_data[] = $row;
}

$trend_data_json = json_encode($trend_data);








$total_images = getTotalImages($debug_logs);
$total_detections = getTotalDetections($debug_logs);
$average_accuracy = $total_detections > 0 ? getAverageAccuracy($debug_logs) : 0.0;
$debug_json = json_encode($debug_logs);
$timeFilter = $_GET['time_filter'] ?? 'today';
$recent_activity = getRecentActivity($debug_logs, $timeFilter);
$litter_summary = getLitterSummary($debug_logs);
$litter_labels = json_encode(array_keys($litter_summary));
$litter_data = json_encode(array_values($litter_summary));
$total_detections_summary = getTotalDetectionsQty($debug_logs);
$total_users_summary = getTotalUsers($debug_logs);
$reports_today_summary = getReportsToday($debug_logs);
$accuracy_summary = getAverageAccuracyValue($debug_logs);
// $users_list = getUsersList($debug_logs);


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
  <link rel="stylesheet" href="../css/camera.css">>  
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <div class="admin-button">
        <span class="admin-circle"></span>
        <span class="admin-divider"></span>
        <span class="admin-label">
            <p><?php echo htmlspecialchars($admin_name); ?></p>
        </span>
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

        <!-- 🕒 Recent Activity Table -->
        <div class="filter-bar" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
            <h3>Recent Activity</h3>
            <form method="GET" style="display: flex; gap: 10px;">
                <select name="time_filter" onchange="this.form.submit()">
                    <option value="today" <?= ($_GET['time_filter'] ?? 'today') == 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="last7" <?= ($_GET['time_filter'] ?? '') == 'last7' ? 'selected' : '' ?>>Last 7 Days</option>
                </select>
            </form>
        </div>

        <div class="dashboard-activity">
            <h3>Recent Activity</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Image</th>
                        <th>Action</th>
                        <!-- <th>Details</th> -->
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
                            <td>
                                <!-- <a href="#"
                                     class="analytics-btn"
                                     data-litter="<?= htmlspecialchars($row['litterType'] ?? 'N/A') ?>"
                                     data-quantity="<?= htmlspecialchars($row['quantity'] ?? '0') ?>">
                                    View Analytics
                                </a> -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;">No recent activity</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div> <!-- end of .dashboard-container -->
</div> <!-- end of #dashboard -->












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
            <div class="a-value"><?= number_format($total_detections_summary) ?></div>
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
        <?php if (!empty($users_list)): ?>
            <?php foreach ($users_list as $user): ?>
                <div class="user-card">
                    <div class="user-menu">
                        <button class="menu-btn">⋮</button>
                        <div class="menu-options">
                            <button>View</button>
                            <button>Edit</button>
                            <button class="danger">Delete</button>
                        </div>
                    </div>

                    Default avatar used since avatar_url is not in the table 
                    <img src="../imgs/default-avatar.png" alt="User Avatar" class="user-avatar">

                    <h3><?= htmlspecialchars($user['name']) ?></h3>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                    <span class="role"><?= htmlspecialchars($user['role']) ?></span>

                    <div class="user-contact">
                        <button class="contact-btn email-btn" onclick="window.location='mailto:<?= htmlspecialchars($user['email']) ?>'">
                            <i class="fa-solid fa-envelope"></i> Email
                        </button>
                        <button class="contact-btn call-btn">
                            <i class="fa-solid fa-phone"></i> Call
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; width:100%; margin-top: 20px;">No users found.</p>
        <?php endif; ?>
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
                            <h2>Live Feed:</h2>
                                    <select id="camera-select">
                                        
                                        <option value="local" selected>Local Camera</option>
                                        <!-- <option value="">IP Camera 1</option> -->
                                    </select>
                            <div class="camera-header">
                                <div class="location-details">
                                    <h3>📍 Location Details</h3>
                                    <p>Latitude: <span id="latitude">--</span></p>
                                    <p>Longitude: <span id="longitude">--</span></p>
                                </div>
                                <div class="cam-controls">
                                    <select id="threshold-select">  
                                          <option value="0.25"> Threshold: 25%</option>
                                          <option value="0.50"> Threshold: 50%</option>
                                          <option value="0.75"> Threshold: 75%</option>
                                          <option value="1.00"> Threshold: 100%</option>
                                    </select>
                                    <button id="startBtn"><i class="fa-solid fa-play"></i> Start</button>
                                    <button id="stopBtn"><i class="fa-solid fa-stop"></i> Stop</button>
                                    <button id="refresh-btn"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
                                </div>
                            </div>
                             <img id="liveFeed" alt="Live Detection Preview" style="display:none; max-width:100%; border:2px solid #ccc; border-radius:8px;">
                            <!-- <video id="liveVideo" autoplay playsinline></video> -->
                             <!-- <div id="noDisplay">⚠️ No camera detected. Please check your device.</div> -->
                            <div class="detection-status">
                                <p>Time: <span class="time">1hr 10mins 30s</span></p>
                                <!-- <p>Status: 
                                    <span class="status">
                                        <i class="fa-solid fa-circle-notch fa-spin"></i> Idle
                                    </span>
                                </p> -->
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
                                <p class="value" id="totalDetections">0</p>
                            </div>
                            <div class="stats-item">
                                <p class="label">Top Detected Litter</p>
                                <p class="value" id="topLitter">--</p>
                            </div>
                            <div class="stats-item">
                                <p class="label">Detection Speed</p>
                                <p class="value" id="detectionSpeed">0.0s/frame</p>
                            </div>
                            <div class="stats-item">
                                <p class="label">Camera Status</p>
                                <p class="value active" id="cameraStatus">Idle</p>
                            </div>
                            <div class="stats-item">
                                <p class="label">Detection Accuracy</p>
                                <p class="value" id="detectionAccuracy">0%</p>
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
                        <tbody id="litterTableBody">
                            <!-- dynamically generated -->
                        </tbody>
                    </table>
                </div>
             </div>
        </div>
    </div>






    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../js/admin_realtime.js"></script>
  


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
    // 🟢 Fixed color palette (used for both charts)
    const fixedColors = [
        "#2d6a4f", "#40916c", "#52b788", "#74c69d",
        "#95d5b2", "#b7e4c7", "#d8f3dc", "#74c69d",
        "#52b788", "#40916c", "#2d6a4f"
    ];

    // ======================================================
    // 🥧 PIE CHART
    // ======================================================
    const litterLabels = <?= isset($litter_labels) ? $litter_labels : '[]' ?>;
    const litterData = <?= isset($litter_data) ? $litter_data : '[]' ?>;
    const pieColors = fixedColors.slice(0, litterLabels.length);

    if (Array.isArray(litterLabels) && litterLabels.length > 0) {
        new Chart(document.getElementById("pieChart"), {
            type: "pie",
            data: {
                labels: litterLabels,
                datasets: [{
                    data: litterData,
                    backgroundColor: pieColors,
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
    }

    // ======================================================
    // 📈 LINE CHART
    // ======================================================
    const trendLabels = <?= isset($trend_labels) ? $trend_labels : '[]' ?>;
    const trendDatasetsRaw = <?= isset($trend_data_json) ? $trend_data_json : '[]' ?>;

    let trendDatasets = [];

    if (Array.isArray(trendDatasetsRaw) && trendDatasetsRaw.length > 0) {
        // ✅ Build line datasets from DB
        trendDatasets = trendDatasetsRaw.map((d, i) => ({
            label: d.label,
            data: d.data,
            borderColor: fixedColors[i % fixedColors.length],
            tension: 0.4,
            fill: false,
            pointRadius: 3,
            pointHoverRadius: 5
        }));
    } else {
        // 🪄 Fallback: static data when DB is empty
        trendLabels.push("Jan 2025", "Feb 2025", "Mar 2025", "Apr 2025", "May 2025", "Jun 2025");
        trendDatasets = [
            {
                label: "Biological Debris",
                data: [55, 48, 52, 40, 50, 47],
                borderColor: fixedColors[0],
                tension: 0.4,
                fill: false,
                pointRadius: 3,
                pointHoverRadius: 5
            },
            {
                label: "Plastic",
                data: [50, 42, 48, 41, 45, 44],
                borderColor: fixedColors[1],
                tension: 0.4,
                fill: false,
                pointRadius: 3,
                pointHoverRadius: 5
            }
        ];
    }

    new Chart(document.getElementById("lineChart"), {
        type: "line",
        data: {
            labels: trendLabels,
            datasets: trendDatasets
        },
        options: {
            responsive: true,
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