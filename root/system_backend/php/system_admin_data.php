<?php
// =======================================================
// ✅ LOGIN CHECK & INITIALIZATION
// =======================================================
require_once __DIR__ . '/system_config.php';

// =======================================================
// ✅ GLOBAL SESSION INIT (Only Once)
// =======================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🧠 Helper for future use
function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Redirect if admin not logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header("Location: /LitterLensThesis2/root/system_frontend/php/index_login.php");
    exit;
}
// =======================================================
// 🧾 SESSION VARIABLES — Auto-sync with latest Supabase data
// =======================================================
$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_name'] ?? '';

if ($admin_id) {
    // 🟢 Always check if Supabase has a newer admin_name
    try {
        $check_profile = supabaseRequest(
            'GET',
            'admin',
            null,
            'select=admin_name&admin_id=eq.' . $admin_id
        );

        if (is_array($check_profile) && !empty($check_profile[0]['admin_name'])) {
            $latest_name = $check_profile[0]['admin_name'];

            // Update session only if name changed
            if ($latest_name !== $_SESSION['admin_name']) {
                $_SESSION['admin_name'] = $latest_name;
                $admin_name = $latest_name;
            }
        }
    } catch (Throwable $e) {
        error_log("⚠️ Failed to sync admin_name from Supabase: " . $e->getMessage());
    }
}

// =======================================================
// 🔧 INITIAL VARIABLES
// =======================================================
$debug_logs       = [];
$total_images     = 0;
$total_detections = 0;
$average_accuracy = 0.0;
$recent_activity  = [];

// =======================================================
// 🛠️ HELPER FUNCTIONS
// =======================================================

/**
 * Add a debug message with timestamp to logs.
 */
function debugLog(&$logs, $message)
{
    $logs[] = "[" . date("H:i:s") . "] " . $message;
}

/**
 * Get full Supabase public image URL.
 */
function getImageUrl($filename)
{
    $baseUrl = 'https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/images/';
    return $baseUrl . rawurlencode($filename);
}

/**
 * Get admin image URL or fallback to default avatar.
 */
function getAdminImageUrl($filename)
{
    $baseUrl = 'https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/admin/';
    return !empty($filename)
        ? $baseUrl . rawurlencode($filename)
        : '../imgs/default-avatar.png';
}

// =======================================================
// 🧾 ACTIVITY LOGGING
// =======================================================

/**
 * Insert a new activity record into Supabase `activity_logs` table.
 */
function logActivity($admin_id, $admin_name, $action, $affected_table, $description, $status = 'Success')
{
    // Always log time in Asia/Manila timezone
    date_default_timezone_set('Asia/Manila');
    $timestamp = date('Y-m-d H:i:s');

    $payload = [
        'admin_id'       => $admin_id,
        'admin_name'     => $admin_name,
        'action'         => $action,
        'affected_table' => $affected_table,
        'description'    => $description,
        'log_status'     => $status,
        'timestamp'      => $timestamp // 👈 Force local timestamp (Philippines time)
    ];

    $response = supabaseRequest('POST', 'activity_logs', json_encode($payload));

    if (isset($response['error'])) {
        error_log("⚠️ Failed to insert activity log: " . json_encode($response));
    } else {
        error_log("🟢 Activity logged: " . json_encode($payload));
    }
}

// =======================================================
// ✅ AUTO-LOG LOGIN (ONCE PER SESSION)
// =======================================================
if (!isset($_SESSION['logged_once'])) {
    logActivity($admin_id, $admin_name, 'Login', 'admin', 'Logged into the system');
    $_SESSION['logged_once'] = true;
}

// =======================================================
// 📸 IMAGES TABLE FUNCTIONS
// =======================================================

/**
 * Get total image count from Supabase `images` table.
 */
function getImagesCount(&$logs)
{
    $response = supabaseRequest('GET', 'images', null, 'select=count');

    if (is_array($response) && isset($response[0]['count'])) {
        debugLog($logs, "🟢 Total images: {$response[0]['count']}");
        return (int)$response[0]['count'];
    }

    debugLog($logs, "❌ Failed to retrieve image count: " . json_encode($response));
    return 0;
}

// =======================================================
// 🗑 DETECTIONS TABLE FUNCTIONS
// =======================================================

/**
 * Get total number of detections (sum of `quantity`).
 */
function getDetectionsCount(&$logs)
{
    $response = supabaseRequest('GET', 'detections', null, 'select=quantity');

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve detections: " . json_encode($response));
        return 0;
    }

    $total = array_sum(array_column($response, 'quantity'));
    debugLog($logs, "🟢 Total detections: $total");
    return $total;
}

/**
 * Compute average detection accuracy from confidence levels.
 */
function getDetectionsAverageAccuracy(&$logs)
{
    $response = supabaseRequest('GET', 'detections', null, 'select=confidence_lvl');

    if (!is_array($response) || empty($response)) {
        debugLog($logs, "ℹ️ No confidence data found. Accuracy = 0%");
        return 0.0;
    }

    $values = array_column($response, 'confidence_lvl');
    $avg = array_sum($values) / count($values);

    debugLog($logs, "🟢 Average detection accuracy: {$avg}%");
    return $avg;
}

/**
 * Get recent detections (today or last 7 days).
 */
