<?php
require 'config.php';
require 'auth.php';

// Security Check
if (!hasPermission('permissions', 'edit')) {
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['save_permissions'])) {
    $permissions = $_POST['perm'];
    $stmt = $conn->prepare("UPDATE role_permissions SET can_view = ?, can_create = ?, can_edit = ?, can_delete = ? WHERE role = ? AND resource = ?");

    foreach ($permissions as $role => $resources) {
        foreach ($resources as $resource => $actions) {
            $can_view = isset($actions['can_view']) ? 1 : 0;
            $can_create = isset($actions['can_create']) ? 1 : 0;
            $can_edit = isset($actions['can_edit']) ? 1 : 0;
            $can_delete = isset($actions['can_delete']) ? 1 : 0;
            
            $stmt->bind_param("iiiiss", $can_view, $can_create, $can_edit, $can_delete, $role, $resource);
            $stmt->execute();
        }
    }
    $stmt->close();
    $_SESSION['message'] = "Permissions updated successfully!";
    $_SESSION['msg_type'] = "success";
}
header("location: permissions.php");
exit();
?>