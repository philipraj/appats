<?php
include 'header.php';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
require_once 'auth.php';

// Only admins should access this page
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// Define all resources, actions, and roles
$resources = ['Clients', 'Contacts', 'Job Orders', 'Candidates', 'Interviews', 'Reports', 'Attendance', 'Leaves'];
$actions = ['view', 'create', 'edit', 'delete'];
$roles = ['admin', 'recruiter', 'delivery_manager'];

// Fetch all current permissions from the database to pre-check the boxes
$current_permissions = [];
$result = $conn->query("SELECT * FROM role_permissions");
while ($row = $result->fetch_assoc()) {
    $current_permissions[$row['role']][$row['resource']] = [
        'view'   => (bool)$row['can_view'],
        'create' => (bool)$row['can_create'],
        'edit'   => (bool)$row['can_edit'],
        'delete' => (bool)$row['can_delete']
    ];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-shield-lock-fill me-2"></i>Role Permissions</h1>
</div>

<div class="card">
    <form action="process_permissions.php" method="POST">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Resource</th>
                        <th style="width: 10%;">Permission</th>
                        <?php foreach ($roles as $role): ?>
                            <th class="text-center"><?php echo ucwords(str_replace('_', ' ', $role)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resources as $resource): ?>
                        <?php foreach ($actions as $i => $action): ?>
                            <tr>
                                <?php if ($i === 0): // Show resource name only on the first row of its group ?>
                                    <td rowspan="<?php echo count($actions); ?>" class="align-middle fw-bold">
                                        <?php echo $resource; ?>
                                    </td>
                                <?php endif; ?>
                                <td><?php echo ucfirst($action); ?></td>
                                <?php foreach ($roles as $role): ?>
                                    <td class="text-center align-middle">
                                        <div class="form-check d-inline-block">
                                            <?php
                                                // Check if the permission is set for this role/resource/action
                                                $is_checked = $current_permissions[$role][$resource][$action] ?? false;
                                            ?>
                                            <input class="form-check-input" type="checkbox" 
                                                   name="permissions[<?php echo $role; ?>][<?php echo $resource; ?>][<?php echo $action; ?>]" 
                                                   value="1" <?php if ($is_checked) echo 'checked'; ?>>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-end">
            <button type="submit" name="save_permissions" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save All Permissions</button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>