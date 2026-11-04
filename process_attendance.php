<?php
require 'config.php';
require 'auth.php';

$user_id = $_SESSION['user_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];

// Determine where to redirect the user after the action
$redirect_url = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'attendance.php';

if (isset($_POST['check_in'])) {
    // Insert a new check-in record
    $stmt = $conn->prepare("INSERT INTO attendance (user_id, check_in_time, check_in_ip) VALUES (?, NOW(), ?)");
    $stmt->bind_param("is", $user_id, $ip_address);
    if($stmt->execute()){
        $_SESSION['message'] = "You have successfully checked in!";
        $_SESSION['msg_type'] = "success";
    }
}

if (isset($_POST['check_out'])) {
    // Find the open check-in and update it with the check-out time
    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW(), check_out_ip = ? WHERE user_id = ? AND check_out_time IS NULL ORDER BY check_in_time DESC LIMIT 1");
    $stmt->bind_param("si", $ip_address, $user_id);
    if($stmt->execute()){
        $_SESSION['message'] = "You have successfully checked out!";
        $_SESSION['msg_type'] = "success";
    }
}

header("location: " . $redirect_url);
exit();
?>