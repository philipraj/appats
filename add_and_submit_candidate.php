<?php
require 'config.php';
require 'auth.php';
require 'text_extractor.php';

// Security Checks
if (!hasPermission('candidates', 'create') || !hasPermission('job_orders', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['add_and_submit'])) {
    $job_id = $_POST['job_id'];
    $recruiter_id = $_SESSION['user_id'];
    $candidate_name = $_POST['candidate_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    // --- NEW: DUPLICATE CHECK ---
    // Check if a candidate with this email already exists before doing anything else.
    if (!empty($email)) {
        $check_stmt = $conn->prepare("SELECT id FROM candidates WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result->num_rows > 0) {
            // If the candidate exists, stop and show a warning message.
            $_SESSION['message'] = "A candidate with this email address already exists. Please use the 'Submit an Existing Candidate' dropdown instead.";
            $_SESSION['msg_type'] = "warning";
            header("location: view_job.php?id=" . $job_id);
            exit();
        }
    }
    // --- END DUPLICATE CHECK ---


    // Check for mandatory resume file
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] != 0) {
        $_SESSION['message'] = "Error: Resume upload is mandatory.";
        $_SESSION['msg_type'] = "danger";
        header("location: view_job.php?id=" . $job_id);
        exit();
    }

    // --- 1. Create the Candidate (Logic from add_candidate_process.php) ---
    $resume_filename = NULL;
    $resume_text = NULL;

    $target_dir = "uploads/";
    $file_extension = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);
    $resume_filename = 'resume-' . uniqid() . '-' . time() . '.' . $file_extension;
    $target_file = $target_dir . $resume_filename;
    if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
        $resume_text = extractTextFromFile($target_file);
    } else {
        $_SESSION['message'] = "Error uploading resume.";
        $_SESSION['msg_type'] = "danger";
        header("location: view_job.php?id=" . $job_id);
        exit();
    }
    
    // Use other fields from the modal form here...
    $stmt_add = $conn->prepare("INSERT INTO candidates (candidate_name, email, phone, resume_filename, resume_text, created_by_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_add->bind_param("sssssi", $candidate_name, $email, $phone, $resume_filename, $resume_text, $recruiter_id);
    
    if ($stmt_add->execute()) {
        $new_candidate_id = $conn->insert_id;

        // --- 2. Create the Submission Record ---
        if ($new_candidate_id) {
            $initial_status = 'Sourced';
            $submit_stmt = $conn->prepare("INSERT INTO submissions (job_id, candidate_id, recruiter_id, status) VALUES (?, ?, ?, ?)");
            $submit_stmt->bind_param("iiis", $job_id, $new_candidate_id, $recruiter_id, $initial_status);
            if ($submit_stmt->execute()) {
                $_SESSION['message'] = "New candidate created and submitted to this job successfully!";
                $_SESSION['msg_type'] = "success";
            }
        }
    } else {
        $_SESSION['message'] = "Error: Could not create the new candidate.";
        $_SESSION['msg_type'] = "danger";
    }

    // Redirect back to the job detail page
    header("location: view_job.php?id=" . $job_id);
    exit();
}
?>