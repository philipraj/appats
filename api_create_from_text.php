<?php
// --- Robust CORS Handling ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: Content-Type");
    exit(0);
}
// --- End of CORS block ---

header("Content-Type: application/json; charset=UTF-8");

require 'config.php';
require_once 'auth.php'; // Corrected

$response = ['status' => 'error', 'message' => 'Invalid Request'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Error: You are not logged into the ATS.';
    echo json_encode($response);
    exit();
}

if (empty($_POST['job_id']) || empty($_POST['text_block'])) {
    $response['message'] = 'Error: Missing required data from extension.';
    echo json_encode($response);
    exit();
}

$text = $_POST['text_block'];
$job_id = (int)$_POST['job_id'];
$recruiter_id = $_SESSION['user_id'];
$parsed_data = [];

// --- Parsing Logic ---
$lines = explode("\n", $text);
$parsed_data['name'] = trim($lines[0] ?? 'N/A');
if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) { $parsed_data['email'] = trim($matches[0]); }
if (preg_match('/\b\d{10}\b/', $text, $matches)) { $parsed_data['phone'] = trim($matches[0]); }

// --- Database Logic ---
$email = $parsed_data['email'] ?? null;
$candidate_id = null;
if ($email) {
    $stmt_check = $conn->prepare("SELECT id FROM candidates WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    if ($result->num_rows > 0) { $candidate_id = $result->fetch_assoc()['id']; }
    $stmt_check->close();
}

if (!$candidate_id) {
    $stmt_add = $conn->prepare("INSERT INTO candidates (candidate_name, email, phone, created_by_id) VALUES (?, ?, ?, ?)");
    $stmt_add->bind_param("sssi", $parsed_data['name'], $parsed_data['email'], $parsed_data['phone'], $recruiter_id);
    $stmt_add->execute();
    $candidate_id = $conn->insert_id;
    $stmt_add->close();
}

if ($candidate_id && $job_id) {
    $sub_check = $conn->prepare("SELECT id FROM submissions WHERE job_id = ? AND candidate_id = ?");
    $sub_check->bind_param("ii", $job_id, $candidate_id);
    $sub_check->execute();
    $sub_result = $sub_check->get_result();
    if ($sub_result->num_rows == 0) {
        $status = "Sourced";
        $sub_stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status) VALUES (?, ?, ?, ?)");
        $sub_stmt->bind_param("iiis", $job_id, $candidate_id, $recruiter_id, $status);
        if ($sub_stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Candidate ' . htmlspecialchars($parsed_data['name']) . ' created and sourced!'];
        }
    } else { $response['message'] = 'This candidate already exists in this job pipeline.'; }
    $sub_check->close();
} else { $response['message'] = 'Candidate was created, but could not be assigned to the job.'; }

echo json_encode($response);
?>