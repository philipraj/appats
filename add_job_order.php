<?php
include 'header.php';
// This line ensures jQuery is loaded before any other scripts try to use it.
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
require_once 'auth.php';

if (!hasPermission('job_orders', 'create')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: job_orders.php");
    exit();
}
// Fetch data for dropdowns
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetch_all(MYSQLI_ASSOC);
$recruiters = $conn->query("SELECT id, full_name FROM users WHERE role IN ('recruiter', 'team_lead', 'admin') ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.tiny.cloud/1/cr4zf0r345rlptrxc9gj110bma41ohbjuu89d7a3ech3su3j/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Add New Job Order</h1>
    <a href="job_orders.php" class="btn btn-secondary">
        <i class="bi bi-x-lg me-1"></i>Cancel
    </a>
</div>

<form id="addJobForm" action="add_job.php" method="POST">
    <div class="card">
        <div class="card-body">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Client Name <span class="text-danger">*</span></label>
                    <select name="client_id" id="client_id" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['client_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                    <select name="contact_id" id="contact_id" required placeholder="-- Select Client First --"></select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Job Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="job_title" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" selected>Active</option>
                        <option value="Hold">Hold</option>
                    </select>
                </div>
            </div>

            <div class="row">
                 <div class="col-md-6 mb-3">
                    <label class="form-label">Primary Recruiter</label>
                    <select id="primary_recruiter" name="primary_recruiter_id"></select>
                </div>
                 <div class="col-md-6 mb-3">
                    <label class="form-label">Secondary Recruiters</label>
                    <select id="secondary_recruiters" name="secondary_recruiters[]" multiple></select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Job Location (City)</label>
                    <input type="text" class="form-control" name="job_location_city" placeholder="e.g., Chennai">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Job Location (State)</label>
                    <input type="text" class="form-control" name="job_location_state" placeholder="e.g., Tamil Nadu">
                </div>
            </div>
             <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Job Type</label>
                    <select name="job_type" class="form-select">
                        <option value="FTE">FTE</option>
                        <option value="Contract">Contract</option>
                        <option value="C2H">C2H</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Experience Required (Years)</label>
                    <div class="input-group">
                        <input type="number" name="exp_required_min" class="form-control" placeholder="Min">
                        <input type="number" name="exp_required_max" class="form-control" placeholder="Max">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Budget Range (LPA)</label>
                <div class="input-group">
                    <input type="number" step="0.01" name="budget_min" class="form-control" placeholder="Min">
                    <input type="number" step="0.01" name="budget_max" class="form-control" placeholder="Max">
                </div>
            </div>
             <div class="mb-3">
                <label class="form-label">Job Description</label>
                <textarea id="job_description" name="job_description"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Additional Comments</label>
                <textarea name="additional_comments" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_non_local" value="1" id="is_non_local">
                <label class="form-check-label" for="is_non_local">Non-Local candidates are acceptable</label>
            </div>

        </div>
        <div class="card-footer text-end">
            <a href="job_orders.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" name="add_job" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Job Order</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tomSelectSettings = { create: false, sortField: { field: "text", direction: "asc" } };
    const recruiterOptions = <?php echo json_encode(array_map(function($r) { return ['value' => $r['id'], 'text' => htmlspecialchars($r['full_name'])]; }, $recruiters)); ?>;

    const clientSelect = new TomSelect("#client_id", tomSelectSettings);
    const contactSelect = new TomSelect("#contact_id", tomSelectSettings);
    const primaryRecruiterSelect = new TomSelect("#primary_recruiter", { ...tomSelectSettings, placeholder: 'Select a primary recruiter...', options: recruiterOptions });
    const secondaryRecruiterSelect = new TomSelect("#secondary_recruiters", { ...tomSelectSettings, plugins: ['remove_button'], placeholder: 'Add team members...', options: recruiterOptions });

    tinymce.init({ selector: '#job_description', plugins: 'lists link wordcount', toolbar: 'undo redo | blocks | bold italic | bullist numlist | link', menubar: false, height: 350 });

    clientSelect.on('change', function(clientId) {
        contactSelect.clear();
        contactSelect.clearOptions();
        contactSelect.disable();
        contactSelect.settings.placeholder = clientId ? 'Loading contacts...' : '-- Select Client First --';
        contactSelect.inputState(); // Re-render the placeholder

        if (clientId) {
            $.ajax({
                url: 'api_get_contacts.php',
                type: 'GET',
                data: { client_id: clientId },
                dataType: 'json',
                success: function(data) {
                    contactSelect.enable();
                    contactSelect.settings.placeholder = (data && data.length > 0) ? 'Select a contact...' : 'No contacts found';
                    contactSelect.inputState();
                    if (data && data.length > 0) {
                        contactSelect.addOptions(data);
                    }
                },
                error: function() {
                    contactSelect.enable();
                    contactSelect.settings.placeholder = 'Error loading contacts';
                    contactSelect.inputState();
                }
            });
        } else {
            contactSelect.enable();
        }
    });
});
</script>

<?php include 'footer.php'; ?>