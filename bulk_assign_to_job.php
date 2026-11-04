<?php
require_once 'config.php';
require_once 'auth.php';

if (!hasPermission('candidates', 'edit')) { // Or a more specific permission
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: candidates.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $candidate_ids = $_POST['candidate_ids'] ?? [];
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $recruiter_id = $_SESSION['user_id'];
    $initial_status = 'Sourced';

    if (empty($candidate_ids) || empty($job_id)) {
        $_SESSION['message'] = "No candidates or job were selected.";
        $_SESSION['msg_type'] = "warning";
        header("location: candidates.php");
        exit();
    }
    
    $assigned_count = 0;
    $skipped_count = 0;

    // Use a transaction for an all-or-nothing operation
    $conn->begin_transaction();
    try {
        $insert_stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status) VALUES (?, ?, ?, ?)");
        $check_stmt = $conn->prepare("SELECT id FROM submissions WHERE candidate_id = ? AND job_id = ?");

        foreach ($candidate_ids as $candidate_id) {
            $cid = (int)$candidate_id;

            // Check if submission already exists to prevent duplicates
            $check_stmt->bind_param("ii", $cid, $job_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $skipped_count++;
                continue; // Skip to the next candidate
            }

            // Insert new submission if it doesn't exist
            $insert_stmt->bind_param("iiis", $job_id, $cid, $recruiter_id, $initial_status);
            $insert_stmt->execute();
            $assigned_count++;
        }
        
        $conn->commit();
        
        $message = "{$assigned_count} candidates were successfully assigned.";
        if ($skipped_count > 0) {
            $message .= " {$skipped_count} were skipped as they were already in the pipeline.";
        }
        $_SESSION['message'] = $message;
        $_SESSION['msg_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "An error occurred during the bulk assignment.";
        $_SESSION['msg_type'] = "danger";
    }

}

header("location: candidates.php");
exit();
?>