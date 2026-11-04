<?php
// --- CORS Handling ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) header("Access-control-allow-methods: GET, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) header("Access-control-allow-headers: Content-Type");
    exit(0);
}
// --- End of CORS block ---

header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php';
require_once 'auth.php';

// Only proceed if a user is logged into the main application
if (isLoggedIn()) {
    $result = $conn->query("
        SELECT j.id, j.job_title, c.client_name
        FROM job_orders j
        JOIN clients c ON j.client_id = c.id
        WHERE j.status = 'Active'
        ORDER BY c.client_name, j.job_title
    ");

    if ($result) {
        $jobs = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($jobs); // Send data directly on success
        exit();
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to fetch jobs from the database.']);
        exit();
    }
}

// If not logged in, send an authentication error
http_response_code(401);
echo json_encode(['message' => 'Not authenticated. Please log in to the ATS.']);
?>