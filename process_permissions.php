<?php
require_once 'config.php';
require_once 'auth.php';

// Only admins can save permissions
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['save_permissions'])) {
    
    // Get the submitted permissions from the form, default to an empty array
    $submitted_permissions = $_POST['permissions'] ?? [];
    
    // Define the master list of all possible resources and roles to ensure a complete reset
    $resources = ['Clients', 'Contacts', 'Job Orders', 'Candidates', 'Interviews', 'Reports', 'Attendance', 'Leaves'];
    $roles = ['admin', 'recruiter', 'delivery_manager'];
    
    // Use a database transaction for an all-or-nothing update
    $conn->begin_transaction();
    try {
        // Step 1: Clear all existing permissions to start fresh
        $conn->query("DELETE FROM role_permissions");

        // Step 2: Prepare a single statement for inserting new permissions
        $stmt = $conn->prepare("
            INSERT INTO role_permissions (role, resource, can_view, can_create, can_edit, can_delete) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        // Step 3: Loop through all possible permissions and insert their new values
        foreach ($roles as $role) {
            foreach ($resources as $resource) {
                // Determine the value for each action (1 if the checkbox was checked, 0 if not)
                $can_view = isset($submitted_permissions[$role][$resource]['view']) ? 1 : 0;
                $can_create = isset($submitted_permissions[$role][$resource]['create']) ? 1 : 0;
                $can_edit = isset($submitted_permissions[$role][$resource]['edit']) ? 1 : 0;
                $can_delete = isset($submitted_permissions[$role][$resource]['delete']) ? 1 : 0;
                
                // Bind the parameters and execute the insert for this role/resource combo
                $stmt->bind_param("ssiiii", $role, $resource, $can_view, $can_create, $can_edit, $can_delete);
                $stmt->execute();
            }
        }
        
        // If everything was successful, commit the changes to the database
        $conn->commit();
        $_SESSION['message'] = "Permissions have been updated successfully!";
        $_SESSION['msg_type'] = "success";
        
    } catch (Exception $e) {
        // If any error occurred, roll back all changes so the database is not left in a partial state
        $conn->rollback();
        $_SESSION['message'] = "An error occurred while saving permissions.";
        $_SESSION['msg_type'] = "danger";
        // Optional: log the actual error for debugging
        // error_log('Permission save error: ' . $e->getMessage());
    }

    $stmt->close();
}

// Redirect back to the permissions page to show the result
header("location: permissions.php");
exit();
?>