function getRecentDetections(&$logs, $timeFilter = 'today')
{
    $today = date('Y-m-d');
    $queryFilter = '';

    switch ($timeFilter) {
        case 'today':
            $queryFilter = '&date=eq.' . $today;
            break;
        case 'last7':
            $start = date('Y-m-d', strtotime('-7 days'));
            $queryFilter = '&date=gte.' . $start;
            break;
    }

    $query = 'select=detection_id,date,detection_time,image_id,images:image_id(image_id,imagefile_name)'
        . $queryFilter
        . '&order=date.desc,detection_time.desc&limit=100';

    $response = supabaseRequest('GET', 'detections', null, $query);

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve recent detections: " . json_encode($response));
        return [];
    }

    $unique = [];
    foreach ($response as $row) {
        $imgId = $row['images']['image_id'] ?? null;
        if ($imgId && !isset($unique[$imgId])) {
            $unique[$imgId] = [
                'date'         => $row['date'],
                'time'         => $row['detection_time'],
                'filename'     => $row['images']['imagefile_name'],
                'image_id'     => $imgId,
                'detection_id' => $row['detection_id']
            ];
        }
    }

    // Sort by most recent
    usort($unique, fn($a, $b) =>
        strtotime($b['date'] . ' ' . $b['time']) <=> strtotime($a['date'] . ' ' . $a['time'])
    );

    $cleaned = array_slice(array_values($unique), 0, 7);

    debugLog($logs, "🟢 Recent detections (filter: $timeFilter): " . count($cleaned) . " rows");
    return $cleaned;
}

// =======================================================
// 👥 USER COUNT
// =======================================================
function getUsersCount(&$logs)
{
    $response = supabaseRequest('GET', 'users', null, 'select=count');

    if (is_array($response) && isset($response[0]['count'])) {
        $count = (int)$response[0]['count'];
        debugLog($logs, "🟢 Total users (count query): {$count}");
        return $count;
    }

    $response = supabaseRequest('GET', 'users', null, 'select=user_id');
    if (is_array($response)) {
        $count = count($response);
        debugLog($logs, "🟢 Total users (manual count): {$count}");
        return $count;
    }

    debugLog($logs, "❌ Failed to retrieve users count: " . json_encode($response));
    return 0;
}

// =======================================================
// 📋 REPORTS & SUMMARIES
// =======================================================
function getReportsTodayCount(&$logs)
{
    $today = date('Y-m-d');
    $response = supabaseRequest('GET', 'detections', null, 'select=count&date=eq.' . $today);

    if (is_array($response) && isset($response[0]['count'])) {
        debugLog($logs, "🟢 Reports today: {$response[0]['count']}");
        return (int)$response[0]['count'];
    }

    return 0;
}

/**
 * Get litter detection summary by type.
 */
function getLitterTypeSummary(&$logs)
{
    $response = supabaseRequest('GET', 'detections', null, 'select=quantity,litter_types:littertype_id(littertype_name)');

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve litter summary: " . json_encode($response));
        return [];
    }

    $summary = [];
    foreach ($response as $row) {
        $type = $row['litter_types']['littertype_name'] ?? 'Unknown';
        $summary[$type] = ($summary[$type] ?? 0) + (int)$row['quantity'];
    }

    debugLog($logs, "🟢 Litter summary: " . json_encode($summary));
    return $summary;
}

// =======================================================
// 🌍 HEATMAP COORDINATES
// =======================================================
function getDetectionCoordinates(&$logs)
{
    $response = supabaseRequest('GET', 'images', null, 'select=latitude,longitude');

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve coordinates: " . json_encode($response));
        return [];
    }

    $coords = [];
    foreach ($response as $row) {
        $lat = $row['latitude'] ?? null;
        $lng = $row['longitude'] ?? null;

        if ($lat !== null && $lng !== null) {
            $coords[] = [(float)$lat, (float)$lng, 1.0];
        }
    }

    debugLog($logs, "🟢 Heatmap coordinates loaded: " . count($coords) . " points");
    return $coords;
}

// =======================================================
// 📊 TREND FILTER (DAY / MONTH / YEAR)
// =======================================================
$trendFilter = $_GET['trend_filter'] ?? 'month';

/**
 * Get litter detection trends grouped by day, month, or year.
 */
function getLitterTrendsDataFiltered(&$logs, $filter)
{
    $response = supabaseRequest('GET', 'detections', null, 'select=quantity,date,littertype_id');

    if (!is_array($response) || empty($response)) {
        debugLog($logs, "❌ No detection data found.");
        return [];
    }

    $trends = [];
    foreach ($response as $row) {
        $type = 'Type ' . ($row['littertype_id'] ?? 'Unknown');
        $date = strtotime($row['date']);
        $qty  = (int)($row['quantity'] ?? 0);

        switch ($filter) {
            case 'day':
                $key = date('M d, Y', $date);
                break;
            case 'year':
                $key = date('Y', $date);
                break;
            default:
                $key = date('M Y', $date);
                break;
        }

        $trends[$type][$key] = ($trends[$type][$key] ?? 0) + $qty;
    }

    debugLog($logs, "🟢 Trends grouped by {$filter}: " . json_encode($trends));
    return $trends;
}

