<?php
// system_config.php
session_start();

// === SUPABASE CONFIGURATION ===
define('SUPABASE_URL', 'https://ksbgdgqpdoxabdefjsin.supabase.co/rest/v1');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtzYmdkZ3FwZG94YWJkZWZqc2luIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2MTAzMjUxOSwiZXhwIjoyMDc2NjA4NTE5fQ.WAai4nbsqgbe-7PgOw8bktVjk0V9Cm8sdEct_vlQCcY');

// === CURL HELPER FUNCTION ===
function supabaseRequest($method, $table, $data = null, $filter = null)
{
    $url = SUPABASE_URL . '/' . $table;
    if ($filter) {
        $url .= '?' . $filter; // e.g., "id=eq.1"
    }

    $ch = curl_init($url);

    $headers = [
        "apikey: " . SUPABASE_KEY,
        "Authorization: Bearer " . SUPABASE_KEY,
        "Content-Type: application/json"
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ["Prefer: return=representation"]));
            break;
        case 'PATCH':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ["Prefer: return=representation"]));
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;
        default: // GET
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            break;
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    }

    curl_close($ch);
    return json_decode($response, true);
}

// === HELPER FUNCTIONS ===
function insertRecord($table, $data)
{
    return supabaseRequest('POST', $table, $data);
}

function getRecords($table, $filter = null)
{
    return supabaseRequest('GET', $table, null, $filter);
}

function updateRecord($table, $filter, $data)
{
    return supabaseRequest('PATCH', $table, $data, $filter);
}

function deleteRecord($table, $filter)
{
    return supabaseRequest('DELETE', $table, null, $filter);
}

// === REDIRECT HELPER FUNCTION ===
function redirect($url)
{
    header("Location: $url");
    exit;
}
?>
