<?php
require 'config.php';
require 'auth.php';

// Security check: only users with permission to edit leaves can proceed
if (!hasPermission('leaves', 'edit')) {
    $_SESSION['message'] = "Access Denied: You do not have permission to manage leave requests.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['update_leave_status'])) {
    $leave_id = $_POST['leave_id'];
    $new_status = $_POST['status'];

    if (!empty($leave_id) && ($new_status == 'Approved' || $new_status == 'Rejected')) {
        $stmt = $conn->prepare("UPDATE leaves SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $leave_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Leave request has been " . strtolower($new_status) . ".";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating leave status.";
            $_SESSION['msg_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Invalid data provided.";
        $_SESSION['msg_type'] = "warning";
    }
}

header("location: manage_attendance.php");
exit();
?>