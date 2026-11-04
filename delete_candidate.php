<?php
require 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // First, get the filename of the resume to delete the file from server
    $stmt_get = $conn->prepare("SELECT resume_filename FROM candidates WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    if ($result->num_rows > 0) {
        $candidate = $result->fetch_assoc();
        $filename = $candidate['resume_filename'];
        // If a resume file exists, delete it
        if (!empty($filename) && file_exists('uploads/' . $filename)) {
            unlink('uploads/' . $filename);
        }
    }
    $stmt_get->close();

    // Now, delete the candidate record from the database
    $stmt_delete = $conn->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt_delete->bind_param("i", $id);

    if ($stmt_delete->execute()) {
        $_SESSION['message'] = "Candidate has been deleted successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error: Could not delete candidate.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt_delete->close();

    header("location: candidates.php");
    exit();
}
?>