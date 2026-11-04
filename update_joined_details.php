<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('job_orders', 'edit')) {
    die("Permission Denied.");
}

if (isset($_POST['submission_id'])) {
    $submission_id = $_POST['submission_id'];
    $joined_ctc = $_POST['joined_ctc'] ?? NULL;
    $actual_doj = $_POST['actual_doj'] ?? NULL;

    $stmt = $conn->prepare("UPDATE submissions SET joined_ctc = ?, actual_doj = ? WHERE id = ?");
    $stmt->bind_param("ssi", $joined_ctc, $actual_doj, $submission_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Joining details updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating joining details: " . $stmt->error;
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
} else {
    $_SESSION['message'] = "Invalid request parameters.";
    $_SESSION['msg_type'] = "danger";
}

// Redirect back to placement management page
header("location: placement_management.php");
exit();
?>