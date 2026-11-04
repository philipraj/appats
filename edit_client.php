<?php
include 'header.php';

// Check if ID is present
if (!isset($_GET['id'])) {
    header("location: clients.php");
    exit();
}

$id = $_GET['id'];

// Fetch the specific client's details
$client_stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$client_stmt->bind_param("i", $id);
$client_stmt->execute();
$client = $client_stmt->get_result()->fetch_assoc();

if (!$client) {
    // No client found with this ID
    header("location: clients.php");
    exit();
}

// Fetch all users for the dropdown
$users_result = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
$users = $users_result->fetch_all(MYSQLI_ASSOC);
?>

<h1 class="mb-4">Edit Client</h1>

<div class="card">
    <div class="card-body">
        <form action="update_client.php" method="POST">
            <input type="hidden" name="id" value="<?php echo $client['id']; ?>">
            
            <div class="mb-3">
                <label for="client_name" class="form-label">Client Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="client_name" value="<?php echo htmlspecialchars($client['client_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="website" class="form-label">Website</label>
                <input type="url" class="form-control" name="website" value="<?php echo htmlspecialchars($client['website']); ?>" placeholder="https://example.com">
            </div>
            <div class="mb-3">
                <label for="owner_id" class="form-label">Owner</label>
                <select name="owner_id" class="form-select">
                    <option value="">-- Select Owner --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo ($user['id'] == $client['owner_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <a href="clients.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" name="update_client" class="btn btn-primary">Update Client</button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>