// =======================================================
// ⚡ REAL-TIME LITTER SUMMARY
// =======================================================
function getRealtimeLitterSummary(&$logs)
{
    // Fetch summarized litter counts with litter type names
    $response = supabaseRequest(
        'GET',
        'realtime_litter_summary',
        null,
        'select=litter_count,litter_types:littertype_id(littertype_name)'
    );

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve realtime litter summary: " . json_encode($response));
        return [];
    }

    // Aggregate litter counts by type
    $summary = [];
    foreach ($response as $row) {
        $type = $row['litter_types']['littertype_name'] ?? 'Unknown';
        $summary[$type] = ($summary[$type] ?? 0) + (int)$row['litter_count'];
    }

    debugLog($logs, "🟢 Realtime litter summary: " . json_encode($summary));
    return $summary;
}

// =======================================================
// 📈 LINE CHART DATA (BY LITTER TYPE NAME)
// =======================================================
function getLitterLineChartData(&$logs, $filter)
{
    $response = supabaseRequest(
        'GET',
        'detections',
        null,
        'select=quantity,date,litter_types:littertype_id(littertype_name)'
    );

    if (!is_array($response) || empty($response)) {
        debugLog($logs, "❌ No detection data found for line chart.");
        return [];
    }

    $trends = [];

    foreach ($response as $row) {
        $type = $row['litter_types']['littertype_name'] ?? 'Unknown';
        $date = strtotime($row['date']);
        $qty  = (int)($row['quantity'] ?? 0);

        // Group by time filter (day / month / year)
        switch ($filter) {
            case 'day':
                $key = date('M d, Y', $date);
                break;
            case 'year':
                $key = date('Y', $date);
                break;
            default:
                $key = date('M Y', $date);
                break;
        }

        $trends[$type][$key] = ($trends[$type][$key] ?? 0) + $qty;
    }

    debugLog($logs, "🟢 Litter line chart data ({$filter}): " . json_encode($trends));
    return $trends;
}

// =======================================================
// 📊 REALTIME LITTER TREND (BY DAY / MONTH / YEAR)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'realtime_trend') {
    header('Content-Type: application/json');
    $filter = $_GET['filter'] ?? 'day';

    $response = supabaseRequest(
        'GET',
        'realtime_detections',
        null,
        'select=top_detected_litter,total_detections,date'
    );

    if (!is_array($response) || empty($response)) {
        echo json_encode(['labels' => [], 'datasets' => []]);
        exit;
    }

    $trend = [];

    foreach ($response as $row) {
        $type = $row['top_detected_litter'] ?? 'Unknown';
        $count = (int)($row['total_detections'] ?? 0);
        $date = strtotime($row['date']);

        switch ($filter) {
            case 'year': $key = date('Y', $date); break;
            case 'month': $key = date('M Y', $date); break;
            default: $key = date('M d, Y', $date); break;
        }

        $trend[$type][$key] = ($trend[$type][$key] ?? 0) + $count;
    }

    // Sort by date keys
    $allDates = [];
    foreach ($trend as $arr) $allDates = array_merge($allDates, array_keys($arr));
    $labels = array_values(array_unique($allDates));
    sort($labels);

    $datasets = [];
    foreach ($trend as $type => $data) {
        $points = [];
        foreach ($labels as $lbl) $points[] = $data[$lbl] ?? 0;
        $datasets[] = [
            'label' => $type,
            'data' => $points
        ];
    }

    echo json_encode([
        'labels' => $labels,
        'datasets' => $datasets
    ]);
    exit;
}



// =======================================================
// 🚀 EXECUTION: DASHBOARD DATA GENERATION
// =======================================================


// Litter trends
$litter_trends = getLitterTrendsDataFiltered($debug_logs, $trendFilter);

// =======================================================
// 📊 LOG DASHBOARD VIEW ONLY ONCE PER SESSION (NO DUPLICATE)
// =======================================================
ensureSession();

// Make sure session persists properly
if (empty($_SESSION['dashboard_view_logged']) || $_SESSION['dashboard_view_logged'] !== $admin_id) {
    logActivity($admin_id, $admin_name, 'Viewed', 'dashboard', 'Accessed analytics dashboard');
    $_SESSION['dashboard_view_logged'] = $admin_id; // store which admin logged it
}


// Prepare trend labels (latest 10 periods)
$all_periods = [];
foreach ($litter_trends as $months) {
    $all_periods = array_merge($all_periods, array_keys($months));
}
$all_periods = array_values(array_unique($all_periods));
sort($all_periods);
$all_periods = array_slice($all_periods, -10);

$trend_labels = json_encode($all_periods);
$trend_data   = [];

foreach ($litter_trends as $type => $months) {
    $trend_data[] = [
        'label' => $type,
        'data'  => array_map(fn($m) => $months[$m] ?? 0, $all_periods)
    ];
}
$trend_data_json = json_encode($trend_data);

// =======================================================
// 📊 DASHBOARD METRICS
// =======================================================
$total_images          = getImagesCount($debug_logs);
$total_detections      = getDetectionsCount($debug_logs);
$average_accuracy      = $total_detections > 0 ? getDetectionsAverageAccuracy($debug_logs) : 0.0;

$timeFilter            = $_GET['time_filter'] ?? 'today';
$recent_activity       = getRecentDetections($debug_logs, $timeFilter);

$litter_summary        = getLitterTypeSummary($debug_logs);
$litter_labels         = json_encode(array_keys($litter_summary));
$litter_data           = json_encode(array_values($litter_summary));

