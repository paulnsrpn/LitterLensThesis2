<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder = $_POST['folder'] ?? '';

    if ($folder !== '') {
        $basePath = __DIR__ . '/../../../uploadbin/';
        $targetDir = realpath($basePath . $folder);

        // ✅ Prevent directory traversal
        if (strpos($targetDir, realpath($basePath)) !== 0) {
            http_response_code(400);
            echo "Invalid folder path";
            exit;
        }

        // ✅ Recursively delete folder
        function deleteDir($dir) {
            if (!is_dir($dir)) return false;
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = "$dir/$file";
                is_dir($path) ? deleteDir($path) : unlink($path);
            }
            return rmdir($dir);
        }

        if (deleteDir($targetDir)) {
            echo "Folder deleted successfully";
        } else {
            http_response_code(500);
            echo "Failed to delete folder";
        }
    } else {
        http_response_code(400);
        echo "No folder name provided";
    }
}
?>