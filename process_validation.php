<?php
require 'config.php';
require_once 'auth.php';

// Security check: Only users who can edit job orders can perform this action.
// We can make this more specific to an 'admin' or 'team_lead' role if needed.
if (!hasPermission('job_orders', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['submission_id'])) {
    $submission_id = $_GET['submission_id'];
    $action = $_GET['action'];
    $new_status = '';

    if ($action == 'approve') {
        $new_status = 'Submitted to client';
    } elseif ($action == 'reject') {
        $new_status = 'Screen Reject'; // As you requested
    }

    if (!empty($new_status)) {
        $stmt = $conn->prepare("UPDATE submissions SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $submission_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Candidate status has been updated successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not update candidate status.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
}

// Always redirect back to the dashboard
header("location: dashboard.php");
exit();
?>