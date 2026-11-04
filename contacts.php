<?php
include 'header.php';
require_once 'auth.php';

if (!hasPermission('contacts', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT id, full_name FROM users ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

$contacts_result = $conn->query("
    SELECT co.id, co.contact_name, co.email, co.phone, cl.client_name, u.full_name as owner_name
    FROM contacts co
    LEFT JOIN clients cl ON co.client_id = cl.id
    LEFT JOIN users u ON co.owner_id = u.id
    ORDER BY co.contact_name
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-person-rolodex me-2"></i>Contacts</h1>
    <?php if (hasPermission('contacts', 'create')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
            <i class="bi bi-plus-circle me-1"></i> Add Contact
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>Contact Name</th><th>Email</th><th>Phone</th><th>Client</th><th>Owner</th><th>Actions</th></tr></thead>
            <tbody>
                <?php while ($row = $contacts_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['contact_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['client_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['owner_name']); ?></td>
                        <td>
                            <?php if (hasPermission('contacts', 'edit')): ?>
                                <a href="edit_contact.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-pencil-square"></i></a>
                            <?php endif; ?>
                            <?php if (hasPermission('contacts', 'delete')): ?>
                                <a href="delete_contact.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this contact?');"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (hasPermission('contacts', 'create')): ?>
<div class="modal fade" id="addContactModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add New Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form action="add_contact.php" method="POST"><div class="modal-body">
            <div class="mb-3"><label class="form-label">Client Name <span class="text-danger">*</span></label><select name="client_id" class="form-select" required><option value="">-- Select Client --</option><?php foreach ($clients as $client): ?><option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['client_name']); ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">Contact Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="contact_name" required></div>
            <div class="mb-3"><label class="form-label">Contact Email</label><input type="email" class="form-control" name="email"></div>
            <div class="mb-3"><label class="form-label">Contact Phone</label><input type="tel" class="form-control" name="phone"></div>
            <div class="mb-3"><label class="form-label">Owner</label><select name="owner_id" class="form-select"><option value="">-- Select Owner --</option><?php foreach ($users as $user): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option><?php endforeach; ?></select></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="add_contact" class="btn btn-primary">Save Contact</button></div></form>
    </div></div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>