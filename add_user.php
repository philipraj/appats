<?php
require 'config.php';
// Include our new email function
require 'send_email.php';

// Security check to ensure only admins can process this form
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['add_user'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password']; // This is the plain text password
    $role = $_POST['role'];

    // Validate inputs
    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $_SESSION['message'] = "All fields are required.";
        $_SESSION['msg_type'] = "danger";
        header("location: users.php");
        exit();
    }

    // Check if user already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $_SESSION['message'] = "A user with this email address already exists.";
        $_SESSION['msg_type'] = "danger";
        header("location: users.php");
        exit();
    }
    $check_stmt->close();

    // Securely hash the password for database storage
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Prepare and execute the INSERT statement
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);

    if ($stmt->execute()) {
        $_SESSION['message'] = "User has been added successfully!";
        $_SESSION['msg_type'] = "success";
        
        // --- NEW: Send Welcome Email ---
        $subject = "Welcome to the Mount Graph Technologies ATS";
        $email_body = "
            <h2>Welcome, " . htmlspecialchars($full_name) . "!</h2>
            <p>An account has been created for you on the Mount Graph Technologies ATS portal.</p>
            <p>You can log in using the following credentials:</p>
            <ul>
                <li><strong>URL:</strong> <a href='" . BASE_URL . "'>" . BASE_URL . "</a></li>
                <li><strong>Username:</strong> " . htmlspecialchars($email) . "</li>
                <li><strong>Password:</strong> " . htmlspecialchars($password) . "</li>
            </ul>
            <p>Please log in and change your password from the Settings page at your earliest convenience.</p>
        ";
        
        // Call the sendEmail function (we don't need to check the result for now)
        sendEmail($email, $subject, $email_body);
        
    } else {
        $_SESSION['message'] = "Error: Could not add user.";
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();

    header("location: users.php");
    exit();
}
?>