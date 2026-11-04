<?php
require_once 'config.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = (int)$_POST['submission_id'];
    $new_status = $_POST['status'];
    
    // Get current status first
    $current_stmt = $conn->prepare("SELECT status FROM submissions WHERE id = ?");
    $current_stmt->bind_param("i", $submission_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    
    if ($current_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Submission not found']);
        exit();
    }
    
    $current_status = $current_result->fetch_assoc()['status'];
    $current_stmt->close();
    
    // Update the submission status
    $stmt = $conn->prepare("UPDATE submissions SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $submission_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Status updated successfully',
            'original_status' => $current_status
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database update failed',
            'original_status' => $current_status
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>