<?php
require_once 'config.php';
require_once 'auth.php';

if (!hasPermission('clients', 'create')) {
    // Permission check
    exit('Access Denied.');
}

if (isset($_POST['add_client'])) {
    // Validation
    if (empty($_POST['client_name'])) {
        $_SESSION['message'] = "Client Name is a required field.";
        $_SESSION['msg_type'] = "danger";
        header("location: clients.php?tab=clients");
        exit();
    }
    
    // Sanitize and Prepare Data
    $client_name = htmlspecialchars($_POST['client_name'], ENT_QUOTES, 'UTF-8');
    $website = !empty($_POST['website']) ? filter_var($_POST['website'], FILTER_SANITIZE_URL) : NULL;
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : NULL;
    
    // Database Execution
    $stmt = $conn->prepare("INSERT INTO clients (client_name, website, owner_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $client_name, $website, $owner_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Client added successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error: Could not add client.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
}

// Redirect back to the clients tab
header("location: clients.php?tab=clients");
exit();
?>