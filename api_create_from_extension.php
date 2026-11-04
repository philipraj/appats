<?php
// --- ADDED: Robust JSON-only Error Handling ---
// This ensures that any PHP error becomes a JSON response instead of HTML.
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly to the output

set_exception_handler(function ($exception) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'Uncaught Exception: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit();
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
// --- End of Error Handling Block ---

// --- CORS Handling (for Chrome Extension) ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
    exit(0);
}
// --- End of CORS block ---

// This header is important for API-style responses to the extension
header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php';
require_once 'auth.php';
require_once 'text_extractor.php'; // Required for resume parsing

// --- Security & Validation ---
if (!isLoggedIn() || !hasPermission('candidates', 'create')) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication or Permission Error.']);
    exit();
}

if (empty($_POST['candidate_name']) || !isset($_FILES['resume']) || $_FILES['resume']['error'] != 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Data: Missing name or resume file.']);
    exit();
}

// --- File Upload Logic ---
$upload_dir = "uploads/";
$file_extension = strtolower(pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION));
$resume_filename = 'resume-' . uniqid() . '.' . $file_extension;
$target_file = $upload_dir . $resume_filename;

if (!move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
    echo json_encode(['status' => 'error', 'message' => 'Server Error: Failed to save uploaded resume.']);
    exit();
}

// Safely get the resume text
$resume_text = extractTextFromFile($target_file);

// --- MODIFIED: Sanitize & Prepare All Data from the Form ---
$candidate_name = htmlspecialchars($_POST['candidate_name'], ENT_QUOTES, 'UTF-8');
$email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : null;
$phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : null;
$current_location = isset($_POST['current_location']) ? htmlspecialchars($_POST['current_location'], ENT_QUOTES, 'UTF-8') : null;
$current_ctc = isset($_POST['current_ctc']) ? htmlspecialchars($_POST['current_ctc'], ENT_QUOTES, 'UTF-8') : null;
$expected_ctc = isset($_POST['expected_ctc']) ? htmlspecialchars($_POST['expected_ctc'], ENT_QUOTES, 'UTF-8') : null; // ADDED: expected_ctc field
$notice_period = isset($_POST['notice_period']) ? htmlspecialchars($_POST['notice_period'], ENT_QUOTES, 'UTF-8') : null;
$job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : (!empty($_POST['jobId']) ? (int)$_POST['jobId'] : null);
$recruiter_id = $_SESSION['user_id'];
$candidate_id = null;

// --- Candidate & Submission Logic ---
$conn->begin_transaction();

try {
    // Check if candidate already exists by email
    if (!empty($email)) {
        $stmt_check = $conn->prepare("SELECT id FROM candidates WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows > 0) {
            $candidate_id = $result->fetch_assoc()['id'];
            
            // If candidate exists, update their details and the new resume
            // UPDATED: Added expected_ctc to UPDATE statement
            $update_stmt = $conn->prepare("UPDATE candidates SET resume_filename = ?, resume_text = ?, current_ctc = ?, expected_ctc = ?, notice_period = ? WHERE id = ?");
            $update_stmt->bind_param("sssssi", $resume_filename, $resume_text, $current_ctc, $expected_ctc, $notice_period, $candidate_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        $stmt_check->close();
    }
    
    // If candidate doesn't exist, create them
    if (!$candidate_id) {
        // UPDATED: Added expected_ctc to INSERT statement
        $stmt_add = $conn->prepare("INSERT INTO candidates (candidate_name, email, phone, current_location, current_ctc, expected_ctc, notice_period, resume_filename, resume_text, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_add->bind_param("sssssssssi", $candidate_name, $email, $phone, $current_location, $current_ctc, $expected_ctc, $notice_period, $resume_filename, $resume_text, $recruiter_id);
        $stmt_add->execute();
        $candidate_id = $conn->insert_id;
        $stmt_add->close();
    }
    
    // If a job ID was provided, create the submission record
    if ($job_id && $candidate_id) {
        $sub_check = $conn->prepare("SELECT id FROM submissions WHERE job_id = ? AND candidate_id = ?");
        $sub_check->bind_param("ii", $job_id, $candidate_id);
        $sub_check->execute();
        
        if ($sub_check->get_result()->num_rows == 0) {
            $status = "Sourced";
            $sub_stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status) VALUES (?, ?, ?, ?)");
            $sub_stmt->bind_param("iiis", $job_id, $candidate_id, $recruiter_id, $status);
            $sub_stmt->execute();
            $sub_stmt->close();
            $message = "Candidate processed and assigned to the job successfully!";
        } else {
            $message = "Candidate already exists and has been assigned to this job pipeline.";
        }
        $sub_check->close();
    } else {
        $message = "Candidate created/updated successfully!";
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => $message]);
} catch (Exception $e) {
    $conn->rollback();
    
    if (file_exists($target_file)) {
        unlink($target_file);
    } // Clean up failed upload
    
    // The global exception handler at the top will now catch this
    throw $e;
}

// No redirect for API calls
exit();
?>