<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('candidates', 'create')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: candidates.php");
    exit();
}

if (isset($_POST['add_candidate'])) {
    
    // Validation
    if (empty($_POST['candidate_name']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) || empty($_FILES['resume']['name'])) {
        $_SESSION['message'] = "Name, a valid Email, and a Resume file are required.";
        $_SESSION['msg_type'] = "danger";
        header("location: add_candidate.php");
        exit();
    }
    
    // File Upload Handling
    $upload_dir = 'uploads/';
    $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
    $resume_filename = 'resume_' . uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $resume_filename;
    if (!move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
        $_SESSION['message'] = "Error uploading resume.";
        header("location: add_candidate.php");
        exit();
    }
    
    $resume_text = null; 

    // Sanitize & Prepare Data for ALL fields that exist in the database
    $candidate_name = htmlspecialchars($_POST['candidate_name'], ENT_QUOTES, 'UTF-8');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8');
    $current_location = htmlspecialchars($_POST['current_location'], ENT_QUOTES, 'UTF-8');
    $can_relocate = isset($_POST['can_relocate']) ? 1 : 0;
    $current_ctc = htmlspecialchars($_POST['current_ctc'], ENT_QUOTES, 'UTF-8');
    $expected_ctc = htmlspecialchars($_POST['expected_ctc'], ENT_QUOTES, 'UTF-8');
    $notice_period = htmlspecialchars($_POST['notice_period'], ENT_QUOTES, 'UTF-8');
    $comments = htmlspecialchars($_POST['comments'], ENT_QUOTES, 'UTF-8');
    $created_by_id = $_SESSION['user_id'];
    $job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : NULL;
    
    $conn->begin_transaction();
    try {
        // This SQL query now perfectly matches your database table structure.
        $sql = "INSERT INTO candidates (
                    candidate_name, phone, email, current_location, can_relocate, 
                    current_ctc, expected_ctc, notice_period, comments, 
                    resume_filename, resume_text, created_by_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            // This will catch errors if the column names are still wrong
            throw new Exception("Failed to prepare the SQL query: " . $conn->error);
        }
        
        $stmt->bind_param("ssssissssssi", 
            $candidate_name, $phone, $email, $current_location, $can_relocate,
            $current_ctc, $expected_ctc, $notice_period, $comments,
            $resume_filename, $resume_text, $created_by_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Database error while creating candidate: " . $stmt->error);
        }
        
        $new_candidate_id = $conn->insert_id;
        $_SESSION['message'] = "Candidate added successfully!";
        
        if ($job_id && $new_candidate_id) {
            $initial_status = 'Sourced'; 
            $submit_stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status) VALUES (?, ?, ?, ?)");
            $submit_stmt->bind_param("iiis", $job_id, $new_candidate_id, $created_by_id, $initial_status);
            if ($submit_stmt->execute()) { $_SESSION['message'] = "Candidate added and assigned to the job successfully!"; }
            $submit_stmt->close();
        }
        
        $conn->commit();
        $_SESSION['msg_type'] = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        // THE FIX: This now shows the SPECIFIC database error message
        $_SESSION['message'] = "An error occurred: " . $e->getMessage();
        $_SESSION['msg_type'] = "danger";
        if (file_exists($target_file)) { unlink($target_file); }
    }
    
    if ($job_id) {
        header("location: view_job.php?id=" . $job_id);
    } else {
        header("location: candidates.php");
    }
    exit();
}
?>