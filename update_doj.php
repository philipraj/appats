<?php
require 'config.php';

if (isset($_POST['submission_id']) && isset($_POST['expected_doj'])) {
    $submission_id = $_POST['submission_id'];
    $expected_doj = $_POST['expected_doj'];

    $stmt = $conn->prepare("UPDATE submissions SET expected_doj = ? WHERE id = ?");
    $stmt->bind_param("si", $expected_doj, $submission_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Expected DOJ updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating Expected DOJ.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
}
header("location: offered.php");
exit();
?>