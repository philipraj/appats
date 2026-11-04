<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

require 'config.php';
require 'auth.php';
require 'text_extractor.php';

$response = ['status' => 'error', 'message' => 'Invalid Request'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Error: You are not logged into the ATS.';
    echo json_encode($response);
    exit();
}

if (empty($_POST['name']) || empty($_POST['jobId']) || !isset($_FILES['resume']) || $_FILES['resume']['error'] != 0) {
    $response['message'] = 'Error: Missing required data from extension (Name, Job, or Resume).';
    echo json_encode($response);
    exit();
}

$resume_filename = NULL;
$resume_text = NULL;
$target_dir = "uploads/";
$file_extension = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);
$resume_filename = 'resume-' . uniqid() . '-' . time() . '.' . $file_extension;
$target_file = $target_dir . $resume_filename;

if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
    $resume_text = extractTextFromFile($target_file);
} else {
    $response['message'] = 'Error: Failed to save uploaded resume.';
    echo json_encode($response);
    exit();
}

$job_id = (int)$_POST['jobId'];
$recruiter_id = $_SESSION['user_id'];
$candidate_name = $_POST['name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$location = $_POST['location'];
$salary = $_POST['salary'];
$notice_period = $_POST['notice_period'];

$candidate_id = null;
if ($email) {
    $stmt_check = $conn->prepare("SELECT id FROM candidates WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    if ($result->num_rows > 0) { $candidate_id = $result->fetch_assoc()['id']; }
}

if (!$candidate_id) {
    $stmt_add = $conn->prepare("INSERT INTO candidates (candidate_name, email, phone, current_location, current_ctc, notice_period, resume_filename, resume_text, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_add->bind_param("ssssssssi", $candidate_name, $email, $phone, $location, $salary, $notice_period, $resume_filename, $resume_text, $recruiter_id);
    $stmt_add->execute();
    $candidate_id = $conn->insert_id;
}

if ($candidate_id && $job_id) {
    $sub_check = $conn->prepare("SELECT id FROM submissions WHERE job_id = ? AND candidate_id = ?");
    $sub_check->bind_param("ii", $job_id, $candidate_id);
    $sub_check->execute();
    if ($sub_check->get_result()->num_rows == 0) {
        $status = "Sourced";
        $sub_stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status) VALUES (?, ?, ?, ?)");
        $sub_stmt->bind_param("iiis", $job_id, $candidate_id, $recruiter_id, $status);
        if ($sub_stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Candidate ' . htmlspecialchars($candidate_name) . ' created and sourced!'];
        }
    } else { $response['message'] = 'This candidate already exists for this job.'; }
} else { $response['message'] = 'Could not create submission record.'; }

echo json_encode($response);
?>