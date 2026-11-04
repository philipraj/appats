<?php
require 'config.php';
require_once 'auth.php';

// Security check: Only users with permission should access this
if (!hasPermission('job_orders', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['submission_ids']) && is_array($_POST['submission_ids'])) {
    $submission_ids = $_POST['submission_ids'];
    $action = $_POST['bulk_action'];
    $new_status = '';

    if ($action == 'approve') {
        $new_status = 'Submitted to client';
    } elseif ($action == 'reject') {
        $new_status = 'Screen Reject';
    }

    if (!empty($new_status) && !empty($submission_ids)) {
        // Create placeholders for the IN clause (e.g., ?, ?, ?)
        $placeholders = implode(',', array_fill(0, count($submission_ids), '?'));
        $types = str_repeat('i', count($submission_ids));
        
        $stmt = $conn->prepare("UPDATE submissions SET status = ? WHERE id IN ($placeholders)");
        // Prepend the status to the params array for binding
        $params = array_merge([$new_status], $submission_ids);
        $stmt->bind_param("s" . $types, ...$params);

        if ($stmt->execute()) {
            $_SESSION['message'] = count($submission_ids) . " candidate(s) have been updated successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not update statuses.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
}

header("location: dashboard.php");
exit();
?>