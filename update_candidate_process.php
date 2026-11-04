<?php
require 'config.php';
require 'text_extractor.php'; // Include our new extractor

if (isset($_POST['update_candidate'])) {
    $id = $_POST['id'];
    $candidate_name = $_POST['candidate_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $current_location = $_POST['current_location'];
    $current_ctc = $_POST['current_ctc'];
    $expected_ctc = $_POST['expected_ctc'];
    $notice_period = $_POST['notice_period'];
    $comments = $_POST['comments'];
    $can_relocate = isset($_POST['can_relocate']) ? 1 : 0;
    
    $resume_filename = $_POST['old_resume'];
    $resume_text = NULL; // We will update this only if a new file is uploaded

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $target_dir = "uploads/";
        $file_extension = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);
        $new_resume_filename = 'resume-' . uniqid() . '-' . time() . '.' . $file_extension;
        $target_file = $target_dir . $new_resume_filename;
        $allowed_types = ['pdf', 'doc', 'docx'];

        if (in_array(strtolower($file_extension), $allowed_types) && move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
            if (!empty($resume_filename) && file_exists('uploads/' . $resume_filename)) {
                unlink('uploads/' . $resume_filename);
            }
            $resume_filename = $new_resume_filename;
            // --- NEW: Extract text from the new file ---
            $resume_text = extractTextFromFile($target_file);
        }
    }

    if (empty($candidate_name) || empty($email) || empty($phone)) {
        $_SESSION['message'] = "Name, Email, and Phone are required fields.";
    } else {
        // If text was extracted, update the resume_text column. Otherwise, it's ignored.
        if ($resume_text !== NULL) {
            $stmt = $conn->prepare("UPDATE candidates SET candidate_name=?, email=?, phone=?, current_location=?, can_relocate=?, current_ctc=?, expected_ctc=?, notice_period=?, comments=?, resume_filename=?, resume_text=? WHERE id=?");
            $stmt->bind_param("ssssissssssi", $candidate_name, $email, $phone, $current_location, $can_relocate, $current_ctc, $expected_ctc, $notice_period, $comments, $resume_filename, $resume_text, $id);
        } else {
            // If no new resume, don't update the text column
            $stmt = $conn->prepare("UPDATE candidates SET candidate_name=?, email=?, phone=?, current_location=?, can_relocate=?, current_ctc=?, expected_ctc=?, notice_period=?, comments=?, resume_filename=? WHERE id=?");
            $stmt->bind_param("ssssisssssi", $candidate_name, $email, $phone, $current_location, $can_relocate, $current_ctc, $expected_ctc, $notice_period, $comments, $resume_filename, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['message'] = "Candidate updated successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating candidate: " . $stmt->error;
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
    header("location: candidates.php");
    exit();
}
?>