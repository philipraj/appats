<?php
include 'header.php';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
require_once 'auth.php';

// Security check
if (!hasPermission('clients', 'view') && !hasPermission('contacts', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// --- CONFIGURATION ---
$limit = 15;
$active_tab = $_GET['tab'] ?? 'clients'; // Default to clients tab

// --- DATA & PAGINATION FOR CLIENTS ---
$client_search = $_GET['client_search'] ?? '';
$client_page = isset($_GET['cpage']) ? (int)$_GET['cpage'] : 1;
$client_offset = ($client_page - 1) * $limit;
$client_params = [];
$client_types = '';
$client_where = '';
if (!empty($client_search)) {
    $client_where = " WHERE c.client_name LIKE ?";
    $client_params[] = "%{$client_search}%";
    $client_types .= 's';
}
$client_total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM clients c" . $client_where);
if (!empty($client_params)) { $client_total_stmt->bind_param($client_types, ...$client_params); }
$client_total_stmt->execute();
$client_total_pages = ceil($client_total_stmt->get_result()->fetch_assoc()['total'] / $limit);
$client_total_stmt->close();
$clients_sql = "SELECT c.id, c.client_name, c.website, u.full_name as owner_name 
                FROM clients c LEFT JOIN users u ON c.owner_id = u.id" . $client_where . " 
                ORDER BY c.client_name LIMIT ? OFFSET ?";
$client_params[] = $limit; $client_types .= 'i';
$client_params[] = $client_offset; $client_types .= 'i';
$client_stmt = $conn->prepare($clients_sql);
$client_stmt->bind_param($client_types, ...$client_params);
$client_stmt->execute();
$clients_result = $client_stmt->get_result();

// --- DATA & PAGINATION FOR CONTACTS ---
$contact_search = $_GET['contact_search'] ?? '';
$contact_page = isset($_GET['copage']) ? (int)$_GET['copage'] : 1;
$contact_offset = ($contact_page - 1) * $limit;
$contact_params = [];
$contact_types = '';
$contact_where = '';
if (!empty($contact_search)) {
    $contact_where = " WHERE co.contact_name LIKE ? OR co.email LIKE ?";
    $search_param = "%{$contact_search}%";
    $contact_params[] = $search_param;
    $contact_params[] = $search_param;
    $contact_types .= 'ss';
}
$contact_total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM contacts co" . $contact_where);
if (!empty($contact_params)) { $contact_total_stmt->bind_param($contact_types, ...$contact_params); }
$contact_total_stmt->execute();
$contact_total_pages = ceil($contact_total_stmt->get_result()->fetch_assoc()['total'] / $limit);
$contact_total_stmt->close();
$contacts_sql = "SELECT co.id, co.contact_name, co.email, co.phone, cl.client_name, u.full_name as owner_name
                 FROM contacts co
                 LEFT JOIN clients cl ON co.client_id = cl.id
                 LEFT JOIN users u ON co.owner_id = u.id" . $contact_where . " 
                 ORDER BY co.contact_name LIMIT ? OFFSET ?";
$contact_params[] = $limit; $contact_types .= 'i';
$contact_params[] = $contact_offset; $contact_types .= 'i';
$contact_stmt = $conn->prepare($contacts_sql);
$contact_stmt->bind_param($contact_types, ...$contact_params);
$contact_stmt->execute();
$contacts_result = $contact_stmt->get_result();

// Data for modals
$users = $conn->query("SELECT id, full_name FROM users ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$all_clients_for_modal = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetch_all(MYSQLI_ASSOC);
?>
<style>
    .item-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 600; color: white; }
    .list-group-item:hover { background-color: #f8f9fa; }
    .list-group-item .text-muted { font-size: 0.85rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-building me-2"></i>Clients & Contacts</h1>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php if($active_tab == 'clients') echo 'active'; ?>" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients-pane" type="button" role="tab">Clients</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php if($active_tab == 'contacts') echo 'active'; ?>" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts-pane" type="button" role="tab">Contacts</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade <?php if($active_tab == 'clients') echo 'show active'; ?>" id="clients-pane" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form action="clients.php" method="GET" class="flex-grow-1 me-2">
                        <input type="hidden" name="tab" value="clients">
                        <div class="input-group">
                            <input type="text" name="client_search" class="form-control" placeholder="Search Clients..." value="<?php echo htmlspecialchars($client_search); ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                    <?php if (hasPermission('clients', 'create')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal"><i class="bi bi-plus-circle me-1"></i> Add Client</button>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <?php while ($row = $clients_result->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-5 d-flex align-items-center">
                                    <div class="item-avatar me-3" style="background-color: #<?php echo substr(md5($row['client_name']), 0, 6); ?>;"><?php echo strtoupper(substr($row['client_name'], 0, 2)); ?></div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['client_name']); ?></h6>
                                        <a href="<?php echo htmlspecialchars($row['website']); ?>" target="_blank" class="text-muted text-decoration-none"><?php echo htmlspecialchars($row['website']); ?></a>
                                    </div>
                                </div>
                                <div class="col-md-4"><span class="text-muted">Owner: <?php echo htmlspecialchars($row['owner_name'] ?? 'N/A'); ?></span></div>
                                <div class="col-md-3 text-md-end">
                                    <div class="btn-group">
                                        <?php if (hasPermission('clients', 'edit')): ?><a href="edit_client.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php if ($client_total_pages > 1): // Pagination for Clients ?>
                    <nav class="mt-4"><ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $client_total_pages; $i++):
                            $_GET['cpage'] = $i; $_GET['tab'] = 'clients';
                            $href = '?' . http_build_query($_GET);
                        ?>
                            <li class="page-item <?php echo ($i == $client_page) ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $href; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                    </ul></nav>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade <?php if($active_tab == 'contacts') echo 'show active'; ?>" id="contacts-pane" role="tabpanel">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                    <form action="clients.php" method="GET" class="flex-grow-1 me-2">
                        <input type="hidden" name="tab" value="contacts">
                        <div class="input-group">
                            <input type="text" name="contact_search" class="form-control" placeholder="Search Contacts..." value="<?php echo htmlspecialchars($contact_search); ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                    <?php if (hasPermission('contacts', 'create')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal"><i class="bi bi-plus-circle me-1"></i> Add Contact</button>
                    <?php endif; ?>
                </div>
                 <div class="list-group list-group-flush">
                    <?php while ($row = $contacts_result->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-md-4 d-flex align-items-center">
                                    <div class="item-avatar me-3" style="background-color: #<?php echo substr(md5($row['contact_name']), 0, 6); ?>;"><?php echo strtoupper(substr($row['contact_name'], 0, 2)); ?></div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['contact_name']); ?></h6>
                                        <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="text-muted text-decoration-none"><?php echo htmlspecialchars($row['email']); ?></a>
                                    </div>
                                </div>
                                <div class="col-md-3"><span class="text-muted"><i class="bi bi-building"></i> <?php echo htmlspecialchars($row['client_name']); ?></span></div>
                                <div class="col-md-2"><span class="text-muted"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($row['phone']); ?></span></div>
                                <div class="col-md-3 text-md-end">
                                    <div class="btn-group">
                                        <?php if (hasPermission('contacts', 'edit')): ?><a href="edit_contact.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                 <?php if ($contact_total_pages > 1): // Pagination for Contacts ?>
                    <nav class="mt-4"><ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $contact_total_pages; $i++):
                            $_GET['copage'] = $i; $_GET['tab'] = 'contacts';
                            $href = '?' . http_build_query($_GET);
                        ?>
                            <li class="page-item <?php echo ($i == $contact_page) ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $href; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                    </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (hasPermission('clients', 'create')): ?>
<div class="modal fade" id="addClientModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add New Client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form action="add_client.php" method="POST"><div class="modal-body"><div class="mb-3"><label class="form-label">Client Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="client_name" required></div><div class="mb-3"><label class="form-label">Website</label><input type="url" class="form-control" name="website" placeholder="https://example.com"></div><div class="mb-3"><label class="form-label">Owner</label><select name="owner_id" class="form-select"><option value="">-- Select Owner --</option><?php foreach ($users as $user): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="add_client" class="btn btn-primary">Save Client</button></div></form></div></div></div>
<?php endif; ?>

<?php if (hasPermission('contacts', 'create')): ?>
<div class="modal fade" id="addContactModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add New Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form action="add_contact.php" method="POST"><div class="modal-body"><div class="mb-3"><label class="form-label">Client Name <span class="text-danger">*</span></label><select name="client_id" class="form-select" required><option value="">-- Select Client --</option><?php foreach ($all_clients_for_modal as $client): ?><option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['client_name']); ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Contact Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="contact_name" required></div><div class="mb-3"><label class="form-label">Contact Email</label><input type="email" class="form-control" name="email"></div><div class="mb-3"><label class="form-label">Contact Phone</label><input type="tel" class="form-control" name="phone"></div><div class="mb-3"><label class="form-label">Owner</label><select name="owner_id" class="form-select"><option value="">-- Select Owner --</option><?php foreach ($users as $user): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="add_contact" class="btn btn-primary">Save Contact</button></div></form></div></div></div>
<?php endif; ?>

<?php include 'footer.php'; ?>