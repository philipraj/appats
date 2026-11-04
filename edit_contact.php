<?php
include 'header.php';
require_once 'auth.php';

if (!hasPermission('contacts', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: contacts.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("location: contacts.php");
    exit();
}
$id = $_GET['id'];

// Fetch the specific contact's details
$contact_stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
$contact_stmt->bind_param("i", $id);
$contact_stmt->execute();
$contact = $contact_stmt->get_result()->fetch_assoc();

if (!$contact) {
    header("location: contacts.php");
    exit();
}

// Fetch data for dropdowns
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT id, full_name FROM users ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
?>

<h1 class="mb-4">Edit Contact</h1>

<div class="card">
    <div class="card-body">
        <form action="update_contact.php" method="POST">
            <input type="hidden" name="id" value="<?php echo $contact['id']; ?>">
            
            <div class="mb-3">
                <label class="form-label">Client Name <span class="text-danger">*</span></label>
                <select name="client_id" class="form-select" required>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>" <?php echo ($client['id'] == $contact['client_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['client_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="contact_name" value="<?php echo htmlspecialchars($contact['contact_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contact Email</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($contact['email']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Contact Phone</label>
                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($contact['phone']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Owner</label>
                <select name="owner_id" class="form-select">
                    <option value="">-- Select Owner --</option>
                    <?php foreach ($users as $user): ?>
                         <option value="<?php echo $user['id']; ?>" <?php echo ($user['id'] == $contact['owner_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <a href="contacts.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" name="update_contact" class="btn btn-primary">Update Contact</button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>