<?php
// ===============================
// ✅ LOGIN CHECK
// ===============================
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    redirect('/LITTERLENSTHESIS2/root/system_frontend/php/index_login.php');
}

$admin_name       = $_SESSION['admin_name'] ?? '';
$debug_logs       = [];
$total_images     = 0;
$total_detections = 0;
$average_accuracy = 0.0;
$recent_activity  = [];

// ===============================
// 🛠️ HELPER FUNCTIONS
// ===============================

function debugLog(&$logs, $message)
{
    $logs[] = "[" . date("H:i:s") . "] " . $message;
}

function getImageUrl($filename)
{
    $baseUrl = 'https://ksbgdgqpdoxabdefjsin.storage.supabase.co/storage/v1/object/public/images/';
    return $baseUrl . rawurlencode($filename);
}

/* =======================================================
   📸 IMAGES TABLE FUNCTIONS
   ======================================================= */
function getImagesCount(&$logs)
{
    $response = supabaseRequest('GET', 'images', null, 'select=count');
    if (is_array($response) && isset($response[0]['count'])) {
        debugLog($logs, "🟢 Total images: {$response[0]['count']}");
        return (int)$response[0]['count'];
    }
    debugLog($logs, "❌ Failed to retrieve images count: " . json_encode($response));
    return 0;
}

/* =======================================================
   🗑 DETECTIONS TABLE FUNCTIONS
   ======================================================= */
function getDetectionsCount(&$logs)
{
    $response = supabaseRequest('GET', 'detections', null, 'select=quantity');
    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve detections: " . json_encode($response));
        return 0;
    }
    $total = array_sum(array_column($response, 'quantity'));
    debugLog($logs, "🟢 Total detections retrieved: $total");
    return $total;
}

function getDetectionsAverageAccuracy(&$logs)
{
    $response = supabaseRequest('GET', 'detections', null, 'select=confidence_lvl');
    if (!is_array($response) || empty($response)) {
        debugLog($logs, "ℹ️ No confidence data found, accuracy = 0%");
        return 0.0;
    }
    $values = array_column($response, 'confidence_lvl');
    $avg = array_sum($values) / count($values);
    debugLog($logs, "🟢 Average detection accuracy: {$avg}%");
    return $avg;
}

function getRecentDetections(&$logs, $timeFilter = 'today')
{
    $today  = date('Y-m-d');
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

    usort($unique, fn($a, $b) => strtotime($b['date'] . ' ' . $b['time']) <=> strtotime($a['date'] . ' ' . $a['time']));
    $cleaned = array_slice(array_values($unique), 0, 7);

    debugLog($logs, "🟢 Recent detections (filter: $timeFilter): " . count($cleaned) . " rows");
    return $cleaned;
}

/* =======================================================
   🧍 USERS TABLE FUNCTIONS
   ======================================================= */
function getUsersCount(&$logs)
{
    $response = supabaseRequest('GET', 'users', null, 'select=count');
    if (is_array($response) && isset($response[0]['count'])) {
        debugLog($logs, "🟢 Total users: {$response[0]['count']}");
        return (int)$response[0]['count'];
    }
    debugLog($logs, "❌ Failed to retrieve users count: " . json_encode($response));
    return 0;
}

/* =======================================================
   📋 REPORTS & SUMMARIES
   ======================================================= */
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

/* =======================================================
   🌍 HEATMAP COORDINATES
   ======================================================= */
function getDetectionCoordinates(&$logs)
{
    $response = supabaseRequest('GET', 'images', null, 'select=latitude,longitude');

    if (!is_array($response)) {
        debugLog($logs, "❌ Failed to retrieve image coordinates: " . json_encode($response));
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

/* =======================================================
   📊 FILTER HANDLER (Day / Month / Year)
   ======================================================= */
$trendFilter = $_GET['trend_filter'] ?? 'month'; // default

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

    debugLog($logs, "🟢 Litter trends grouped by {$filter}: " . json_encode($trends));
    return $trends;
}

/* =======================================================
   🚀 EXECUTION (Generate Dashboard Data)
   ======================================================= */
$litter_trends = getLitterTrendsDataFiltered($debug_logs, $trendFilter);

// X-Axis Labels
$all_periods = [];
foreach ($litter_trends as $months) {
    $all_periods = array_merge($all_periods, array_keys($months));
}
$all_periods = array_values(array_unique($all_periods));
sort($all_periods);

// Limit to 10 recent
$all_periods = array_slice($all_periods, -10);

$trend_labels = json_encode($all_periods);
$trend_data = [];

foreach ($litter_trends as $type => $months) {
    $trend_data[] = [
        'label' => $type,
        'data'  => array_map(fn($m) => $months[$m] ?? 0, $all_periods)
    ];
}

$trend_data_json = json_encode($trend_data);

// Other Dashboard Metrics
$total_images             = getImagesCount($debug_logs);
$total_detections         = getDetectionsCount($debug_logs);
$average_accuracy         = $total_detections > 0 ? getDetectionsAverageAccuracy($debug_logs) : 0.0;
$timeFilter               = $_GET['time_filter'] ?? 'today';
$recent_activity          = getRecentDetections($debug_logs, $timeFilter);
$litter_summary           = getLitterTypeSummary($debug_logs);
$litter_labels            = json_encode(array_keys($litter_summary));
$litter_data              = json_encode(array_values($litter_summary));
$total_users_summary      = getUsersCount($debug_logs);
$reports_today_summary    = getReportsTodayCount($debug_logs);
$accuracy_summary         = getDetectionsAverageAccuracy($debug_logs);
$debug_json               = json_encode($debug_logs);
$heatmap_points           = getDetectionCoordinates($debug_logs);
$heatmap_json             = json_encode($heatmap_points, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);

?>
