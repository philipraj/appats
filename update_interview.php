<?php
require 'config.php';
require_once 'auth.php'; // Corrected from 'require' to 'require_once'

// Security check
if (!hasPermission('job_orders', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: interviews.php");
    exit();
}

if (isset($_POST['update_interview'])) {
    $interview_id = $_POST['interview_id'];
    $submission_id = $_POST['submission_id'];
    $interview_datetime = $_POST['interview_datetime'];
    $interview_round = $_POST['interview_round'];
    $interviewers = $_POST['interviewers'];
    $location_or_link = $_POST['location_or_link'];
    $feedback = $_POST['feedback'];

    if (empty($interview_id) || empty($submission_id) || empty($interview_datetime) || empty($interview_round)) {
        $_SESSION['message'] = "Error: All fields are required.";
        $_SESSION['msg_type'] = "danger";
    } else {
        // 1. Update the interview record
        $stmt_update_interview = $conn->prepare("UPDATE interviews SET interview_datetime = ?, interview_round = ?, interviewers = ?, location_or_link = ?, feedback = ? WHERE id = ?");
        $stmt_update_interview->bind_param("sssssi", $interview_datetime, $interview_round, $interviewers, $location_or_link, $feedback, $interview_id);

        if ($stmt_update_interview->execute()) {
            // 2. Also update the main submission status to match
            $stmt_update_submission = $conn->prepare("UPDATE submissions SET status = ? WHERE id = ?");
            $stmt_update_submission->bind_param("si", $interview_round, $submission_id);
            $stmt_update_submission->execute();
            
            $_SESSION['message'] = "Interview details updated successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not update interview details.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt_update_interview->close();
    }
    
    header("location: interviews.php");
    exit();
}
?>