$total_users_summary   = getUsersCount($debug_logs);
$reports_today_summary = getReportsTodayCount($debug_logs);
$accuracy_summary      = getDetectionsAverageAccuracy($debug_logs);

$debug_json            = json_encode($debug_logs);

$heatmap_points        = getDetectionCoordinates($debug_logs);
$heatmap_json          = json_encode($heatmap_points, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);

$realtime_summary      = getRealtimeLitterSummary($debug_logs);
$realtime_labels       = json_encode(array_keys($realtime_summary));
$realtime_data         = json_encode(array_values($realtime_summary));

// =======================================================
// 📈 LINE CHART (BASED ON LITTER NAMES)
// =======================================================
$litter_linechart = getLitterLineChartData($debug_logs, $trendFilter);

$all_periods = [];
foreach ($litter_linechart as $months) {
    $all_periods = array_merge($all_periods, array_keys($months));
}
$all_periods = array_values(array_unique($all_periods));
sort($all_periods);
$all_periods = array_slice($all_periods, -10);

$trend_labels = json_encode($all_periods);
$trend_data   = [];

foreach ($litter_linechart as $type => $months) {
    $trend_data[] = [
        'label' => $type,
        'data'  => array_map(fn($m) => $months[$m] ?? 0, $all_periods)
    ];
}
$trend_data_json = json_encode($trend_data);


// =======================================================
// 📡 FETCH REAL-TIME DETECTIONS (JOIN ADMIN INFO)
// =======================================================
$realtime_detections = [];

$realtimeResp = supabaseRequest(
    'GET',
    'realtime_detections',
    null,
    'select=realtime_id,date,timestamp,camera_name,camera_status,total_detections,detection_accuracy,admin:admin_id(admin_name)&order=date.desc'
);

if (is_array($realtimeResp)) {
    foreach ($realtimeResp as $row) {
        $realtime_detections[] = [
            'realtime_id'      => $row['realtime_id'],
            'date'             => $row['date'],
            'timestamp'        => $row['timestamp'] ?? '',
            'camera_name'      => $row['camera_name'] ?? 'Unknown Camera',
            'camera_status'    => $row['camera_status'] ?? 'Idle',
            'total_detections' => $row['total_detections'] ?? 0,
            'accuracy'         => $row['detection_accuracy'] ?? 0,
            'admin_name'       => $row['admin']['admin_name'] ?? 'N/A'
        ];
    }
}

// =======================================================
// 🗺️ FETCH REAL-TIME DETECTION COORDINATES
// =======================================================
$realtime_coords = [];

try {
    $realtime_response = supabaseRequest(
        'GET',
        'realtime_detections',
        null,
        'select=realtime_id,latitude,longitude,camera_name,camera_status,detection_accuracy,timestamp'
    );

    if (is_array($realtime_response)) {
        foreach ($realtime_response as $row) {
            $lat = isset($row['latitude']) ? (float)$row['latitude'] : null;
            $lng = isset($row['longitude']) ? (float)$row['longitude'] : null;

            if ($lat !== null && $lng !== null) {
                $realtime_coords[] = [
                    'id'        => $row['realtime_id'] ?? null,
                    'lat'       => $lat,
                    'lng'       => $lng,
                    'camera'    => $row['camera_name'] ?? 'Unknown Camera',
                    'status'    => $row['camera_status'] ?? 'Idle',
                    'accuracy'  => $row['detection_accuracy'] ?? 0,
                    'timestamp' => $row['timestamp'] ?? ''
                ];
            }
        }
    } else {
        error_log("⚠️ Supabase returned invalid response for realtime_detections: " . json_encode($realtime_response));
    }
} catch (Exception $e) {
    error_log("⚠️ Error fetching realtime coords: " . $e->getMessage());
}

// =======================================================
// 👥 FETCH ADMIN LIST (TEAM MEMBERS)
// =======================================================
$admins_list = [];

try {
    $admins_response = supabaseRequest(
        'GET',
        'admin',
        null,
        'select=admin_id,admin_name,email,role,profile_pic,contact_number'
    );

    if (is_array($admins_response)) {
        foreach ($admins_response as $adm) {
            $admins_list[] = [
                'admin_id'    => $adm['admin_id'],
                'name'        => $adm['admin_name'],
                'email'       => $adm['email'],
                'role'        => $adm['role'],
                'contact_number' => $adm['contact_number'] ?? '',
                'profile_pic' => getAdminImageUrl($adm['profile_pic'] ?? null)
            ];
        }
    } else {
        debugLog($debug_logs, "❌ Failed to fetch admins: " . json_encode($admins_response));
    }
} catch (Exception $e) {
    debugLog($debug_logs, "⚠️ Error fetching admins: " . $e->getMessage());
}

// =======================================================
// 📋 FETCH ACTIVITY LOGS
// =======================================================
$logs_response = supabaseRequest(
    'GET',
    'activity_logs',
    null,
    'select=admin_name,action,affected_table,description,log_status,timestamp&order=timestamp.desc'
);

$activity_logs     = [];
$actions_today     = 0;
$most_active_admin = "N/A";

