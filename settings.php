<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'header.php';


$page = isset($_GET['page']) ? $_GET['page'] : 'change_password';

// Fetch email settings
$email_settings_result = $conn->query("SELECT * FROM app_settings WHERE setting_key LIKE 'smtp_%'");
$email_settings = [];
while($row = $email_settings_result->fetch_assoc()) {
    $email_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-gear-fill me-2"></i>Settings</h1>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="list-group">
            <a href="settings.php?page=change_password" class="list-group-item list-group-item-action <?php echo ($page == 'change_password') ? 'active' : ''; ?>">Change Password</a>
            <?php if (hasPermission('permissions', 'view')): ?>
                <div class="mt-3">
                    <h6 class="px-3 text-muted">Admin Settings</h6>
                    <a href="permissions.php" class="list-group-item list-group-item-action">Permissions Matrix</a>
                    <a href="settings.php?page=email_config" class="list-group-item list-group-item-action <?php echo ($page == 'email_config') ? 'active' : ''; ?>">Email Configuration</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card"><div class="card-body">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($page == 'change_password'): ?>
                <h4 class="card-title">Change Your Password</h4><hr>
                <form action="change_password_process.php" method="POST">
                    <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                    <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" required></div>
                    <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_new_password" required></div>
                    <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                </form>
            <?php elseif ($page == 'email_config' && hasPermission('permissions', 'view')): ?>
                <h4 class="card-title">Email (SMTP) Configuration</h4><hr>
                <form action="update_email_settings.php" method="POST">
                    <div class="mb-3"><label class="form-label">SMTP Host</label><input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($email_settings['smtp_host'] ?? ''); ?>"></div>
                    <div class="mb-3"><label class="form-label">SMTP Username</label><input type="email" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($email_settings['smtp_user'] ?? ''); ?>"></div>
                    <div class="mb-3"><label class="form-label">SMTP Password</label><input type="password" class="form-control" name="smtp_pass" placeholder="Enter new password or leave blank"></div>
                    <div class="mb-3"><label class="form-label">SMTP Port</label><input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($email_settings['smtp_port'] ?? '587'); ?>"></div>
                    <div class="mb-3"><label class="form-label">SMTP Encryption</label><select name="smtp_secure" class="form-select"><option value="tls" <?php echo (($email_settings['smtp_secure'] ?? '') == 'tls') ? 'selected' : ''; ?>>TLS</option><option value="ssl" <?php echo (($email_settings['smtp_secure'] ?? '') == 'ssl') ? 'selected' : ''; ?>>SSL</option></select></div>
                    <button type="submit" name="save_email_settings" class="btn btn-primary">Save Email Settings</button>
                </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php include 'footer.php'; ?>