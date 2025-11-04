<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(["success" => false, "error" => "Missing model ID"]);
    exit;
}

$supabase_url = "https://ksbgdgqpdoxabdefjsin.supabase.co/rest/v1/models";
$supabase_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtzYmdkZ3FwZG94YWJkZWZqc2luIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2MTAzMjUxOSwiZXhwIjoyMDc2NjA4NTE5fQ.WAai4nbsqgbe-7PgOw8bktVjk0V9Cm8sdEct_vlQCcY";

// =========================================================
// 1️⃣ Mark all models as Inactive
// =========================================================
curl_setopt_array($ch = curl_init("$supabase_url"), [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_POSTFIELDS => json_encode(["status" => "Inactive"]),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $supabase_key",
        "apikey: $supabase_key",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ],
]);
$response1 = curl_exec($ch);
$code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// =========================================================
// 2️⃣ Set the selected model to Active
// =========================================================
curl_setopt_array($ch = curl_init("$supabase_url?id=eq.$id"), [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_POSTFIELDS => json_encode(["status" => "Active"]),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $supabase_key",
        "apikey: $supabase_key",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ],
]);
$response2 = curl_exec($ch);
$code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// =========================================================
// 3️⃣ Reload Flask Model AFTER successful activation
// =========================================================
if ($code2 == 204) {
    // Wait a bit to ensure DB state is updated
    sleep(1);
    $flaskResponse = @file_get_contents("http://127.0.0.1:5000/reload_model");

    echo json_encode([
        "success" => true,
        "message" => "Model activated successfully.",
        "flask_response" => $flaskResponse ? "✅ Flask reloaded." : "⚠️ Flask not reachable."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Failed to activate selected model. HTTP $code2",
        "response" => $response2
    ]);
}
