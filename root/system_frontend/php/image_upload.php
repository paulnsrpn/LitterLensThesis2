<?php
// ✅ Use absolute path to uploadbin
$baseUploadDir = __DIR__ . '/../../../uploadbin';

// ✅ If uploadbin doesn't exist, create it
if (!file_exists($baseUploadDir)) {
    if (!mkdir($baseUploadDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to create uploadbin folder"]);
        exit;
    }
}

// ✅ Create unique folder based on timestamp
$folderName = date('Ymd_His');
$targetFolder = $baseUploadDir . DIRECTORY_SEPARATOR . $folderName;

if (!file_exists($targetFolder)) {
    if (!mkdir($targetFolder, 0777, true)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to create target folder"]);
        exit;
    }
}

// ✅ Handle the uploaded file
if (isset($_FILES['file'])) {
    $fileName = basename($_FILES['file']['name']);
    $targetFile = $targetFolder . DIRECTORY_SEPARATOR . $fileName;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
        // Build relative path for browser to load
        $relativePath = "uploadbin/$folderName/$fileName";

        echo json_encode([
            "status" => "success",
            "folder" => $folderName,
            "fileName" => $fileName,
            "filePath" => $relativePath
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "No file uploaded"]);
}
