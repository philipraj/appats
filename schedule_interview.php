<?php
require 'config.php';
require_once 'auth.php';

// Security: Check for permission to edit job orders
if (!hasPermission('job_orders', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['schedule_interview'])) {
    
    // --- Validation ---
    $redirect_url = $_POST['redirect_url'] ?? 'dashboard.php';
    if (empty($_POST['submission_id']) || empty($_POST['interview_round']) || empty($_POST['interview_datetime']) || empty($_POST['interview_mode'])) {
        $_SESSION['message'] = "Please fill all required fields.";
        $_SESSION['msg_type'] = "danger";
        header("location: " . $redirect_url);
        exit();
    }

    // --- Sanitize & Prepare Data ---
    $submission_id = (int)$_POST['submission_id'];
    $interview_round = htmlspecialchars($_POST['interview_round'], ENT_QUOTES, 'UTF-8');
    $interview_mode = htmlspecialchars($_POST['interview_mode'], ENT_QUOTES, 'UTF-8');
    $interview_datetime = $_POST['interview_datetime']; // Is a specific format, safe for DB
    $interviewers = !empty($_POST['interviewers']) ? htmlspecialchars($_POST['interviewers'], ENT_QUOTES, 'UTF-8') : NULL;
    
    // --- Database Transaction ---
    $conn->begin_transaction();
    try {
        // Step 1: Insert the new interview record
        $sql_insert = "INSERT INTO interviews (submission_id, interview_datetime, interview_round, interview_mode, interviewers) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("issss", $submission_id, $interview_datetime, $interview_round, $interview_mode, $interviewers);
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Failed to save interview schedule.");
        }
        $stmt_insert->close();
        
        // Step 2: Update the submission status to match the interview round
        $sql_update = "UPDATE submissions SET status = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $interview_round, $submission_id);

        if (!$stmt_update->execute()) {
            throw new Exception("Failed to update candidate status.");
        }
        $stmt_update->close();

        // If both queries succeed, commit the changes
        $conn->commit();
        $_SESSION['message'] = "Interview scheduled and candidate status updated successfully!";
        $_SESSION['msg_type'] = "success";

    } catch (Exception $e) {
        // If any query fails, roll back all changes
        $conn->rollback();
        $_SESSION['message'] = "An error occurred: " . $e->getMessage();
        $_SESSION['msg_type'] = "danger";
    }

    header("location: " . $redirect_url);
    exit();
}

// Fallback redirect if the form wasn't submitted correctly
header("location: dashboard.php");
exit();
?>