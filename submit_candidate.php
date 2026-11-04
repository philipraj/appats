<?php
require 'config.php';
require_once 'auth.php';

// Security Check: A user must have "edit" permission for job orders to submit a candidate
if (!hasPermission('job_orders', 'edit')) {
    // We use die() here because this might be called via AJAX in the future
    die("Permission Denied.");
}

if (isset($_POST['candidate_id']) && isset($_POST['job_id'])) {
    $candidate_id = $_POST['candidate_id'];
    $job_id = $_POST['job_id'];
    $recruiter_id = $_SESSION['user_id']; 
    
    // CORRECTED: Default status is now 'Sourced' as you requested.
    $initial_status = 'Sourced';

    // Check if this candidate is already submitted to this job to prevent errors
    $check_stmt = $conn->prepare("SELECT id FROM submissions WHERE job_id = ? AND candidate_id = ?");
    $check_stmt->bind_param("ii", $job_id, $candidate_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['message'] = "This candidate has already been submitted to this job.";
        $_SESSION['msg_type'] = "warning";
    } else {
        $stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $job_id, $candidate_id, $recruiter_id, $initial_status);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Candidate submitted successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not submit candidate.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
    $check_stmt->close();

    // Redirect back to the job detail page
    header("location: view_job.php?id=" . $job_id);
    exit();
}
?>