if (is_array($logs_response)) {
    $activity_logs = $logs_response;

    // Count today's logs
    $today = date('Y-m-d');
    $today_logs = array_filter($activity_logs, fn($log) =>
        str_starts_with($log['timestamp'], $today)
    );
    $actions_today = count($today_logs);

    // Determine most active admin
    $admin_actions = [];
    foreach ($activity_logs as $log) {
        $admin = $log['admin_name'] ?? 'Unknown';
        $admin_actions[$admin] = ($admin_actions[$admin] ?? 0) + 1;
    }

    arsort($admin_actions);
    $most_active_admin = array_key_first($admin_actions) ?? 'N/A';
} else {
    error_log("⚠️ Failed to fetch activity logs: " . json_encode($logs_response));
}


// =======================================================
// 👤 FETCH LOGGED-IN ADMIN PROFILE
// =======================================================
$admin_profile = [];

try {
    $profile_response = supabaseRequest(
        'GET',
        'admin',
        null,
       'select=admin_id,admin_name,email,role,password,profile_pic,contact_number&admin_id=eq.' . $admin_id

    );

    if (is_array($profile_response) && !empty($profile_response)) {
        $data = $profile_response[0];

        $admin_profile = [
            'admin_id'    => $data['admin_id'],
            'name'        => $data['admin_name'],
            'email'       => $data['email'],
            'role'        => $data['role'],
            'contact_number' => $data['contact_number'] ?? '',
            // 🟢 show empty placeholder instead of hash
            'password'    => '',
            'profile_pic' => getAdminImageUrl($data['profile_pic'])
        ];
    } else {
        $admin_profile = [
            'admin_id'    => $admin_id,
            'name'        => $admin_name,
            'email'       => '',
            'role'        => '',
            'password'    => '',
            'contact_number' => '',
            'profile_pic' => '../imgs/default-avatar.png'
        ];
    }
} catch (Exception $e) {
    error_log("⚠️ Error loading admin profile: " . $e->getMessage());
    $admin_profile = [
        'admin_id'    => $admin_id,
        'name'        => $admin_name,
        'email'       => '',
        'role'        => '',
        'password'    => '',
        'contact_number' => '',
        'profile_pic' => '../imgs/default-avatar.png'
    ];
}

function supabaseUploadFile($bucket, $fileName, $filePath, $contentType = 'application/octet-stream') {
    $apiKey = SUPABASE_KEY;
    $projectUrl = 'https://ksbgdgqpdoxabdefjsin.supabase.co';
    $uploadUrl = $projectUrl . '/storage/v1/object/' . $bucket . '/' . $fileName;

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'apikey: ' . $apiKey,
        'Content-Type: ' . $contentType,
    ];

    $fileData = file_get_contents($filePath);
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("🟢 Supabase Upload [$bucket]: HTTP $status → $uploadUrl");

    if ($err) {
        return ['error' => $err];
    }

    if ($status >= 400) {
        return ['error' => 'Upload failed', 'response' => $response];
    }

    return [
        'success' => true,
        'url' => "https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/{$bucket}/{$fileName}"
    ];
}




// =======================================================
// 📊 PREPARE ACTIVITY LOGS FOR FRONTEND
// =======================================================
$logs_json              = json_encode($activity_logs, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
$actions_today_json     = json_encode($actions_today);
$most_active_admin_json = json_encode($most_active_admin);

// =======================================================
// 🔁 AJAX HANDLERS (TREND, TOTALS, RECENT ACTIVITY)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'trend') {
    $trendFilter = $_GET['trend_filter'] ?? 'month';
    $litter_linechart = getLitterLineChartData($debug_logs, $trendFilter);

    $all_periods = [];
    foreach ($litter_linechart as $months) {
        $all_periods = array_merge($all_periods, array_keys($months));
    }
    $all_periods = array_values(array_unique($all_periods));
    sort($all_periods);
    $all_periods = array_slice($all_periods, -10);

    $trend_labels = array_values($all_periods);
    $trend_data = [];

    foreach ($litter_linechart as $type => $months) {
        $trend_data[] = [
            'label' => $type,
            'data'  => array_map(fn($m) => $months[$m] ?? 0, $all_periods)
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'labels'   => $trend_labels,
        'datasets' => $trend_data
    ]);
    exit;
}

// =======================================================
// 📊 AJAX: TOTAL DETECTION TRENDS (BAR CHART)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'trend_total') {
    $trendFilter = $_GET['trend_filter'] ?? 'month';
    $response = supabaseRequest('GET', 'detections', null, 'select=quantity,date');

    if (!is_array($response) || empty($response)) {
        header('Content-Type: application/json');
        echo json_encode(['labels' => [], 'datasets' => []]);
        exit;
    }

    $totals = [];
    foreach ($response as $row) {
        $date = strtotime($row['date']);
        $qty  = (int)($row['quantity'] ?? 0);

        switch ($trendFilter) {
            case 'day':
                $key = date('M d, Y', $date);
                break;
            case 'year':
                $key = date('Y', $date);
                break;
            default:
                $key = date('M Y', $date);
                break;
        }

        $totals[$key] = ($totals[$key] ?? 0) + $qty;
    }

    ksort($totals);
    $labels = array_keys($totals);
    $data   = array_values($totals);

    header('Content-Type: application/json');
    echo json_encode([
        'labels'   => $labels,
        'datasets' => [[
            'label' => 'Total Litter Detections',
            'data'  => $data
        ]]
    ]);
    exit;
}



