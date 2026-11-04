<?php
require_once 'config.php';
require_once 'auth.php';

if (!hasPermission('contacts', 'create')) {
    // Permission check
    exit('Access Denied.');
}

if (isset($_POST['add_contact'])) {
    // Validation
    if (empty($_POST['client_id']) || empty($_POST['contact_name'])) {
        $_SESSION['message'] = "Client and Contact Name are required fields.";
        $_SESSION['msg_type'] = "danger";
        header("location: clients.php?tab=contacts");
        exit();
    }
    
    // Sanitize and Prepare Data
    $client_id = (int)$_POST['client_id'];
    $contact_name = htmlspecialchars($_POST['contact_name'], ENT_QUOTES, 'UTF-8');
    $email = !empty($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : NULL;
    $phone = !empty($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : NULL;
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : NULL;
    
    // Database Execution
    $stmt = $conn->prepare("INSERT INTO contacts (client_id, contact_name, email, phone, owner_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $client_id, $contact_name, $email, $phone, $owner_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Contact added successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error: Could not add contact.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
}

// Redirect back to the contacts tab
header("location: clients.php?tab=contacts");
exit();
?>