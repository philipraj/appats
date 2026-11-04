<?php
require 'config.php';

if (isset($_POST['submission_id']) && isset($_POST['actual_doj'])) {
    $submission_id = $_POST['submission_id'];
    $actual_doj = $_POST['actual_doj'];

    $stmt = $conn->prepare("UPDATE submissions SET actual_doj = ? WHERE id = ?");
    $stmt->bind_param("si", $actual_doj, $submission_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Actual DOJ updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating Actual DOJ.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
}
header("location: joined.php");
exit();
?>