<?php
require_once 'config.php';
require_once 'auth.php';
include 'header.php';

// Get counts for statistics with error handling
$stats_query = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM submissions WHERE status = 'Offered') as offered_count,
        (SELECT COUNT(*) FROM submissions WHERE status = 'Joined') as joined_count,
        (SELECT COUNT(*) FROM submissions WHERE status = 'Hired') as hired_count,
        (SELECT COUNT(*) FROM submissions WHERE status = 'Rejected') as rejected_count
");

if (!$stats_query) {
    die("Error in statistics query: " . $conn->error);
}

$stats = $stats_query->fetch_assoc();

// Get offered candidates with simplified query
$offered_query = "
    SELECT 
        s.id as submission_id, 
        s.submitted_at,
        s.updated_at as offered_date,
        s.offered_ctc, 
        s.expected_doj,
        s.status,
        c.candidate_name, 
        c.id as candidate_id,
        c.email as candidate_email,
        c.phone as candidate_phone,
        j.job_title, 
        j.id as job_id,
        cl.client_name,
        u.full_name as recruiter_name
    FROM submissions s
    JOIN candidates c ON s.candidate_id = c.id
    JOIN job_orders j ON s.job_id = j.id
    JOIN clients cl ON j.client_id = cl.id
    LEFT JOIN users u ON s.recruiter_id = u.id
    WHERE s.status = 'Offered' OR s.status = 'Hired'
    ORDER BY s.updated_at DESC
";

$offered_result = $conn->query($offered_query);
if (!$offered_result) {
    die("Error in offered candidates query: " . $conn->error);
}

// Get joined candidates with simplified query
$joined_query = "
    SELECT 
        s.id as submission_id,
        s.submitted_at,
        s.joined_ctc, 
        s.actual_doj,
        s.status,
        c.candidate_name, 
        c.id as candidate_id,
        c.email as candidate_email,
        c.phone as candidate_phone,
        j.job_title, 
        j.id as job_id,
        cl.client_name,
        u.full_name as recruiter_name
    FROM submissions s
    JOIN candidates c ON s.candidate_id = c.id
    JOIN job_orders j ON s.job_id = j.id
    JOIN clients cl ON j.client_id = cl.id
    LEFT JOIN users u ON s.recruiter_id = u.id
    WHERE s.status = 'Joined'
    ORDER BY s.actual_doj DESC
";

$joined_result = $conn->query($joined_query);
if (!$joined_result) {
    die("Error in joined candidates query: " . $conn->error);
}
?>

