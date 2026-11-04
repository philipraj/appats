<?php
include 'header.php';
require_once 'auth.php';

if (!hasPermission('job_orders', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_interview'])) {
    $interview_id = $_POST['interview_id'];
    $cancellation_reason = $_POST['cancellation_reason'];
    
    // Get interview details first
    $stmt = $conn->prepare("
        SELECT i.*, s.id as submission_id, s.status as current_status, s.job_id, i.interview_round
        FROM interviews i 
        JOIN submissions s ON i.submission_id = s.id 
        WHERE i.id = ?
    ");
    $stmt->bind_param('i', $interview_id);
    $stmt->execute();
    $interview = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($interview) {
        // Determine the previous status based on interview round
        $previous_status = getPreviousStatus($interview['interview_round']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update interview as cancelled
            $stmt = $conn->prepare("
                UPDATE interviews 
                SET cancelled_at = NOW(), cancellation_reason = ?
                WHERE id = ?
            ");
            $stmt->bind_param('si', $cancellation_reason, $interview_id);
            $stmt->execute();
            $stmt->close();
            
            // Update submission status to previous status
            $stmt = $conn->prepare("
                UPDATE submissions 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param('si', $previous_status, $interview['submission_id']);
            $stmt->execute();
            $stmt->close();
            
            // Log the activity
            $activity_stmt = $conn->prepare("
                INSERT INTO activity_log (user_id, activity_type, description, related_id, related_type) 
                VALUES (?, 'interview_cancelled', ?, ?, 'interview')
            ");
            $user_id = $_SESSION['user_id'];
            $description = "Cancelled {$interview['interview_round']} interview for submission #{$interview['submission_id']}. Reason: {$cancellation_reason}";
            $activity_stmt->bind_param('isi', $user_id, $description, $interview_id);
            $activity_stmt->execute();
            $activity_stmt->close();
            
            $conn->commit();
            
            $_SESSION['message'] = "Interview cancelled successfully. Status rolled back to {$previous_status}.";
            $_SESSION['msg_type'] = "warning";
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error cancelling interview: " . $e->getMessage();
            $_SESSION['msg_type'] = "danger";
        }
        
        header("location: pipeline_tracking.php");
        exit();
        
    } else {
        $_SESSION['message'] = "Interview not found.";
        $_SESSION['msg_type'] = "danger";
        header("location: pipeline_tracking.php");
        exit();
    }
}

function getPreviousStatus($current_round) {
    $status_map = [
        'L1 interview' => 'Submitted to client',
        'L2 interview' => 'L1 interview', 
        'Customer Interview' => 'L2 interview',
        'HR Interview' => 'Customer Interview'
    ];
    
    return $status_map[$current_round] ?? 'Submitted to client';
}
?>