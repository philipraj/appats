<?php
require 'config.php';

if (isset($_FILES['profile_photo'])) {
    // File upload configuration
    $target_dir = "uploads/profiles/";
    $file_extension = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
    $new_filename = 'user-' . $_SESSION['user_id'] . '-' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["profile_photo"]["tmp_name"]);
    if($check === false) {
        $_SESSION['message'] = "Error: File is not an image.";
        $_SESSION['msg_type'] = "danger";
        header("location: profile.php");
        exit();
    }

    // Check file size (e.g., 2MB limit)
    if ($_FILES["profile_photo"]["size"] > 2000000) {
        $_SESSION['message'] = "Error: Your file is too large (Max 2MB).";
        $_SESSION['msg_type'] = "danger";
        header("location: profile.php");
        exit();
    }

    // Allow certain file formats
    $allowed_types = ['jpg', 'png', 'jpeg', 'gif'];
    if(!in_array(strtolower($file_extension), $allowed_types)) {
        $_SESSION['message'] = "Error: Only JPG, JPEG, PNG & GIF files are allowed.";
        $_SESSION['msg_type'] = "danger";
        header("location: profile.php");
        exit();
    }

    // --- Process Upload ---
    // First, get the old photo filename to delete it after successful upload
    $old_photo = $_SESSION['profile_photo'];

    if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
        // Update the database with the new filename
        $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
        $stmt->bind_param("si", $new_filename, $_SESSION['user_id']);
        if ($stmt->execute()) {
            // If database update is successful, delete the old photo file
            if (!empty($old_photo) && file_exists($target_dir . $old_photo)) {
                unlink($target_dir . $old_photo);
            }
            
            // Update the session variable immediately
            $_SESSION['profile_photo'] = $new_filename;
            
            $_SESSION['message'] = "Profile photo updated successfully!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error: Could not update database record.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Error: There was a problem uploading your file.";
        $_SESSION['msg_type'] = "danger";
    }
}

header("location: profile.php");
exit();
?>