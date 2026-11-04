<?php
// --- Robust CORS Handling ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
// --- End of CORS block ---

header("Content-Type: application/json; charset=UTF-8");

require 'config.php';

// Security: Ensure a user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
    exit();
}

$response = ['status' => 'error', 'message' => 'Invalid candidate ID.'];

if (isset($_GET['candidate_id'])) {
    $candidate_id = (int)$_GET['candidate_id'];

    // UPDATED: Fetching more fields now
    $stmt = $conn->prepare("SELECT candidate_name, resume_text, resume_filename FROM candidates WHERE id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $candidate_data = $result->fetch_assoc();
        $file_extension = strtolower(pathinfo($candidate_data['resume_filename'], PATHINFO_EXTENSION));

        $response = [
            'status' => 'success',
            'name' => $candidate_data['candidate_name'],
            'resume_text' => nl2br(htmlspecialchars($candidate_data['resume_text'])), // Fallback text
            'filename' => $candidate_data['resume_filename'],
            'filetype' => $file_extension
        ];
    } else {
        $response['message'] = 'Candidate not found.';
    }
    $stmt->close();
}

echo json_encode($response);
?>