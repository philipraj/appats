<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('contacts', 'delete')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: contacts.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Contact has been deleted successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error: Could not delete contact.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();

    header("location: contacts.php");
    exit();
}
?>