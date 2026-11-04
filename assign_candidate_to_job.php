<?php
// Start output buffering to prevent any accidental output
ob_start();

// Include required files
require_once 'config.php';
require_once 'auth.php';

// Set JSON header
header('Content-Type: application/json');

// Initialize response array
$response = ['status' => 'error', 'message' => 'Unknown error occurred.'];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You are not logged in.");
    }

    // Check permissions
    if (!hasPermission('candidates', 'edit')) {
        throw new Exception("You do not have permission to perform this action.");
    }

    // Check if it's a POST request with required parameters
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    if (!isset($_POST['assign_job']) || !isset($_POST['candidate_id']) || !isset($_POST['job_id'])) {
        throw new Exception("Missing required parameters.");
    }

    // Get and validate parameters
    $candidate_id = (int)$_POST['candidate_id'];
    $job_id = (int)$_POST['job_id'];
    
    if ($candidate_id <= 0) {
        throw new Exception("Invalid candidate ID.");
    }
    
    if ($job_id <= 0) {
        throw new Exception("Invalid job ID.");
    }

    // Check if candidate exists
    $candidate_check = $conn->prepare("SELECT candidate_name FROM candidates WHERE id = ?");
    if (!$candidate_check) {
        throw new Exception("Database error: Failed to prepare candidate query - " . $conn->error);
    }
    
    $candidate_check->bind_param("i", $candidate_id);
    if (!$candidate_check->execute()) {
        throw new Exception("Database error: Failed to execute candidate query - " . $candidate_check->error);
    }
    
    $candidate_result = $candidate_check->get_result();
    if ($candidate_result->num_rows === 0) {
        throw new Exception("Candidate not found.");
    }
    
    $candidate = $candidate_result->fetch_assoc();
    $candidate_check->close();

    // Check if job exists and is active
    $job_check = $conn->prepare("SELECT id, job_title, client_id FROM job_orders WHERE id = ? AND status = 'Active'");
    if (!$job_check) {
        throw new Exception("Database error: Failed to prepare job query - " . $conn->error);
    }
    
    $job_check->bind_param("i", $job_id);
    if (!$job_check->execute()) {
        throw new Exception("Database error: Failed to execute job query - " . $job_check->error);
    }
    
    $job_result = $job_check->get_result();
    if ($job_result->num_rows === 0) {
        throw new Exception("Job not found or not active.");
    }
    
    $job = $job_result->fetch_assoc();
    $job_check->close();

    // Get client name
    $client_name = "Unknown Client";
    $client_check = $conn->prepare("SELECT client_name FROM clients WHERE id = ?");
    if ($client_check) {
        $client_check->bind_param("i", $job['client_id']);
        if ($client_check->execute()) {
            $client_result = $client_check->get_result();
            if ($client_result->num_rows > 0) {
                $client_data = $client_result->fetch_assoc();
                $client_name = $client_data['client_name'];
            }
        }
        $client_check->close();
    }

    // Insert submission into database - NO DUPLICATE CHECK
    $stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status, submitted_at) VALUES (?, ?, ?, 'Submitted', NOW())");
    if (!$stmt) {
        throw new Exception("Database error: Failed to prepare insert query - " . $conn->error);
    }
    
    $stmt->bind_param("iii", $job_id, $candidate_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $response = [
            'status' => 'success',
            'message' => "Candidate '{$candidate['candidate_name']}' successfully submitted for job '{$job['job_title']}' ({$client_name})."
        ];
        
        // Log activity (optional - remove if you don't have activity_logs table)
        try {
            $activity_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'candidate_submission', ?, ?, ?, NOW())");
            if ($activity_stmt) {
                $activity_description = "Submitted candidate '{$candidate['candidate_name']}' to job '{$job['job_title']}' for client '{$client_name}'";
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $activity_stmt->bind_param("isss", $_SESSION['user_id'], $activity_description, $ip_address, $user_agent);
                $activity_stmt->execute();
                $activity_stmt->close();
            }
        } catch (Exception $e) {
            // Ignore activity log errors
        }
        
    } else {
        throw new Exception("Failed to submit candidate: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// Clean any output buffers and send JSON response
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Output the JSON response
echo json_encode($response);
exit();
?>