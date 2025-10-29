<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/debugfiles/php_error_log.txt");

require_once __DIR__ . "/system_config.php";
header("Content-Type: application/json");
session_start();

/* ============================================================
   ✅ AUTH CHECK — Ensure Admin is Logged In
   ============================================================ */
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized access. Please log in first."
    ]);
    exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? "Admin";

/* ============================================================
   ✅ READ JSON PAYLOAD FROM FRONTEND
   ============================================================ */
$json = file_get_contents("php://input");
file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "RAW JSON: " . $json . PHP_EOL, FILE_APPEND);
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["success" => false, "error" => "No data received or invalid JSON"]);
    exit;
}

try {
    /* ============================================================
       ✅ PREPARE DATA FOR REALTIME_DETECTIONS
       ============================================================ */
    $currentDate = date("Y-m-d"); // 👈 add date automatically

    $realtimeData = [
        "admin_id" => $admin_id,
        "camera_name" => $data["cameraName"] ?? "Unknown Camera",
        "camera_status" => "Stopped",
        "total_detections" => (int)($data["totalDetections"] ?? 0),
        "top_detected_litter" => $data["topDetectedLitter"] ?? null,
        "detection_speed" => floatval(preg_replace('/[^0-9.]/', '', $data["detectionSpeed"] ?? "0")),
        "detection_accuracy" => floatval($data["detectionAccuracy"] ?? 0),
        "latitude" => $data["latitude"] ?? null,
        "longitude" => $data["longitude"] ?? null,
        "date" => $currentDate  // ✅ new date field for daily reporting
    ];

    file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "Realtime Insert: " . json_encode($realtimeData) . PHP_EOL, FILE_APPEND);

    /* ============================================================
       ✅ INSERT INTO REALTIME_DETECTIONS
       ============================================================ */
    $insertResponse = supabaseRequest("POST", "realtime_detections", $realtimeData);
    file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "Insert Response: " . json_encode($insertResponse) . PHP_EOL, FILE_APPEND);

    if (isset($insertResponse["error"])) {
        throw new Exception("Supabase error: " . $insertResponse["error"]);
    }

    $realtime_id = $insertResponse[0]["realtime_id"] ?? null;
    if (!$realtime_id) {
        throw new Exception("No realtime_id returned after insert. Response: " . json_encode($insertResponse));
    }

    /* ============================================================
       ✅ INSERT LITTER SUMMARY (AUTO-CREATE TYPES)
       ============================================================ */
    if (!empty($data["litterSummary"])) {
        foreach ($data["litterSummary"] as $litterName => $count) {
            // Lookup litter type
            $filter = "littertype_name=eq." . urlencode($litterName);
            $litterType = getRecords("litter_types", $filter);

            // Auto-create if not found
            if (!empty($litterType[0]["littertype_id"])) {
                $litterTypeId = $litterType[0]["littertype_id"];
            } else {
                $newType = insertRecord("litter_types", ["littertype_name" => $litterName]);
                $litterTypeId = $newType[0]["littertype_id"] ?? null;
                file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "🆕 Created new litter type: $litterName" . PHP_EOL, FILE_APPEND);
            }

            // Insert into realtime_litter_summary
            if ($litterTypeId) {
                $summaryRow = [
                    "realtime_id" => $realtime_id,
                    "littertype_id" => $litterTypeId,
                    "litter_count" => (int)$count
                ];
                insertRecord("realtime_litter_summary", $summaryRow);
                file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "Inserted litter summary: " . json_encode($summaryRow) . PHP_EOL, FILE_APPEND);
            }

            /* ============================================================
               ✅ OPTIONAL: ALSO RECORD EACH DETECTION IN `detections`
               ============================================================ */
            $time = date('H:i:s');
            $detectionRecord = [
                "image_id" => 1, // placeholder — adjust if you store image IDs
                "littertype_id" => $litterTypeId,
                "date" => $currentDate,
                "quantity" => (int)$count,
                "confidence_lvl" => floatval($data["detectionAccuracy"] ?? 0),
                "detection_time" => $time
            ];
            insertRecord("detections", $detectionRecord);
            file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "Inserted detection: " . json_encode($detectionRecord) . PHP_EOL, FILE_APPEND);
        }
    }

    /* ============================================================
       ✅ LOG ADMIN ACTIVITY
       ============================================================ */
    $activity = [
        "admin_id" => $admin_id,
        "action" => "Uploaded realtime detection for " . ($data["cameraName"] ?? "Unknown Camera")
    ];
    insertRecord("activity_logs", $activity);
    file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "Activity logged for admin $admin_id" . PHP_EOL, FILE_APPEND);

    /* ============================================================
       ✅ SUCCESS RESPONSE
       ============================================================ */
    if (ob_get_length()) ob_clean();
    echo json_encode([
        "success" => true,
        "message" => "Realtime detection session saved successfully",
        "realtime_id" => $realtime_id,
        "date" => $currentDate,
        "admin" => $admin_name
    ]);

} catch (Exception $e) {
    file_put_contents(__DIR__ . "/debugfiles/debug_log.txt", "❌ ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