// =======================================================
// 📅 AJAX: RECENT ACTIVITY (TODAY / LAST 7 DAYS)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'recent_activity') {
    $filter = $_GET['time_filter'] ?? 'today';
    $recent_activity = getRecentDetections($debug_logs, $filter);

    $data = array_map(fn($r) => [
        'date'      => $r['date'],
        'time'      => $r['time'],
        'image_url' => getImageUrl($r['filename']),
    ], $recent_activity);

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}


// =======================================================
// 🧾 AJAX: UPDATE ADMIN PROFILE (All fields + image)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'update_profile_all') {
    ob_clean();
    header('Content-Type: application/json');

    // Read incoming data
    $input = json_decode(file_get_contents('php://input'), true);
    $admin_id = $input['admin_id'] ?? '';

    if (!$admin_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing admin ID']);
        exit;
    }
    

    // 🟢 Fetch current admin data
    $oldData = supabaseRequest('GET', 'admin', null, 'select=admin_name,email,role,contact_number,password,profile_pic&admin_id=eq.' . $admin_id);

    if (!is_array($oldData) || empty($oldData[0])) {
        http_response_code(404);
        echo json_encode(['error' => 'Admin not found']);
        exit;
    }

    $old = $oldData[0];

    // 🟡 Prepare updated data

$update = [
    'admin_name'     => $input['name'] ?: $old['admin_name'],
    'email'          => $input['email'] ?: $old['email'],
    'role'           => $input['role'] ?: $old['role'],
    'contact_number' => $input['contact_number'] ?: $old['contact_number']
];



// Hash password if provided
if (!empty($input['password'])) {
    $update['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
} else {
    $update['password'] = $old['password']; // keep current hash
}

    // 🟤 Handle profile image (base64 upload)
    $newProfileUrl = null;
    if (!empty($input['profile_pic'])) {
        $decoded = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['profile_pic']));
        $fileName = 'admin_' . $admin_id . '_' . time() . '.png';
        $filePath = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($filePath, $decoded);

        $upload = supabaseUploadFile('admin', $fileName, $filePath, 'image/png');
        if (!isset($upload['error'])) {
            $update['profile_pic'] = $fileName;
            $newProfileUrl = 'https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/admin/' . rawurlencode($fileName);
        } else {
            error_log("⚠️ Upload failed: " . json_encode($upload));
        }
    }

    // 🔵 Send PATCH request to Supabase
    $response = supabaseRequest('PATCH', 'admin', json_encode($update), 'admin_id=eq.' . $admin_id);
    error_log("🧩 Supabase PATCH Response: " . json_encode($response));

    // 🔴 Validate response from Supabase
    if (isset($response['error']) || (isset($response['message']) && stripos($response['message'], 'error') !== false)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Supabase update failed',
            'details' => $response
        ]);
        exit;
    }

    // 🟢 Log activity
    logActivity($admin_id, $update['admin_name'], 'Update', 'admin', 'Updated profile info or photo');

    echo json_encode([
        'success' => true,
        'new_profile_pic_url' => $newProfileUrl
    ]);
    exit;
}