<style>
:root {
    --primary-color: #4361ee;
    --success-color: #06d6a0;
    --warning-color: #ffd166;
    --danger-color: #ef476f;
    --dark-color: #2b2d42;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border-left: 4px solid var(--primary-color);
    transition: transform 0.3s ease;
    text-align: center;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.offered { border-left-color: var(--warning-color); }
.stat-card.joined { border-left-color: var(--success-color); }
.stat-card.hired { border-left-color: var(--primary-color); }
.stat-card.rejected { border-left-color: var(--danger-color); }

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.candidate-list {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.candidate-item {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr auto;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e9ecef;
    align-items: center;
    transition: background-color 0.3s ease;
}

.candidate-item:hover {
    background-color: #f8f9fa;
}

.candidate-item:last-child {
    border-bottom: none;
}

.candidate-main-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.candidate-name {
    font-weight: 600;
    color: var(--dark-color);
    margin: 0;
    font-size: 1rem;
}

.candidate-contact {
    font-size: 0.85rem;
    color: #6c757d;
}

.candidate-contact i {
    width: 16px;
    text-align: center;
}

.candidate-job {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.job-title {
    font-weight: 500;
    color: var(--dark-color);
}

.client-name {
    font-size: 0.85rem;
    color: #6c757d;
}

.candidate-dates {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.85rem;
}

.date-label {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
}

.candidate-salary {
    font-weight: 500;
    color: var(--dark-color);
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-width: 100px;
}

.status-offered {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-hired {
    background-color: #cce7ff;
    color: #004085;
    border: 1px solid #b3d7ff;
}

.status-joined {
    background-color: #d1f2eb;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-rejected {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.candidate-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-action {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    border: none;
    text-decoration: none;
    white-space: nowrap;
}

.btn-update {
    background: var(--primary-color);
    color: white;
}

.btn-update:hover {
    background: #3a56d4;
    color: white;
}

.btn-status {
    background: var(--warning-color);
    color: #000;
}

.btn-status:hover {
    background: #ffc107;
    color: #000;
}

.btn-view {
    background: transparent;
    border: 1px solid #e9ecef;
    color: #6c757d;
}

.btn-view:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.nav-tabs-custom {
    background: white;
    border-radius: 12px;
    padding: 0.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.nav-tabs-custom .nav-link {
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s ease;
}

.nav-tabs-custom .nav-link.active {
    background: var(--primary-color);
    color: white;
}

.nav-tabs-custom .nav-link:hover:not(.active) {
    background: #f8f9fa;
    color: var(--primary-color);
}

.list-header {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr auto;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background-color: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    color: #495057;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h2 mb-2 text-dark fw-bold">
                <i class="bi bi-trophy-fill me-2"></i>Placement Management
            </h1>
            <p class="text-muted mb-0">Track offers and joinings in one place</p>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card offered">
            <div class="stat-number text-warning"><?php echo $stats['offered_count']; ?></div>
            <div class="stat-label">Offered</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card hired">
            <div class="stat-number text-primary"><?php echo $stats['hired_count']; ?></div>
            <div class="stat-label">Hired</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card joined">
            <div class="stat-number text-success"><?php echo $stats['joined_count']; ?></div>
            <div class="stat-label">Joined</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card rejected">
            <div class="stat-number text-danger"><?php echo $stats['rejected_count']; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs nav-tabs-custom" id="placementTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="offered-tab" data-bs-toggle="tab" data-bs-target="#offered" type="button" role="tab">
            <i class="bi bi-patch-check me-2"></i>Offered & Hired (<?php echo $stats['offered_count'] + $stats['hired_count']; ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="joined-tab" data-bs-toggle="tab" data-bs-target="#joined" type="button" role="tab">
            <i class="bi bi-person-check me-2"></i>Joined (<?php echo $stats['joined_count']; ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="placementTabsContent">
    <!-- Offered & Hired Candidates Tab -->
    <div class="tab-pane fade show active" id="offered" role="tabpanel">
        <?php if ($offered_result->num_rows > 0): ?>
            <div class="candidate-list">
                <div class="list-header">
                    <div>Candidate</div>
                    <div>Job & Client</div>
                    <div>Dates</div>
                    <div>Salary</div>
                    <div>Status</div>
                    <div>Actions</div>
                </div>
                <?php while ($row = $offered_result->fetch_assoc()): ?>
                    <div class="candidate-item">
                        <div class="candidate-main-info">
                            <div class="candidate-name"><?php echo htmlspecialchars($row['candidate_name']); ?></div>
                            <div class="candidate-contact">
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($row['candidate_email']); ?>
                            </div>
                            <div class="candidate-contact">
                                <i class="bi bi-phone"></i> <?php echo htmlspecialchars($row['candidate_phone']); ?>
                            </div>
                        </div>
                        
                        <div class="candidate-job">
                            <div class="job-title"><?php echo htmlspecialchars($row['job_title']); ?></div>
                            <div class="client-name"><?php echo htmlspecialchars($row['client_name']); ?></div>
                            <div class="text-muted small">Recruiter: <?php echo htmlspecialchars($row['recruiter_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        
                        <div class="candidate-dates">
                            <div>
                                <span class="date-label">Offered:</span>
                                <div><?php echo date('d M Y', strtotime($row['offered_date'])); ?></div>
                            </div>
                            <div>
                                <span class="date-label">Expected DOJ:</span>
                                <div><?php echo $row['expected_doj'] ? date('d M Y', strtotime($row['expected_doj'])) : 'Not set'; ?></div>
                            </div>
                        </div>
                        
                        <div class="candidate-salary">
                            <?php echo htmlspecialchars($row['offered_ctc'] ?? 'Not set'); ?>
                        </div>
                        
                        <div>
                            <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                <i class="bi bi-<?php echo $row['status'] == 'Hired' ? 'patch-check' : 'patch-exclamation'; ?>"></i>
                                <?php echo $row['status']; ?>
                            </span>
                        </div>
                        
                        <div class="candidate-actions">
                            <button class="btn btn-action btn-status" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#updateStatusModal"
                                    data-submission-id="<?php echo $row['submission_id']; ?>"
                                    data-current-status="<?php echo $row['status']; ?>"
                                    data-current-ctc="<?php echo htmlspecialchars($row['offered_ctc'] ?? ''); ?>"
                                    data-candidate-name="<?php echo htmlspecialchars($row['candidate_name']); ?>">
                                <i class="bi bi-arrow-repeat"></i> Status
                            </button>
                            
                            <button class="btn btn-action btn-update" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#updateOfferModal"
                                    data-submission-id="<?php echo $row['submission_id']; ?>"
                                    data-ctc="<?php echo htmlspecialchars($row['offered_ctc'] ?? ''); ?>"
                                    data-doj="<?php echo $row['expected_doj']; ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            
                            <a href="candidate_details.php?id=<?php echo $row['candidate_id']; ?>" class="btn btn-action btn-view">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-patch-check empty-state-icon"></i>
                <h4 class="text-muted">No offered or hired candidates found</h4>
                <p class="text-muted">When candidates receive offers or get hired, they will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Joined Candidates Tab -->
    <div class="tab-pane fade" id="joined" role="tabpanel">
        <?php if ($joined_result->num_rows > 0): ?>
            <div class="candidate-list">
                <div class="list-header">
                    <div>Candidate</div>
                    <div>Job & Client</div>
                    <div>Dates</div>
                    <div>Salary</div>
                    <div>Status</div>
                    <div>Actions</div>
                </div>
                <?php while ($row = $joined_result->fetch_assoc()): ?>
                    <div class="candidate-item">
                        <div class="candidate-main-info">
                            <div class="candidate-name"><?php echo htmlspecialchars($row['candidate_name']); ?></div>
                            <div class="candidate-contact">
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($row['candidate_email']); ?>
                            </div>
                            <div class="candidate-contact">
                                <i class="bi bi-phone"></i> <?php echo htmlspecialchars($row['candidate_phone']); ?>
                            </div>
                        </div>
                        
                        <div class="candidate-job">
                            <div class="job-title"><?php echo htmlspecialchars($row['job_title']); ?></div>
                            <div class="client-name"><?php echo htmlspecialchars($row['client_name']); ?></div>
                            <div class="text-muted small">Recruiter: <?php echo htmlspecialchars($row['recruiter_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        
                        <div class="candidate-dates">
                            <div>
                                <span class="date-label">Joined:</span>
                                <div><?php echo $row['actual_doj'] ? date('d M Y', strtotime($row['actual_doj'])) : 'Not set'; ?></div>
                            </div>
                            <div>
                                <span class="date-label">Submitted:</span>
                                <div><?php echo date('d M Y', strtotime($row['submitted_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="candidate-salary">
                            <?php echo htmlspecialchars($row['joined_ctc'] ?? 'Not set'); ?>
                        </div>
                        
                        <div>
                            <span class="status-badge status-joined">
                                <i class="bi bi-person-check"></i>
                                Joined
                            </span>
                        </div>
                        
                        <div class="candidate-actions">
                            <button class="btn btn-action btn-update" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#updateJoinModal"
                                    data-submission-id="<?php echo $row['submission_id']; ?>"
                                    data-ctc="<?php echo htmlspecialchars($row['joined_ctc'] ?? ''); ?>"
                                    data-doj="<?php echo $row['actual_doj']; ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            
                            <a href="candidate_details.php?id=<?php echo $row['candidate_id']; ?>" class="btn btn-action btn-view">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-person-check empty-state-icon"></i>
                <h4 class="text-muted">No joined candidates found</h4>
                <p class="text-muted">When candidates join, they will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Offer Modal -->
<div class="modal fade" id="updateOfferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Update Offer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_offered_details.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="offer_submission_id">
                    <div class="mb-3">
                        <label class="form-label">Offered CTC</label>
                        <input type="text" class="form-control" name="offered_ctc" id="offer_ctc_input" placeholder="e.g., 12.5 LPA">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expected DOJ</label>
                        <input type="date" class="form-control" name="expected_doj" id="offer_doj_input">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Update Candidate Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_candidate_status.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="status_submission_id">
                    <input type="hidden" name="current_status" id="current_status">
                    
                    <div class="mb-3">
                        <label class="form-label">Candidate</label>
                        <input type="text" class="form-control" id="status_candidate_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-control" name="new_status" id="new_status_select" required>
                            <option value="">Select Status</option>
                            <option value="Offered">Offered</option>
                            <option value="Hired">Hired</option>
                            <option value="Joined">Joined</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <!-- Conditional fields based on status -->
                    <div id="joined_fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Joined CTC</label>
                            <input type="text" class="form-control" name="joined_ctc" id="joined_ctc_input" placeholder="e.g., 12.5 LPA">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Actual Date of Joining</label>
                            <input type="date" class="form-control" name="actual_doj" id="actual_doj_input">
                        </div>
                    </div>
                    
                    <div id="offered_fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Offered CTC</label>
                            <input type="text" class="form-control" name="offered_ctc" id="offered_ctc_status_input" placeholder="e.g., 12.5 LPA">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expected Date of Joining</label>
                            <input type="date" class="form-control" name="expected_doj" id="expected_doj_status_input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Join Modal -->
<div class="modal fade" id="updateJoinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Update Joining Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_joined_details.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="join_submission_id">
                    <div class="mb-3">
                        <label class="form-label">Joined CTC</label>
                        <input type="text" class="form-control" name="joined_ctc" id="join_ctc_input" placeholder="e.g., 15 LPA">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Actual Joined Date</label>
                        <input type="date" class="form-control" name="actual_doj" id="join_doj_input">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update Offer Modal
const updateOfferModal = document.getElementById('updateOfferModal');
if (updateOfferModal) {
    updateOfferModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const submissionId = button.getAttribute('data-submission-id');
        const ctc = button.getAttribute('data-ctc');
        const doj = button.getAttribute('data-doj');
        
        updateOfferModal.querySelector('#offer_submission_id').value = submissionId;
        updateOfferModal.querySelector('#offer_ctc_input').value = ctc;
        updateOfferModal.querySelector('#offer_doj_input').value = doj;
    });
}

// Update Status Modal
const updateStatusModal = document.getElementById('updateStatusModal');
if (updateStatusModal) {
    updateStatusModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const submissionId = button.getAttribute('data-submission-id');
        const currentStatus = button.getAttribute('data-current-status');
        const currentCtc = button.getAttribute('data-current-ctc');
        const candidateName = button.getAttribute('data-candidate-name');
        
        updateStatusModal.querySelector('#status_submission_id').value = submissionId;
        updateStatusModal.querySelector('#current_status').value = currentStatus;
        updateStatusModal.querySelector('#status_candidate_name').value = candidateName;
        updateStatusModal.querySelector('#new_status_select').value = currentStatus;
        
        // Set current CTC in both fields
        updateStatusModal.querySelector('#joined_ctc_input').value = currentCtc;
        updateStatusModal.querySelector('#offered_ctc_status_input').value = currentCtc;
        
        // Show/hide appropriate fields based on current status
        toggleStatusFields(currentStatus);
    });
    
    // Add event listener for status change
    const statusSelect = updateStatusModal.querySelector('#new_status_select');
    statusSelect.addEventListener('change', function() {
        toggleStatusFields(this.value);
    });
}

function toggleStatusFields(status) {
    const joinedFields = document.getElementById('joined_fields');
    const offeredFields = document.getElementById('offered_fields');
    
    // Hide all fields first
    joinedFields.style.display = 'none';
    offeredFields.style.display = 'none';
    
    // Show relevant fields based on status
    if (status === 'Joined') {
        joinedFields.style.display = 'block';
    } else if (status === 'Offered' || status === 'Hired') {
        offeredFields.style.display = 'block';
    }
}

// Update Join Modal
const updateJoinModal = document.getElementById('updateJoinModal');
if (updateJoinModal) {
    updateJoinModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const submissionId = button.getAttribute('data-submission-id');
        const ctc = button.getAttribute('data-ctc');
        const doj = button.getAttribute('data-doj');
        
        updateJoinModal.querySelector('#join_submission_id').value = submissionId;
        updateJoinModal.querySelector('#join_ctc_input').value = ctc;
        updateJoinModal.querySelector('#join_doj_input').value = doj;
    });
}
</script>

<?php include 'footer.php'; ?>