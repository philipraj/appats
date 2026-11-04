<?php
include 'header.php';
require_once 'auth.php'; // Require the new authentication functions

// Security Check: Ensure user has 'edit' permission for job orders
if (!hasPermission('job_orders', 'edit')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: job_orders.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("location: job_orders.php");
    exit();
}
$id = $_GET['id'];

// Fetch the job order
$job_stmt = $conn->prepare("SELECT * FROM job_orders WHERE id = ?");
$job_stmt->bind_param("i", $id);
$job_stmt->execute();
$job = $job_stmt->get_result()->fetch_assoc();
if (!$job) {
    header("location: job_orders.php");
    exit();
}

// Fetch clients, contacts, and recruiters for dropdowns
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetch_all(MYSQLI_ASSOC);
$contacts_stmt = $conn->prepare("SELECT id, contact_name FROM contacts WHERE client_id = ? ORDER BY contact_name");
$contacts_stmt->bind_param("i", $job['client_id']);
$contacts_stmt->execute();
$contacts = $contacts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recruiters = $conn->query("SELECT id, full_name FROM users WHERE role IN ('recruiter', 'team_lead', 'admin') ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Fetch the currently assigned secondary recruiters for this job
$assigned_stmt = $conn->prepare("SELECT user_id FROM job_assignments WHERE job_order_id = ?");
$assigned_stmt->bind_param("i", $id);
$assigned_stmt->execute();
$assigned_recruiters_result = $assigned_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Create a simple array of just the user IDs for easy checking
$assigned_recruiter_ids = array_column($assigned_recruiters_result, 'user_id');
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.tiny.cloud/1/cr4zf0r345rlptrxc9gj110bma41ohbjuu89d7a3ech3su3j/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

<div class="d-flex justify-content-between align-items-center mb-4"><h1>Edit Job Order</h1><a href="job_orders.php" class="btn btn-secondary">Cancel</a></div>

<div class="card">
    <div class="card-body">
        <form action="update_job_order.php" method="POST">
            <input type="hidden" name="id" value="<?php echo $job['id']; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Client Name <span class="text-danger">*</span></label>
                    <select name="client_id" id="client_id" class="form-select" required>
                        <?php foreach ($clients as $client): ?><option value="<?php echo $client['id']; ?>" <?php echo ($client['id'] == $job['client_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['client_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                    <select name="contact_id" id="contact_id" class="form-select" required>
                        <?php foreach ($contacts as $contact): ?><option value="<?php echo $contact['id']; ?>" <?php echo ($contact['id'] == $job['contact_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($contact['contact_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 mb-3"><label class="form-label">Job Title <span class="text-danger">*</span></label><input type="text" class="form-control" name="job_title" value="<?php echo htmlspecialchars($job['job_title']); ?>" required></div>
                <div class="col-md-4 mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active" <?php echo ($job['status'] == 'Active') ? 'selected' : ''; ?>>Active</option><option value="Hold" <?php echo ($job['status'] == 'Hold') ? 'selected' : ''; ?>>Hold</option><option value="Closed" <?php echo ($job['status'] == 'Closed') ? 'selected' : ''; ?>>Closed</option></select></div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Primary Recruiter</label>
                    <select name="primary_recruiter_id" class="form-select">
                        <option value="">-- Select One --</option>
                        <?php foreach ($recruiters as $recruiter): ?><option value="<?php echo $recruiter['id']; ?>" <?php echo ($recruiter['id'] == $job['primary_recruiter_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($recruiter['full_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Secondary Recruiters (hold Ctrl/Cmd to select multiple)</label>
                    <select name="secondary_recruiters[]" class="form-select" multiple size="4">
                        <?php foreach ($recruiters as $recruiter): ?><option value="<?php echo $recruiter['id']; ?>" <?php echo in_array($recruiter['id'], $assigned_recruiter_ids) ? 'selected' : ''; ?>><?php echo htmlspecialchars($recruiter['full_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Job Location (City)</label><input type="text" class="form-control" name="job_location_city" value="<?php echo htmlspecialchars($job['job_location_city']); ?>"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Job Location (State)</label><input type="text" class="form-control" name="job_location_state" value="<?php echo htmlspecialchars($job['job_location_state']); ?>"></div>
            </div>
            <div class="row">
                 <div class="col-md-6 mb-3"><label class="form-label">Job Type</label><select name="job_type" class="form-select"><option value="FTE" <?php echo ($job['job_type'] == 'FTE') ? 'selected' : ''; ?>>FTE</option><option value="Contract" <?php echo ($job['job_type'] == 'Contract') ? 'selected' : ''; ?>>Contract</option><option value="C2H" <?php echo ($job['job_type'] == 'C2H') ? 'selected' : ''; ?>>C2H</option></select></div>
                 <div class="col-md-6 mb-3"><label class="form-label">Experience Required (Years)</label><div class="input-group"><input type="number" name="exp_required_min" class="form-control" placeholder="From" value="<?php echo $job['exp_required_min']; ?>"><input type="number" name="exp_required_max" class="form-control" placeholder="To" value="<?php echo $job['exp_required_max']; ?>"></div></div>
            </div>
             <div class="mb-3"><label class="form-label">Budget (LPA)</label><div class="input-group"><input type="number" step="0.01" name="budget_min" class="form-control" placeholder="From" value="<?php echo $job['budget_min']; ?>"><input type="number" step="0.01" name="budget_max" class="form-control" placeholder="To" value="<?php echo $job['budget_max']; ?>"></div></div>
             <div class="mb-3"><label class="form-label">Job Description</label><textarea id="job_description" name="job_description" class="form-control" rows="8"><?php echo htmlspecialchars($job['job_description']); ?></textarea></div>
             <div class="mb-3"><label class="form-label">Additional Comments</label><textarea name="additional_comments" class="form-control" rows="3"><?php echo htmlspecialchars($job['additional_comments']); ?></textarea></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_non_local" value="1" id="is_non_local" <?php echo ($job['is_non_local'] == 1) ? 'checked' : ''; ?>><label class="form-check-label" for="is_non_local">Non-Local candidates are acceptable</label></div>
            
            <div class="mt-4 text-end">
                <a href="job_orders.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_job" class="btn btn-primary">Update Job Order</button>
            </div>
        </form>
    </div>
</div>
<script>
$(document).ready(function(){
    $('#client_id').on('change', function(){
        var clientId = $(this).val();
        if(clientId){ $.ajax({ type: 'POST', url: 'get_contacts.php', data: 'client_id=' + clientId, success: function(html){ $('#contact_id').html(html); } }); } else { $('#contact_id').html('<option value="">-- Select Client First --</option>'); }
    });
});
tinymce.init({ selector: '#job_description', plugins: 'lists link autolink', toolbar: 'undo redo | bold italic underline | bullist numlist | link', menubar: false, height: 300 });
</script>

<?php include 'footer.php'; ?>