// =====// =======================================================
// ✏️ AJAX: EDIT MEMBER (Only Admin 1 or 'admin')
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'edit_member') {
    ob_clean();
    header('Content-Type: application/json');

    ensureSession();

    $session_role = strtolower($_SESSION['role'] ?? ''); // prevent undefined warning

    // ✅ Permission check
    if ($_SESSION['admin_id'] != 1 && $session_role !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // ✅ Read input
    $input = json_decode(file_get_contents('php://input'), true);
    $admin_id = $input['admin_id'] ?? '';
    if (!$admin_id) {
        echo json_encode(['success' => false, 'error' => 'Missing admin_id']);
        exit;
    }

    $update = [
        'admin_name' => $input['name'] ?? '',
        'email'      => $input['email'] ?? '',
        'role'       => $input['role'] ?? ''
    ];

    // ✅ Update Supabase
    $response = supabaseRequest('PATCH', 'admin', json_encode($update), 'admin_id=eq.' . $admin_id);

    if (isset($response['error'])) {
        echo json_encode(['success' => false, 'error' => $response['error']['message'] ?? 'Update failed']);
    } else {
        logActivity($_SESSION['admin_id'], $_SESSION['admin_name'] ?? 'Unknown', 'Edit', 'admin', "Edited member ID $admin_id");
        echo json_encode(['success' => true]);
    }

    exit;
}


// =======================================================
// 🗑️ AJAX: DELETE MEMBER (Only Admin 1 or 'admin')
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete_member') {
    header('Content-Type: application/json');
    ensureSession();

    if ($_SESSION['admin_id'] != 1 && strtolower($_SESSION['role']) !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $admin_id = $input['admin_id'] ?? '';

    if (!$admin_id) {
        echo json_encode(['error' => 'Missing admin_id']);
        exit;
    }

    if ($admin_id == $_SESSION['admin_id']) {
        echo json_encode(['error' => 'You cannot delete your own account.']);
        exit;
    }

    $response = supabaseRequest('DELETE', 'admin', null, 'admin_id=eq.' . $admin_id);
    if (isset($response['error'])) {
        echo json_encode(['error' => $response['error']['message']]);
    } else {
        logActivity($_SESSION['admin_id'], $_SESSION['admin_name'], 'Delete', 'admin', "Deleted member ID $admin_id");
        echo json_encode(['success' => true]);
    }
    exit;
}




// =======================================================
// 🔄 AJAX: FETCH UPDATED ADMIN LIST (for Team Tab refresh)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_admins') {
    header('Content-Type: text/html; charset=UTF-8');
    ensureSession();

    // Same logic as your initial $admins_list fetch
    $admins_list = [];

    try {
        $admins_response = supabaseRequest(
            'GET',
            'admin',
            null,
            'select=admin_id,admin_name,email,role,profile_pic,contact_number'
        );

        if (is_array($admins_response)) {
            foreach ($admins_response as $adm) {
                $admins_list[] = [
                    'admin_id'        => $adm['admin_id'],
                    'name'            => $adm['admin_name'],
                    'email'           => $adm['email'],
                    'role'            => $adm['role'],
                    'contact_number'  => $adm['contact_number'] ?? '',
                    'profile_pic'     => getAdminImageUrl($adm['profile_pic'] ?? null)
                ];
            }
        }
    } catch (Exception $e) {
        echo "<p style='text-align:center;color:red;'>Error loading admins: {$e->getMessage()}</p>";
        exit;
    }

    // ✅ Return refreshed HTML (same structure as your original user-grid)
    if (!empty($admins_list)) {
        foreach ($admins_list as $user) {
            $isCurrentAdmin = ($user['admin_id'] == $_SESSION['admin_id']);
            $isSuperAdmin = ($_SESSION['admin_id'] == 1 || strtolower($_SESSION['role']) === 'admin');

            echo "<div class='user-card " . ($isCurrentAdmin ? 'disabled-admin' : '') . "'>";
            
            // ⋮ Menu
            echo "<div class='user-menu'>
                    <div class='tooltip-wrapper'>
                      <button class='menu-btn' " . (!$isSuperAdmin ? 'disabled' : '') . ">⋮</button>";
            if (!$isSuperAdmin) echo "<span class='no-access-tooltip'>No access</span>";
            echo "  </div>
                    <div class='menu-options'>
                      <button class='btn-edit'
                        data-id='{$user['admin_id']}'
                        data-name='" . htmlspecialchars($user['name']) . "'
                        data-email='" . htmlspecialchars($user['email']) . "'
                        data-role='" . htmlspecialchars($user['role']) . "'
                        " . (!$isSuperAdmin ? 'disabled' : '') . ">Edit</button>
                      <button class='btn-delete danger'
                        data-id='{$user['admin_id']}'
                        data-name='" . htmlspecialchars($user['name']) . "'
                        " . ((!$isSuperAdmin || $isCurrentAdmin) ? 'disabled' : '') . ">Delete</button>
                    </div>
                  </div>";

            // Avatar + Info
            echo "<img src='" . htmlspecialchars($user['profile_pic']) . "' alt='User Avatar' class='user-avatar'>
                  <h3>" . htmlspecialchars($user['name']) . "</h3>
                  <p>" . htmlspecialchars($user['email']) . "</p>
                  <span class='role'>" . htmlspecialchars($user['role']) . "</span>";

            // Email + Call Buttons
            echo "<div class='user-contact'>
                    <button class='contact-btn email-btn'
                      data-email='" . htmlspecialchars($user['email']) . "'
                      data-name='" . htmlspecialchars($user['name']) . "'
                      " . (empty($user['email']) ? 'disabled' : '') . ">
                      <i class='fa-solid fa-envelope'></i> Email
                    </button>
                    <div class='tooltip-wrapper'>
                      <button class='contact-btn call-btn'
                        data-phone='" . htmlspecialchars($user['contact_number'] ?? '') . "'
                        " . (empty($user['contact_number']) ? 'disabled' : '') . ">
                        <i class='fa-solid fa-phone'></i> Call
                      </button>";
            if (empty($user['contact_number'])) {
                echo "<span class='no-access-tooltip'>No contact number</span>";
            }
            echo "    </div>
                  </div>";

            if ($isCurrentAdmin) echo "<div class='self-label'>You</div>";
            echo "</div>"; // user-card
        }
    } else {
        echo "<p style='text-align:center; width:100%; margin-top: 20px;'>No users found.</p>";
    }

    exit;
}

// =======================================================
// 📤 AJAX: EXPORT ACTIVITY LOGS TO CSV (Excel-Compatible)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'export_logs_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Activity_Logs.csv"');

    $input = json_decode(file_get_contents('php://input'), true);
    $logs = $input['logs'] ?? [];

    // Open output buffer for direct download
    $output = fopen('php://output', 'w');

    // CSV Header
    fputcsv($output, ['Date', 'Admin', 'Action', 'Affected Record', 'Description', 'Status']);

    // CSV Rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['date'],
            $log['admin'],
            ucfirst($log['action']),
            $log['affected_record'],
            $log['description'],
            ucfirst($log['status'])
        ]);
    }

    fclose($output);
    exit;

}


