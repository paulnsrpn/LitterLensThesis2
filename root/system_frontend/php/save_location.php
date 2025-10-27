<?php
if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
    $lat = $_POST['latitude'];
    $lon = $_POST['longitude'];
    $log = date('Y-m-d H:i:s') . " - Lat: $lat, Lon: $lon\n";
    file_put_contents(__DIR__ . "/debugfiles/location_log.txt", $log, FILE_APPEND);
    echo "Location saved successfully";
} else {
    echo "No location data received";
}
?>
