<?php
include 'header.php';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
require_once 'auth.php';

if (!hasPermission('candidates', 'create')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: candidates.php");
    exit();
}

// This query is needed to populate the "Assign to Job" dropdown
$active_jobs_result = $conn->query("
    SELECT j.id, j.job_title, c.client_name 
    FROM job_orders AS j JOIN clients AS c ON j.client_id = c.id 
    WHERE j.status = 'Active' ORDER BY c.client_name, j.job_title
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-person-plus-fill me-2"></i>Add New Candidate</h1>
    <a href="candidates.php" class="btn btn-secondary"><i class="bi bi-x-lg me-1"></i>Cancel</a>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-clipboard-plus me-2"></i>Auto-fill from Text</h5></div>
    <div class="card-body">
        <div class="mb-3">
            <label for="profile_text_block" class="form-label">Paste candidate details from a job portal here:</label>
            <textarea id="profile_text_block" class="form-control" rows="6"></textarea>
        </div>
        <button id="parse-btn" type="button" class="btn btn-info"><i class="bi bi-magic"></i> Parse Text & Auto-fill Form</button>
        <span id="parsing-status" class="ms-3 text-muted"></span>
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <h4 class="mb-4">Candidate Details</h4>
        <form action="add_candidate_process.php" method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Candidate Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="candidate_name" name="candidate_name" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" id="email" name="email" required></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Phone <span class="text-danger">*</span></label><input type="tel" class="form-control" id="phone" name="phone" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Current Location</label><input type="text" class="form-control" id="current_location" name="current_location"></div>
            </div>
            <div class="row">
                 <div class="col-md-4 mb-3"><label class="form-label">Current CTC</label><input type="text" class="form-control" id="current_ctc" name="current_ctc"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Expected CTC</label><input type="text" class="form-control" id="expected_ctc" name="expected_ctc"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Notice Period</label><input type="text" class="form-control" id="notice_period" name="notice_period"></div>
            </div>
            <div class="mb-3">
                <label for="resume" class="form-label">Upload Resume <span class="text-danger">*</span></label>
                <input class="form-control" type="file" id="resume" name="resume" required accept=".pdf,.doc,.docx">
                <small class="form-text text-muted">Accepted formats: PDF, DOC, DOCX.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Comments</label>
                <textarea name="comments" class="form-control" rows="3"></textarea>
            </div>
             <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="can_relocate" value="1" id="can_relocate">
                <label class="form-check-label" for="can_relocate">Willing to Relocate</label>
            </div>
            <hr class="my-4">
            <div class="bg-light p-3 rounded border">
                <label for="job_id" class="form-label"><strong>Assign to Job (Optional)</strong></label>
                <select name="job_id" id="job_id" class="form-select">
                    <option value="">-- Do not assign to a job --</option>
                    <?php if ($active_jobs_result && $active_jobs_result->num_rows > 0): ?>
                        <?php while($job = $active_jobs_result->fetch_assoc()): ?>
                            <option value="<?php echo $job['id']; ?>"><?php echo htmlspecialchars($job['job_title'] . " (" . $job['client_name'] . ")"); ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="mt-4 text-end">
                <a href="candidates.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="add_candidate" class="btn btn-primary">Save Candidate</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#parse-btn').on('click', function() {
        const textBlock = $('#profile_text_block').val();
        const statusEl = $('#parsing-status');
        if (!textBlock) { statusEl.text('Please paste text first.').css('color', 'red'); return; }
        statusEl.text('Parsing...').css('color', 'blue');
        $(this).prop('disabled', true);
        $.ajax({
            url: 'api_parse_clipboard.php', type: 'POST', data: { text_block: textBlock }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    $('#candidate_name').val(data.name || '');
                    $('#email').val(data.email || '');
                    $('#phone').val(data.phone || '');
                    $('#current_location').val(data.currentLocation || '');
                    $('#current_ctc').val(data.currentSalary || '');
                    $('#notice_period').val(data.noticePeriod || '');
                    statusEl.text('Form auto-filled! Please review.').css('color', 'green');
                } else { statusEl.text('Could not parse text.').css('color', 'red'); }
            },
            error: function() { statusEl.text('An error on the server.').css('color', 'red'); },
            complete: function() { $('#parse-btn').prop('disabled', false); }
        });
    });
});
</script>

<?php include 'footer.php'; ?>