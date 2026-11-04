<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('job_orders', 'edit')) {
    die("Permission Denied.");
}

if (isset($_POST['submission_id'])) {
    $submission_id = $_POST['submission_id'];
    $offered_ctc = $_POST['offered_ctc'] ?? NULL;
    $expected_doj = $_POST['expected_doj'] ?? NULL;

    $stmt = $conn->prepare("UPDATE submissions SET offered_ctc = ?, expected_doj = ? WHERE id = ?");
    $stmt->bind_param("ssi", $offered_ctc, $expected_doj, $submission_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Offer details updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating offer details: " . $stmt->error;
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