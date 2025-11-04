<?php
// =========================================================
// ✅ Fetch all model records from Supabase
// =========================================================
header('Content-Type: application/json');
require_once __DIR__ . '/system_config.php';

// Ensure getRecords() helper exists in your system_config.php
try {
    // Fetch from the correct Supabase table: public.models
    $response = getRecords('models', 'order=uploaded_on.desc');

    if (!$response || isset($response['error'])) {
        echo json_encode([
            'success' => false,
            'error' => $response['error'] ?? 'No response from Supabase'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $response]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server exception: ' . $e->getMessage()
    ]);
}
?>
