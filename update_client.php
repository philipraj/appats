<?php
require 'config.php';

if (isset($_POST['update_client'])) {
    $id = $_POST['id'];
    $client_name = $_POST['client_name'];
    $website = $_POST['website'];
    $owner_id = !empty($_POST['owner_id']) ? $_POST['owner_id'] : NULL;

    if (empty($client_name)) {
        $_SESSION['message'] = "Client Name is a required field.";
        $_SESSION['msg_type'] = "danger";
    } else {
        $stmt = $conn->prepare("UPDATE clients SET client_name = ?, website = ?, owner_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $client_name, $website, $owner_id, $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Client has been updated successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not update client.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
    
    header("location: clients.php");
    exit();
}
?>