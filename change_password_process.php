<?php
require 'config.php';

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    $user_id = $_SESSION['user_id'];

    // 1. Validate new passwords match
    if ($new_password !== $confirm_new_password) {
        $_SESSION['message'] = "New passwords do not match.";
        $_SESSION['msg_type'] = "danger";
        header("location: settings.php?page=change_password");
        exit();
    }

    // 2. Fetch the user's current hashed password from the database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // 3. Verify the current password is correct
    if (password_verify($current_password, $user['password'])) {
        // If correct, hash the new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // 4. Update the database with the new hashed password
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_password_hash, $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "Password changed successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not update password.";
            $_SESSION['msg_type'] = "danger";
        }
        $update_stmt->close();

    } else {
        // If current password does not match
        $_SESSION['message'] = "Incorrect current password.";
        $_SESSION['msg_type'] = "danger";
    }

    header("location: settings.php?page=change_password");
    exit();
} else {
    // Redirect if accessed directly
    header("location: settings.php");
    exit();
}
?>