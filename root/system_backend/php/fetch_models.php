<?php
// =========================================================
// 📦 FETCH MODELS (For Admin Panel)
// =========================================================
header('Content-Type: application/json');
require_once __DIR__ . '/system_config.php';

try {
    $models = getRecords('models', 'order=uploaded_on.desc');

    if (!$models || isset($models['error'])) {
        echo json_encode([
            'success' => false,
            'error' => $models['error'] ?? 'Failed to fetch model data.'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $models]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
