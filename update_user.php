<?php
require 'config.php';

if (isset($_POST['update_user'])) {
    $id = $_POST['id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    // If password field is empty, we don't update it
    if (empty($password)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $role, $id);
    } else {
        // If a new password is provided, hash it and update it
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $email, $role, $password_hash, $id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "User updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating user.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
}
header("location: users.php");
exit();
?>