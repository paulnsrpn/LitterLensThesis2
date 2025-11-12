<?php
// =========================================================
// 🧩 MANAGE MODEL (UPLOAD, ACTIVATE, DELETE)
// =========================================================
error_reporting(0);
header('Content-Type: application/json');
require_once 'system_config.php';
require_once 'system_admin_data.php'; // ✅ for logActivity()
session_start();

// ✅ Ensure admin is logged in and authorized
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_id'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

$action = $_POST['action'] ?? null;
$SUPABASE_URL = 'https://ksbgdgqpdoxabdefjsin.supabase.co';
$SUPABASE_KEY = SUPABASE_KEY;
$BUCKET = 'model';
$SUPABASE_STORAGE_URL = "$SUPABASE_URL/storage/v1/object";
$PUBLIC_URL_PREFIX = "$SUPABASE_URL/storage/v1/object/public/$BUCKET/";

// =========================================================
// 🟢 UPLOAD MODEL
// =========================================================
if ($action === 'upload') {
    if (!isset($_FILES['modelFile']) || $_FILES['modelFile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
        exit;
    }

    $fileTmp = $_FILES['modelFile']['tmp_name'];
    $fileName = basename($_FILES['modelFile']['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExt !== 'pt') {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only .pt files are allowed.']);
        exit;
    }

    // ✅ Generate unique filename
    $uniqueName = pathinfo($fileName, PATHINFO_FILENAME) . "_" . time() . ".pt";
    $uploadUrl = "$SUPABASE_STORAGE_URL/$BUCKET/$uniqueName";
    $fileData = file_get_contents($fileTmp);

    // ✅ Upload to Supabase
    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $fileData,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $SUPABASE_KEY",
            "apikey: $SUPABASE_KEY",
            "Content-Type: application/octet-stream"
        ],
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 && $status !== 201) {
        logActivity($admin_id, $admin_name, 'Upload', 'models', "Upload failed for '$fileName' (HTTP $status)", 'Failed');
        echo json_encode([
            'success' => false,
            'error' => "Upload failed (HTTP $status): $response"
        ]);
        exit;
    }

    // ✅ Deactivate existing active models
    updateRecord('models', 'status=eq.Active', ['status' => 'Inactive']);

    // ✅ Insert new model record
    $modelData = [
        'model_name'     => pathinfo($fileName, PATHINFO_FILENAME),
        'version'        => 'v' . date('y.m.d.His'),
        'accuracy'       => $_POST['accuracy'] ?? 0,
        'uploaded_by'    => $_SESSION['admin_id'],
        'model_filename' => $uniqueName,
        'status'         => 'Active',
        'uploaded_on'    => date('Y-m-d H:i:s'),
    ];
    $insert = insertRecord('models', $modelData);

    // ✅ Tell Flask to reload after successful activation
    $ch = curl_init("http://72.61.117.189/api/reload_model");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
    ]);
    $flaskResponse = curl_exec($ch);
    curl_close($ch);

    // ✅ Log success
    logActivity($admin_id, $admin_name, 'Upload', 'models', "Uploaded and activated new model '{$fileName}'", 'Success');

    echo json_encode([
        'success' => true,
        'message' => '✅ Model uploaded and activated successfully.',
        'file' => $uniqueName,
        'insert' => $insert,
        'flask_reload' => $flaskResponse
    ]);
    exit;
}

// =========================================================
// 🔄 ACTIVATE MODEL
// =========================================================
if ($action === 'activate') {
    $id = $_POST['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing model ID']);
        exit;
    }

    // ✅ Deactivate all others
    updateRecord('models', 'status=eq.Active', ['status' => 'Inactive']);

    // ✅ Activate selected model
    $result = updateRecord('models', "model_id=eq.$id", ['status' => 'Active']);

    // ✅ Tell Flask to reload the new model
    $ch = curl_init("http://72.61.117.189/api/reload_model");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
    ]);
    $flaskResponse = curl_exec($ch);
    curl_close($ch);

    // ✅ Log activation
    logActivity($admin_id, $admin_name, 'Update', 'models', "Activated model ID {$id}", 'Success');

    echo json_encode([
        'success' => true,
        'message' => '✅ Model activated successfully.',
        'updated' => $result,
        'flask_reload' => $flaskResponse
    ]);
    exit;
}

// =========================================================
// 🗑 DELETE MODEL
// =========================================================
if ($action === 'delete') {
    $id = $_POST['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing model ID']);
        exit;
    }

    if ($id == 1) {
        logActivity($admin_id, $admin_name, 'Delete Attempt', 'models', 'Attempted to delete default model (ID 1)', 'Failed');
        echo json_encode(['success' => false, 'error' => 'Default model cannot be deleted.']);
        exit;
    }

    $model = getRecords('models', "model_id=eq.$id");
    if (empty($model)) {
        echo json_encode(['success' => false, 'error' => 'Model not found.']);
        exit;
    }

    $fileName = $model[0]['model_filename'] ?? null;
    $modelName = $model[0]['model_name'] ?? 'Unknown';

    if (!$fileName) {
        echo json_encode(['success' => false, 'error' => 'Model filename missing.']);
        exit;
    }

    // ✅ Delete file from Supabase
    $deleteUrl = "$SUPABASE_STORAGE_URL/$BUCKET/$fileName";
    $ch = curl_init($deleteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "DELETE",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $SUPABASE_KEY",
            "apikey: $SUPABASE_KEY"
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);

    // ✅ Delete record in DB
    $delete = deleteRecord('models', "model_id=eq.$id");

    // ✅ Log deletion
    logActivity($admin_id, $admin_name, 'Delete', 'models', "Deleted model '{$modelName}' (ID {$id})", 'Success');

    echo json_encode([
        'success' => true,
        'message' => '🗑 Model deleted successfully.',
        'deleted' => $delete
    ]);
    exit;
}

// =========================================================
// ⚠️ INVALID ACTION
// =========================================================
logActivity($admin_id, $admin_name, 'Invalid Action', 'models', "Invalid action request: '{$action}'", 'Failed');
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
exit;
?>
