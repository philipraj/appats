<?php
// Ensure the session is always started when this file is included.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 * @return bool True if logged in, false otherwise.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Loads all permissions for a given role from the database.
 * @param string $role The user's role (e.g., 'admin', 'recruiter').
 * @param mysqli $conn The database connection object.
 * @return array An associative array of permissions.
 */
function loadPermissions($role, $conn) {
    $permissions = [];
    
    try {
        $stmt = $conn->prepare("SELECT resource, can_view, can_create, can_edit, can_delete FROM role_permissions WHERE role = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $role);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $permissions[$row['resource']] = [
                            'view' => (bool)$row['can_view'],
                            'create' => (bool)$row['can_create'],
                            'edit' => (bool)$row['can_edit'],
                            'delete' => (bool)$row['can_delete']
                        ];
                    }
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Error loading permissions: " . $e->getMessage());
    }
    
    return $permissions;
}

/**
 * Checks if the currently logged-in user has a specific permission.
 * @param string $resource The resource to check (e.g., 'job_orders').
 * @param string $action The action to check (e.g., 'view', 'create', 'edit', 'delete').
 * @return bool True if the user has the permission, false otherwise.
 */
function hasPermission($resource, $action) {
    // Make sure the user is logged in
    if (!isLoggedIn()) {
        return false;
    }

    // Always grant full access for admin role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    // If permissions aren't loaded yet, try to load them
    if (!isset($_SESSION['permissions']) && isset($_SESSION['role']) && isset($GLOBALS['conn'])) {
        $_SESSION['permissions'] = loadPermissions($_SESSION['role'], $GLOBALS['conn']);
    }

    // Check if permissions are loaded and the specific resource exists
    if (!isset($_SESSION['permissions'][$resource])) {
        // If we don't have specific permissions for this resource, be permissive
        // You might want to change this to false for stricter security
        return true;
    }

    // Check the specific action permission
    switch ($action) {
        case 'view':
            return $_SESSION['permissions'][$resource]['view'] === true;
        case 'create':
            return $_SESSION['permissions'][$resource]['create'] === true;
        case 'edit':
            return $_SESSION['permissions'][$resource]['edit'] === true;
        case 'delete':
            return $_SESSION['permissions'][$resource]['delete'] === true;
        default:
            return true; // Be permissive for unknown actions
    }
}
?>