<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('job_orders', 'edit')) {
    die("Permission Denied.");
}

if (isset($_POST['submission_id']) && isset($_POST['new_status'])) {
    $submission_id = $_POST['submission_id'];
    $new_status = $_POST['new_status'];
    $current_status = $_POST['current_status'] ?? '';
    
    // Initialize variables
    $offered_ctc = $_POST['offered_ctc'] ?? NULL;
    $expected_doj = $_POST['expected_doj'] ?? NULL;
    $joined_ctc = $_POST['joined_ctc'] ?? NULL;
    $actual_doj = $_POST['actual_doj'] ?? NULL;
    
    // Build the update query based on status
    $query = "UPDATE submissions SET status = ?";
    $types = "s";
    $params = array($new_status);
    
    // Add fields based on the new status
    if ($new_status === 'Offered' || $new_status === 'Hired') {
        $query .= ", offered_ctc = ?, expected_doj = ?";
        $types .= "ss";
        $params[] = $offered_ctc;
        $params[] = $expected_doj;
        
        // Clear joined fields if moving back from Joined status
        if ($current_status === 'Joined') {
            $query .= ", joined_ctc = NULL, actual_doj = NULL";
        }
    } 
    elseif ($new_status === 'Joined') {
        $query .= ", joined_ctc = ?, actual_doj = ?";
        $types .= "ss";
        $params[] = $joined_ctc;
        $params[] = $actual_doj;
        
        // If offered CTC is not provided but we have joined CTC, use it as offered CTC
        if (empty($offered_ctc) && !empty($joined_ctc)) {
            $query .= ", offered_ctc = ?";
            $types .= "s";
            $params[] = $joined_ctc;
        }
    }
    elseif ($new_status === 'Rejected') {
        // Keep the existing offered details but set status to rejected
        // No additional fields to update
    }
    
    $query .= " WHERE id = ?";
    $types .= "i";
    $params[] = $submission_id;
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Dynamically bind parameters
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Candidate status updated to $new_status successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating candidate status: " . $stmt->error;
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Error preparing statement: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
} else {
    $_SESSION['message'] = "Invalid request parameters.";
    $_SESSION['msg_type'] = "danger";
}

// Redirect back to placement management page
header("location: placement_management.php");
exit();
?>