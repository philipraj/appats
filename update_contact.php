<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('contacts', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: contacts.php");
    exit();
}

if (isset($_POST['update_contact'])) {
    $id = $_POST['id'];
    $client_id = $_POST['client_id'];
    $contact_name = $_POST['contact_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $owner_id = !empty($_POST['owner_id']) ? $_POST['owner_id'] : NULL;

    if (empty($client_id) || empty($contact_name)) {
        $_SESSION['message'] = "Client and Contact Name are required fields.";
        $_SESSION['msg_type'] = "danger";
    } else {
        $stmt = $conn->prepare("UPDATE contacts SET client_id = ?, contact_name = ?, email = ?, phone = ?, owner_id = ? WHERE id = ?");
        $stmt->bind_param("isssii", $client_id, $contact_name, $email, $phone, $owner_id, $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Contact has been updated successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not update contact.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
    
    header("location: contacts.php");
    exit();
}
?>