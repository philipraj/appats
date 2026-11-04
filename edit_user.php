<?php
include 'header.php';

// Security Check: Only admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_GET['id'])) {
    header("location: users.php");
    exit();
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT full_name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    header("location: users.php");
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Edit User</h1>
    <a href="users.php" class="btn btn-secondary">Back to User List</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="update_user.php" method="POST">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="mb-3">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Role <span class="text-danger">*</span></label>
                <select name="role" class="form-select" required>
                    <option value="recruiter" <?php echo ($user['role'] == 'recruiter') ? 'selected' : ''; ?>>Recruiter</option>
                    <option value="team_lead" <?php echo ($user['role'] == 'team_lead') ? 'selected' : ''; ?>>Team Lead</option>
                    <option value="account_manager" <?php echo ($user['role'] == 'account_manager') ? 'selected' : ''; ?>>Account Manager</option>
                    <option value="delivery_manager" <?php echo ($user['role'] == 'delivery_manager') ? 'selected' : ''; ?>>Delivery Manager</option>
                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <hr>
            <h5 class="mt-4">Reset Password</h5>
            <div class="mb-3">
                <label class="form-label">New Password (leave blank to keep current password)</label>
                <input type="password" class="form-control" name="password">
            </div>

            <div class="mt-4 text-end">
                <a href="users.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>