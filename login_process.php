<?php
require_once 'config.php';
require_once 'auth.php'; // This includes the loadPermissions() function

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Basic validation
    if (empty(trim($_POST["email"])) || empty(trim($_POST["password"]))) {
        $_SESSION['message'] = "Please enter both email and password.";
        $_SESSION['msg_type'] = "danger";
        header("location: login.php");
        exit();
    }

    // Prepare a select statement to find the user by email
    $sql = "SELECT id, full_name, email, password, role FROM users WHERE email = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $_POST['email']);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($_POST['password'], $user['password'])) {
                    // Password is correct, so start a new session
                    
                    // Set the basic session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    // THIS IS THE CRUCIAL STEP
                    // Load the permissions for this user's role into the session
                    $_SESSION['permissions'] = loadPermissions($user['role'], $conn);

                    // Redirect to the dashboard
                    header("location: dashboard.php");
                    exit();
                    
                } else {
                    // Password is not valid
                    $_SESSION['message'] = "Invalid email or password.";
                    $_SESSION['msg_type'] = "danger";
                }
            } else {
                // No account found with that email
                $_SESSION['message'] = "Invalid email or password.";
                $_SESSION['msg_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
}

// If anything fails, redirect back to the login page
header("location: login.php");
exit();

?>