if (isset($_GET['ajax']) && $_GET['ajax'] === 'update_profile_secure') {
    ob_clean();
    header('Content-Type: application/json');
    ensureSession();

    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $admin_id = $_SESSION['admin_id'];
    $session_role = strtolower($_SESSION['role'] ?? '');

    // Fetch old data
    $oldData = supabaseRequest('GET', 'admin', null, 'select=admin_name,email,role,contact_number,password,profile_pic&admin_id=eq.' . $admin_id);
    if (!is_array($oldData) || empty($oldData[0])) {
        echo json_encode(['error' => 'Admin not found']);
        exit;
    }
    $old = $oldData[0];

    // Build update payload
    $update = [
        'admin_name'     => $input['name'] ?: $old['admin_name'],
        'email'          => $input['email'] ?: $old['email'],
        'contact_number' => $input['contact_number'] ?: $old['contact_number']
    ];

    // Only Admin 1 or 'admin' can update role
    if ($_SESSION['admin_id'] == 1 || $session_role === 'admin') {
        if (!empty($input['role'])) $update['role'] = $input['role'];
    } else {
        logActivity($_SESSION['admin_id'], $_SESSION['admin_name'], 'Unauthorized Attempt', 'admin', 'Tried to change role');
    }

    // Handle password
    if (!empty($input['password'])) {
        $update['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    } else {
        $update['password'] = $old['password'];
    }

    // Handle image upload
    $newProfileUrl = null;
    if (!empty($input['profile_pic'])) {
        $decoded = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $input['profile_pic']));
        $fileName = 'admin_' . $admin_id . '_' . time() . '.png';
        $filePath = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($filePath, $decoded);

        $upload = supabaseUploadFile('admin', $fileName, $filePath, 'image/png');
        if (!isset($upload['error'])) {
            $update['profile_pic'] = $fileName;
            $newProfileUrl = 'https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/admin/' . rawurlencode($fileName);
        }
    }

    // Save to Supabase
    $response = supabaseRequest('PATCH', 'admin', json_encode($update), 'admin_id=eq.' . $admin_id);
    if (isset($response['error'])) {
        echo json_encode(['error' => 'Update failed', 'details' => $response]);
        exit;
    }

    logActivity($admin_id, $_SESSION['admin_name'], 'Update', 'admin', 'Updated profile (secure)');

    echo json_encode(['success' => true, 'new_profile_pic_url' => $newProfileUrl]);
    exit;
}

// =======================================================
// 📦 FETCH DETECTION HISTORY (Admin Uploads Only)
// =======================================================
ensureSession();

$admin_detections = [];

try {
    // 🧩 Get current admin session info
    $admin_id   = $_SESSION['admin_id'] ?? null;
    $admin_name = $_SESSION['admin_name'] ?? null;

    if (!$admin_name) {
        throw new Exception("Admin not logged in.");
    }

    // 🧾 Supabase view request (ensure `detection_view` exists)
    $response = supabaseRequest(
        'GET',
        'detection_view',
        null,
        'select=detection_id,date,imagefile_name,littertype_name,confidence_lvl,uploaded_by&uploaded_by=eq.' . urlencode($admin_name)
    );

    // 🧩 Parse and build array
    if (is_array($response) && !empty($response)) {
        foreach ($response as $det) {
            $image_url = !empty($det['imagefile_name'])
                ? "https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/images/" . rawurlencode($det['imagefile_name'])
                : "../imgs/no-image.png";

            $admin_detections[] = [
                'detection_id'   => $det['detection_id'] ?? 0,
                'date'           => $det['date'] ?? 'N/A',
                'image_url'      => $image_url,
                'litter_name'    => $det['littertype_name'] ?? 'Unknown',
                'confidence_lvl' => number_format($det['confidence_lvl'] ?? 0, 2),
                'uploaded_by'    => $det['uploaded_by'] ?? 'Unknown'
            ];
        }

        // 🔄 Show newest detections first
        $admin_detections = array_reverse($admin_detections);
    }
} catch (Throwable $e) {
    error_log("⚠️ Error fetching admin detections: " . $e->getMessage());
}



// =======================================================
// 🗑️ AJAX: DELETE DETECTIONS (Admin Only, Stable Version)
// =======================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete_detections') {
    ob_clean();
    header('Content-Type: application/json');
    ensureSession();

    // 🔒 Verify admin session
    if (empty($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Unauthorized: Admin not logged in.']);
        exit;
    }

    $admin_id   = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'] ?? 'Unknown Admin';

    // 🧾 Read incoming JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $ids   = $input['ids'] ?? [];

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['error' => 'No valid detection IDs provided.']);
        exit;
    }

    $deleted = [];
    $failed  = [];

    foreach ($ids as $id) {
        // ✅ Supabase DELETE call (filtered by detection_id)
        $resp = supabaseRequest(
            'DELETE',
            'detections',
            null,
            'detection_id=eq.' . intval($id)
        );

        if (is_array($resp) && !isset($resp['error'])) {
            $deleted[] = $id;
        } else {
            $failed[] = [
                'id' => $id,
                'error' => $resp['error']['message'] ?? json_encode($resp)
            ];
        }
    }

    // ✍️ Log activity
    $actionDesc = count($deleted) > 1
        ? "Deleted detections: " . implode(', ', $deleted)
        : "Deleted detection ID " . ($deleted[0] ?? 'N/A');

    $status = empty($failed) ? 'Success' : 'Partial';
    logActivity($admin_id, $admin_name, 'Delete', 'detections', $actionDesc, $status);

    // 📦 Response JSON
    echo json_encode([
        'success' => empty($failed),
        'deleted' => $deleted,
        'failed'  => $failed,
        'message' => empty($failed)
            ? '✅ All selected detections successfully deleted.'
            : '⚠️ Some records could not be deleted.'
    ]);
    exit;
}




