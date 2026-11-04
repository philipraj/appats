<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('job_orders', 'edit')) {
    // ... permission check ...
    exit();
}

if (isset($_POST['update_interview'])) {
    // Validation
    $interview_id = (int)$_POST['interview_id'];
    if (empty($interview_id) || empty($_POST['interview_round']) || empty($_POST['interview_datetime'])) {
        $_SESSION['message'] = "Please fill all required fields.";
        $_SESSION['msg_type'] = "danger";
        header("location: edit_interview.php?id=" . $interview_id);
        exit();
    }

    // Sanitize and Prepare
    $interview_round = htmlspecialchars($_POST['interview_round'], ENT_QUOTES, 'UTF-8');
    $interview_mode = htmlspecialchars($_POST['interview_mode'], ENT_QUOTES, 'UTF-8');
    $interview_datetime = $_POST['interview_datetime'];
    $interviewers = !empty($_POST['interviewers']) ? htmlspecialchars($_POST['interviewers'], ENT_QUOTES, 'UTF-8') : NULL;

    // Database Execution
    $stmt = $conn->prepare("UPDATE interviews SET 
                                interview_round = ?, interview_mode = ?, interview_datetime = ?, interviewers = ? 
                            WHERE id = ?");
    $stmt->bind_param("ssssi", $interview_round, $interview_mode, $interview_datetime, $interviewers, $interview_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Interview updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error: Could not update interview.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
}

header("location: interviews.php");
exit();
?>