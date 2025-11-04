<?php
require_once 'system_config.php';
session_start();

// 🧩 Only Admin 1 can upload models
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_id'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized — only Admin 1 can upload models.']);
    exit;
}

// 🧠 Capture uploader info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Unknown';

// ✅ Supabase Configuration
$BUCKET = 'model';
$SUPABASE_URL = 'https://ksbgdgqpdoxabdefjsin.supabase.co';
$SUPABASE_STORAGE_URL = "$SUPABASE_URL/storage/v1/object";
$PUBLIC_URL_PREFIX = "$SUPABASE_URL/storage/v1/object/public/$BUCKET/";

// 📂 Check uploaded file
if (!isset($_FILES['modelFile']) || $_FILES['modelFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
    exit;
}

$fileTmp = $_FILES['modelFile']['tmp_name'];
$fileName = basename($_FILES['modelFile']['name']);
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validate file type
if ($fileExt !== 'pt') {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only .pt allowed.']);
    exit;
}

// 🧩 Prevent overwriting files with same name
$uniqueName = pathinfo($fileName, PATHINFO_FILENAME) . "_" . time() . ".pt";

// 🧠 Prepare Upload URL
$uploadUrl = "$SUPABASE_STORAGE_URL/$BUCKET/$uniqueName";

// 🧾 Read file data
$fileData = file_get_contents($fileTmp);

// Upload to Supabase Storage
$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => $fileData,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . SUPABASE_KEY,
        "apikey: " . SUPABASE_KEY,
        "Content-Type: application/octet-stream",
    ],
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200 && $status !== 201) {
    echo json_encode(['success' => false, 'error' => "Upload failed (HTTP $status): $response"]);
    exit;
}

// 🌐 Public URL (for download / Flask)
$publicUrl = $PUBLIC_URL_PREFIX . $uniqueName;

// 🧩 Auto-generate version and metadata
$version = "v" . date("y.m.d.His");
$model_name = pathinfo($fileName, PATHINFO_FILENAME);

// 📦 Prepare record for Supabase `models` table
$modelData = [
    'model_name' => $model_name,
    'version' => $version,
    'accuracy' => $_POST['accuracy'] ?? null,
    'uploaded_by' => $admin_id,
    'bucket_url' => $publicUrl,
    'status' => 'Inactive',
    'uploaded_on' => date('c'),
];

// ✅ Insert record into `models` table via REST API
$insertUrl = "$SUPABASE_URL/rest/v1/models";
$ch = curl_init($insertUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($modelData),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . SUPABASE_KEY,
        "apikey: " . SUPABASE_KEY,
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ],
]);
$response = curl_exec($ch);
$insertStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($insertStatus !== 201 && $insertStatus !== 200 && $insertStatus !== 204) {
    echo json_encode(['success' => false, 'error' => "Database insert failed (HTTP $insertStatus): $response"]);
    exit;
}

// 🪵 Log to activity_logs
$logData = [
    'admin_id' => $admin_id,
    'admin_name' => $admin_name,
    'action' => 'Upload',
    'affected_table' => 'models',
    'description' => "Uploaded new model: $model_name ($uniqueName)"
];
insertRecord('activity_logs', $logData);

// ✅ Final JSON response
echo json_encode([
    'success' => true,
    'message' => 'Model uploaded successfully',
    'file_url' => $publicUrl,
    'file_name' => $uniqueName,
    'version' => $version,
]);
