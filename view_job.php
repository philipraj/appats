<?php
include 'header.php';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
require_once 'auth.php';

// --- Permission & Data Validation ---
if (!hasPermission('job_orders', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}
if (!isset($_GET['id'])) {
    header("location: job_orders.php");
    exit();
}
$job_id = (int)$_GET['id'];

// --- Database Queries ---
// Job Details
$job_stmt = $conn->prepare("
    SELECT j.*, c.client_name, u.full_name as primary_recruiter_name
    FROM job_orders j
    LEFT JOIN clients c ON j.client_id = c.id
    LEFT JOIN users u ON j.primary_recruiter_id = u.id
    WHERE j.id = ?
");
$job_stmt->bind_param("i", $job_id);
$job_stmt->execute();
$job = $job_stmt->get_result()->fetch_assoc();
if (!$job) {
    $_SESSION['message'] = "Job Order not found.";
    $_SESSION['msg_type'] = "warning";
    header("location: job_orders.php");
    exit();
}

// Submissions / Candidates for the Pipeline
$submissions_stmt = $conn->prepare("
    SELECT s.id as submission_id, s.status, s.submitted_at,
           c.id as candidate_id, c.candidate_name, c.email, c.phone,
           u.full_name as created_by
    FROM submissions s
    JOIN candidates c ON s.candidate_id = c.id
    JOIN users u ON s.recruiter_id = u.id
    WHERE s.job_id = ? ORDER BY s.submitted_at DESC
");
$submissions_stmt->bind_param("i", $job_id);
$submissions_stmt->execute();
$all_submissions = $submissions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- TAB GROUPING SETUP ---
$tab_groups = [
    'Sourced'    => ['Sourced'],
    'Submitted'  => ['Submitted', 'Submitted to client'],
    'Interview'  => ['L1 interview', 'L2 interview', 'Customer Interview', 'Awaiting Feedback'],
    'Reject'     => ['Screen Reject', 'L1 reject', 'L2 Reject', 'Customer reject'],
    'Selects'    => ['Selected', 'Offered', 'Joined'],
    'Others'     => ['Not Interested', 'No show']
];
$candidates_by_tab = array_fill_keys(array_keys($tab_groups), []);
foreach ($all_submissions as $submission) {
    foreach ($tab_groups as $tab_name => $statuses_in_tab) {
        if (in_array($submission['status'], $statuses_in_tab)) {
            $candidates_by_tab[$tab_name][] = $submission;
            break;
        }
    }
}
$status_options = ['Sourced', 'Screen Reject', 'Submitted', 'Submitted to client', 'L1 interview', 'L1 reject', 'L2 interview', 'L2 Reject', 'Customer Interview', 'Customer reject', 'Selected', 'Offered', 'Joined', 'No show', 'Not Interested'];

// --- Job Description Cleanup Function ---
function cleanJobDescription($description) {
    if (empty($description)) {
        return 'No description available.';
    }
    
    // Remove specific problematic classes
    $patterns_to_remove = [
        '/class="[^"]*x_272127622[^"]*"/',
        '/class="[^"]*elementToProof[^"]*"/',
        '/<p[^>]*>&nbsp;<\/p>/',
        '/<div[^>]*>&nbsp;<\/div>/',
        '/<u>&nbsp;<\/u>/',
        '/<strong><\/strong>/',
        '/\&amp;amp;/', // Fix double-encoded ampersand
    ];
    
    $clean = $description;
    foreach ($patterns_to_remove as $pattern) {
        $clean = preg_replace($pattern, '', $clean);
    }
    
    // Replace bullet entities with actual bullets
    $clean = str_replace('&bull;', '•', $clean);
    $clean = str_replace('&middot;', '·', $clean);
    
    // Fix common HTML issues
    $clean = str_replace('&nbsp;', ' ', $clean);
    $clean = str_replace('</u><strong>', '</u> <strong>', $clean);
    $clean = str_replace('</u><strong>', '</u> <strong>', $clean);
    $clean = str_replace('<strong></u>', '<strong><u>', $clean);
    $clean = str_replace('</u></strong>', '</u></strong>', $clean);
    
    // Fix malformed tags
    $clean = str_replace('<u>&nbsp;</u>', ' ', $clean);
    $clean = str_replace('</key skillsets</u>', '</strong> <strong>Key skillsets:</strong>', $clean);
    
    // Remove empty paragraphs and divs
    $clean = preg_replace('/<[^>]+>\s*<\/[^>]+>/', '', $clean);
    
    // Ensure proper paragraph structure
    $clean = '<div class="clean-job-description">' . $clean . '</div>';
    
    return $clean;
}
?>

<style>
    /* Job Header Styling */
    .job-header { 
        background: #ffffff; 
        border-radius: 8px; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
        border-left: 4px solid #0d6efd;
    }
    .stat-pill { 
        display: inline-flex; 
        align-items: center; 
        background-color: #e9ecef; 
        border-radius: 20px; 
        padding: 0.25rem 0.75rem; 
        font-size: 0.85rem; 
        margin-right: 0.75rem; 
        margin-bottom: 0.75rem; 
    }
    .candidate-avatar { 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 1rem; 
        font-weight: 600; 
        color: white; 
    }
    .candidate-list-item { 
        padding: 1rem 1.5rem; 
        transition: background-color 0.2s ease;
    }
    .candidate-list-item:hover { 
        background-color: #f8f9fa; 
    }
    .status-dropdown { 
        font-weight: 500; 
        border-radius: 20px; 
        padding-left: 2rem; 
        -webkit-appearance: none; 
        -moz-appearance: none; 
        appearance: none; 
        background-repeat: no-repeat; 
        background-position: right 0.75rem center; 
        background-size: 16px 12px; 
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .status-dropdown:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .status-badge-dot { 
        width: 10px; 
        height: 10px; 
        border-radius: 50%; 
        display: inline-block; 
        position: absolute; 
        left: 0.75rem; 
        top: 50%; 
        transform: translateY(-50%); 
    }
    /* RESTORED STATUS CLASSES */
    .status-Sourced, .status-Submitted, .status-Submitted-to-client { 
        border: 1px solid #0d6efd; 
        color: #0d6efd; 
        background-color: #cfe2ff; 
    }
    .status-Sourced .status-badge-dot, .status-Submitted .status-badge-dot, .status-Submitted-to-client .status-badge-dot { 
        background-color: #0d6efd; 
    }
    .status-Joined { 
        border: 1px solid #198754; 
        color: #198754; 
        background-color: #d1e7dd; 
    }
    .status-Joined .status-badge-dot { 
        background-color: #198754; 
    }
    .status-Screen-Reject, .status-L1-reject, .status-L2-Reject, .status-Customer-reject { 
        border: 1px solid #dc3545; 
        color: #dc3545; 
        background-color: #f8d7da; 
    }
    .status-Screen-Reject .status-badge-dot, .status-L1-reject .status-badge-dot, .status-L2-Reject .status-badge-dot, .status-Customer-reject .status-badge-dot { 
        background-color: #dc3545; 
    }
    #toast-container { 
        position: fixed; 
        top: 80px; 
        right: 20px; 
        z-index: 1080; 
    }
    .toast-notification { 
        padding: 15px 20px; 
        color: white; 
        margin-bottom: 10px; 
        border-radius: 5px; 
        box-shadow: 0 0 10px rgba(0,0,0,0.2); 
        animation: slideIn 0.3s ease, fadeOut 0.3s ease 4s forwards;
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    /* Clean job description styling */
    .job-description-content {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        max-height: 500px;
        overflow-y: auto;
        padding: 20px;
        background: white;
        border-radius: 8px;
    }
    
    .clean-job-description {
        font-size: 14px;
        color: #333;
    }
    
    .clean-job-description strong {
        color: #2c3e50;
        font-weight: 600;
    }
    
    .clean-job-description u {
        text-decoration: none;
        border-bottom: 1px solid #ddd;
        padding-bottom: 1px;
    }
    
    .clean-job-description p {
        margin-bottom: 12px;
    }
    
    .clean-job-description div {
        margin-bottom: 10px;
    }
    
    .clean-job-description .zw-paragraph {
        margin-bottom: 15px;
    }
    
    .clean-job-description .zw-text-portion {
        white-space: normal !important;
        line-height: 1.5;
    }
    
    /* Pipeline tabs styling */
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        position: relative;
    }
    
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom: 3px solid #0d6efd;
        background: transparent;
    }
    
    .nav-tabs .nav-link .badge {
        font-size: 0.75em;
        padding: 0.25em 0.5em;
    }
    
    /* Candidate avatar color generator */
    .candidate-avatar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
</style>

<div class="job-header p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-1">
                <i class="bi bi-briefcase-fill text-primary me-2"></i>
                <?php echo htmlspecialchars($job['job_title']); ?>
            </h1>
            <h2 class="h5 text-muted mb-3">
                <i class="bi bi-building me-1"></i>
                <?php echo htmlspecialchars($job['client_name']); ?>
            </h2>
            <div class="d-flex flex-wrap align-items-center">
                <div class="stat-pill">
                    <i class="bi bi-geo-alt-fill text-primary"></i> 
                    <?php echo htmlspecialchars($job['job_location_city']); ?>
                </div>
                <div class="stat-pill">
                    <i class="bi bi-bar-chart-fill text-warning"></i> 
                    Priority: <span class="fw-bold"><?php echo htmlspecialchars($job['priority'] ?? 'Not Set'); ?></span>
                </div>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#jobDescriptionCollapse" role="button">
                    <i class="bi bi-card-text me-1"></i> View Description
                </button>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                <i class="bi bi-person-plus-fill me-1"></i> Add Candidate
            </button>
            <a href="job_orders.php" class="btn btn-secondary ms-2">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
        <div class="col-12">
            <div class="collapse mt-3" id="jobDescriptionCollapse">
                <div class="p-3 border rounded bg-light job-description-content">
                    <?php echo cleanJobDescription($job['job_description'] ?? ''); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <ul class="nav nav-tabs card-header-tabs border-0" id="pipelineTabs" role="tablist">
            <?php foreach($tab_groups as $tab_name => $statuses): 
                $count = count($candidates_by_tab[$tab_name]);
                $tab_id = strtolower(str_replace(' ', '-', $tab_name));
            ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php if ($tab_name === 'Sourced') echo 'active'; ?>" 
                            id="<?php echo $tab_id; ?>-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#<?php echo $tab_id; ?>-pane" 
                            type="button" 
                            role="tab">
                        <?php echo $tab_name; ?>
                        <span class="badge rounded-pill bg-<?php echo $count > 0 ? 'primary' : 'secondary'; ?> ms-1">
                            <?php echo $count; ?>
                        </span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="pipelineTabsContent">
            <?php foreach($tab_groups as $tab_name => $statuses): 
                $tab_id = strtolower(str_replace(' ', '-', $tab_name));
                $candidates = $candidates_by_tab[$tab_name];
            ?>
                <div class="tab-pane fade <?php if ($tab_name === 'Sourced') echo 'show active'; ?>" 
                     id="<?php echo $tab_id; ?>-pane" 
                     role="tabpanel">
                    <div class="list-group list-group-flush">
                        <?php if (empty($candidates)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 opacity-50"></i>
                                <h5 class="mt-2 fw-light">No candidates in this stage</h5>
                                <p class="small">Add candidates using the "Add Candidate" button above</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($candidates as $sub): 
                                $initials = strtoupper(substr($sub['candidate_name'], 0, 2));
                                $avatar_color = substr(md5($sub['candidate_name']), 0, 6);
                            ?>
                                <div class="list-group-item candidate-list-item border-start-0 border-end-0">
                                    <div class="row w-100 align-items-center">
                                        <div class="col-md-3 d-flex align-items-center">
                                            <div class="candidate-avatar me-3" 
                                                 style="background-color: #<?php echo $avatar_color; ?>;">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div>
                                                <!-- RESTORED CANDIDATE DETAILS LINK -->
                                                <h6 class="mb-0">
                                                    <a href="candidate_details.php?id=<?php echo $sub['candidate_id']; ?>" 
                                                       class="text-decoration-none text-dark fw-semibold">
                                                        <?php echo htmlspecialchars($sub['candidate_name']); ?>
                                                    </a>
                                                </h6>
                                                <div class="small">
                                                    <a href="mailto:<?php echo htmlspecialchars($sub['email']); ?>" 
                                                       class="text-muted text-decoration-none">
                                                        <?php echo htmlspecialchars($sub['email']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-muted">
                                            <i class="bi bi-person-up me-1"></i>
                                            <small><?php echo htmlspecialchars($sub['created_by']); ?></small>
                                        </div>
                                        <div class="col-md-2 text-muted">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <small><?php echo date('d M Y, h:i A', strtotime($sub['submitted_at'])); ?></small>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if (hasPermission('job_orders', 'edit')): ?>
                                                <div class="position-relative">
                                                    <span class="status-badge-dot"></span>
                                                    <select class="form-select form-select-sm status-dropdown status-<?php echo str_replace(' ', '-', $sub['status']); ?>" 
                                                            data-submission-id="<?php echo $sub['submission_id']; ?>"
                                                            title="Change candidate status">
                                                        <?php foreach($status_options as $status): 
                                                            $selected = $sub['status'] == $status ? 'selected' : '';
                                                        ?>
                                                            <option value="<?php echo $status; ?>" <?php echo $selected; ?>>
                                                                <?php echo $status; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-secondary">
                                                    <?php echo htmlspecialchars($sub['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-md-end">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-secondary view-resume-btn" 
                                                        data-candidate-id="<?php echo $sub['candidate_id']; ?>" 
                                                        title="View Resume">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </button>
                                                <button class="btn btn-outline-primary schedule-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#scheduleInterviewModal" 
                                                        data-submission-id="<?php echo $sub['submission_id']; ?>" 
                                                        title="Schedule Interview">
                                                    <i class="bi bi-calendar-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- RESTORED ADD CANDIDATE MODAL (Full original structure) -->
<div class="modal fade" id="addCandidateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add & Submit Candidate to "<?php echo htmlspecialchars($job['job_title']); ?>"</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="parsing-section mb-4 p-3 bg-light rounded border">
                    <h6 class="mb-2"><i class="bi bi-clipboard-plus me-2"></i>Auto-fill from Text</h6>
                    <div class="mb-2">
                        <label for="profile_text_block" class="form-label small">Paste candidate details from job portal:</label>
                        <textarea id="profile_text_block" class="form-control form-control-sm" rows="5"></textarea>
                    </div>
                    <button id="parse-btn" type="button" class="btn btn-sm btn-info"><i class="bi bi-magic"></i> Parse & Auto-fill Form</button>
                    <span id="parsing-status" class="ms-2 text-muted small"></span>
                </div>
                <form id="add-candidate-form" action="add_candidate_process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                    <h6>Candidate Details</h6><hr class="mt-2">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Candidate Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="candidate_name" name="candidate_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Location</label>
                            <input type="text" class="form-control" id="current_location" name="current_location">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current CTC</label>
                            <input type="text" class="form-control" id="current_ctc" name="current_ctc">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expected CTC</label>
                            <input type="text" class="form-control" id="expected_ctc" name="expected_ctc">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Notice Period</label>
                            <input type="text" class="form-control" id="notice_period" name="notice_period">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="resume" class="form-label">Upload Resume <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" name="resume" required>
                    </div>
                    <div class="modal-footer pb-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_candidate" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save & Submit to Job
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- RESTORED SCHEDULE INTERVIEW MODAL (Full original structure) -->
<div class="modal fade" id="scheduleInterviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Interview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="schedule_interview.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="modal_submission_id">
                    <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                    <input type="hidden" name="redirect_url" value="view_job.php?id=<?php echo $job_id; ?>">
                    <div class="mb-3">
                        <label class="form-label">Interview Round/Type <span class="text-danger">*</span></label>
                        <select name="interview_round" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="L1 interview">L1</option>
                            <option value="L2 interview">L2</option>
                            <option value="Customer Interview">Customer</option>
                            <option value="HR Interview">HR</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mode of Interview <span class="text-danger">*</span></label>
                        <select name="interview_mode" class="form-select" required>
                            <option value="Virtual">Virtual</option>
                            <option value="Telephonic">Telephonic</option>
                            <option value="In-person">In-person</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interview Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="interview_datetime" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interviewer(s)</label>
                        <input type="text" class="form-control" name="interviewers">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="schedule_interview" class="btn btn-primary">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RESTORED RESUME VIEWER MODAL -->
<div id="resumeViewerModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-xl" style="height: 95vh;">
        <div class="modal-content" style="height: 100%;">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCandidateName">Resume</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalResumeBody" style="padding: 0; overflow: hidden;"></div>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- STATUS DROPDOWN CHANGE HANDLER (Original logic restored) ---
    $(document).on('change', '.status-dropdown', function() {
        const submissionId = $(this).data('submission-id');
        const newStatus = $(this).val();
        if (confirm(`Are you sure you want to update status to "${newStatus}"?`)) {
            updateSubmissionStatus(submissionId, newStatus, () => location.reload());
        } else {
            // RESTORED: Reset to original value
            $(this).val($(this).find('option[selected]').val()); 
        }
    });

    // --- MODAL HANDLERS ---
    const resumeModal = new bootstrap.Modal(document.getElementById('resumeViewerModal'));
    
    // RESTORED: Schedule button handler
    $(document).on('click', '.schedule-btn', function() {
        $('#modal_submission_id').val($(this).data('submission-id'));
    });
    
    // RESTORED: View resume button handler (original logic)
    $(document).on('click', '.view-resume-btn', function(e) {
        e.preventDefault();
        const candidateId = $(this).data('candidate-id');
        const modalBody = $('#modalResumeBody');
        const modalTitle = $('#modalCandidateName');
        modalTitle.text('Loading Resume...');
        modalBody.html('<p class="text-center p-5">Loading...</p>');
        resumeModal.show();
        $.ajax({
            url: 'api_get_resume_content.php',
            type: 'GET', 
            data: { candidate_id: candidateId }, 
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    modalTitle.text(response.name + "'s Resume");
                    modalBody.html(`<iframe src="uploads/${response.filename}" style="width: 100%; height: 100%; border: none;"></iframe>`);
                } else {
                    modalBody.html(`<p class="text-center text-danger p-5">${response.message}</p>`);
                }
            },
            error: function() { 
                modalBody.html('<p class="text-center text-danger p-5">Failed to load resume content.</p>'); 
            }
        });
    });

    // --- PARSING SCRIPT FOR ADD CANDIDATE MODAL (Original logic restored) ---
    $('#parse-btn').on('click', function() {
        const textBlock = $('#profile_text_block').val();
        const statusEl = $('#parsing-status');
        if (!textBlock) {
            statusEl.text('Please paste text first.').css('color', 'red'); 
            return;
        }
        statusEl.text('Parsing...').css('color', 'blue');
        $(this).prop('disabled', true);
        $.ajax({
            url: 'api_parse_clipboard.php',
            type: 'POST', 
            data: { text_block: textBlock }, 
            dataType: 'json',
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
                } else {
                    statusEl.text(response.message || 'Could not parse text.').css('color', 'red');
                }
            },
            error: function() { 
                statusEl.text('An error occurred on the server.').css('color', 'red'); 
            },
            complete: function() { 
                $('#parse-btn').prop('disabled', false); 
            }
        });
    });

    // RESTORED: Clear parsing text area when modal is closed
    const addCandidateModal = document.getElementById('addCandidateModal');
    addCandidateModal.addEventListener('hidden.bs.modal', function () {
        $('#profile_text_block').val('');
        $('#parsing-status').text('');
        $('#add-candidate-form')[0].reset();
    });
});

// RESTORED: updateSubmissionStatus function
function updateSubmissionStatus(submissionId, newStatus, callback) {
    $.ajax({
        type: 'POST', 
        url: 'update_submission_status.php',
        data: { submission_id: submissionId, status: newStatus },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showToast('Status updated successfully!', 'success');
                if (callback) setTimeout(callback, 500);
            } else {
                showToast(response.message || 'Update failed', 'danger');
            }
        },
        error: function() { 
            showToast('An unexpected error occurred.', 'danger'); 
        }
    });
}

// RESTORED: showToast function (with improvements kept)
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast-notification bg-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 4500);
}

// RESTORED: Keyboard shortcuts (optional improvement)
document.addEventListener('keydown', function(e) {
    // Ctrl+Shift+A to open Add Candidate modal
    if (e.ctrlKey && e.shiftKey && e.key === 'A') {
        e.preventDefault();
        const modal = new bootstrap.Modal(document.getElementById('addCandidateModal'));
        modal.show();
    }
});
</script>

<?php include 'footer.php'; ?>