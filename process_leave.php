<?php
require 'config.php';
require 'auth.php';

if (isset($_POST['apply_leave'])) {
    $user_id = $_SESSION['user_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    if (!empty($start_date) && !empty($end_date)) {
        $stmt = $conn->prepare("INSERT INTO leaves (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $start_date, $end_date, $reason);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Leave request submitted successfully.";
            $_SESSION['msg_type'] = "success";
        }
    }
}
header("location: attendance.php");
exit();
?>