<?php
require_once 'config.php';
require_once 'auth.php';

// Check permissions BEFORE including the header
if (!hasPermission('job_orders', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

include 'header.php';

// Get all active jobs for the dropdown selector
$active_jobs = $conn->query("SELECT j.id, j.job_title, c.client_name FROM job_orders j JOIN clients c ON j.client_id = c.id WHERE j.status = 'Active' ORDER BY c.client_name, j.job_title")->fetch_all(MYSQLI_ASSOC);

// More robust logic for selecting a job
$selected_job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($selected_job_id === 0 && !empty($active_jobs)) {
    $selected_job_id = $active_jobs[0]['id'];
}

$all_submissions = [];
if ($selected_job_id > 0) {
    // Fetch all submission data for the selected job
    $submissions_stmt = $conn->prepare("
        SELECT 
            s.id as submission_id, 
            s.status, 
            s.offered_ctc,
            s.expected_doj,
            s.joined_ctc,
            s.actual_doj,
            c.candidate_name, 
            c.email,
            c.id as candidate_id,
            c.phone as candidate_phone,
            u.full_name as sourced_by_recruiter,
            i.id as interview_id, 
            i.interview_datetime, 
            i.interview_round,
            i.interviewers,
            i.interview_mode,
            i.location_or_link,
            j.job_title,
            j.id as job_id,
            cl.client_name
        FROM submissions s 
        JOIN candidates c ON s.candidate_id = c.id
        JOIN users u ON s.recruiter_id = u.id
        JOIN job_orders j ON s.job_id = j.id
        JOIN clients cl ON j.client_id = cl.id
        LEFT JOIN interviews i ON s.id = i.submission_id
        WHERE s.job_id = ?
    ");
    $submissions_stmt->bind_param("i", $selected_job_id);
    $submissions_stmt->execute();
    $all_submissions = $submissions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Define pipeline stages
$pipeline_stages = [
    'Submitted to client' => [],
    'L1 interview' => [],
    'L2 interview' => [],
    'Customer Interview' => [],
    'Offered' => [],
    'Joined' => []
];

// Distribute the fetched candidates into the correct stage arrays
foreach ($all_submissions as $submission) {
    if (array_key_exists($submission['status'], $pipeline_stages)) {
        $pipeline_stages[$submission['status']][] = $submission;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment Pipeline</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<style>
:root {
    --primary-color: #4361ee;
    --success-color: #06d6a0;
    --warning-color: #ffd166;
    --danger-color: #ef476f;
    --dark-color: #2b2d42;
    --light-color: #f8f9fa;
    --border-color: #e9ecef;
}

.pipeline-board { 
    display: flex; 
    overflow-x: auto; 
    overflow-y: visible;
    padding: 20px 10px;
    min-height: 600px;
    gap: 15px;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    position: relative;
}
.pipeline-column { 
    flex: 0 0 320px; 
    background-color: #f8f9fa; 
    border-radius: 12px; 
    padding: 15px; 
    border: 1px solid var(--border-color);
    min-height: 500px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.pipeline-column h5 { 
    font-size: 1rem; 
    font-weight: bold; 
    padding-bottom: 10px; 
    border-bottom: 2px solid #dee2e6; 
    margin-bottom: 15px; 
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 2;
    padding-top: 5px;
    padding-bottom: 10px;
    margin-top: -5px;
}
.candidate-card { 
    padding: 15px; 
    background-color: #fff; 
    border: 1px solid var(--border-color); 
    border-radius: 8px; 
    margin-bottom: 12px; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    cursor: move;
    position: relative;
    z-index: 1;
}
.candidate-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.candidate-card strong { display: block; margin-bottom: 5px; color: var(--dark-color); }
.candidate-card small { color: #6c757d; display: block; margin-bottom: 8px; }
.card-actions { 
    margin-top: 12px; 
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.move-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}
.btn-move {
    padding: 4px 8px;
    font-size: 0.75rem;
    border-radius: 4px;
}
.salary-info {
    font-size: 0.8rem;
    color: #28a745;
    font-weight: 500;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.doj-info {
    font-size: 0.8rem;
    color: #6f42c1;
    font-weight: 500;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.interview-info {
    font-size: 0.8rem;
    color: #0d6efd;
    font-weight: 500;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.recruiter-info {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 3px;
}
.empty-column {
    text-align: center;
    padding: 30px 15px;
    color: #6c757d;
}
.empty-column i {
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: 0.5;
}
.column-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-top: 10px;
}

/* Drag and drop styles */
.ui-sortable-placeholder {
    border: 2px dashed #ccc !important;
    background-color: #f8f9fa !important;
    visibility: visible !important;
    height: 120px !important;
    margin-bottom: 12px !important;
    border-radius: 8px !important;
}
.candidate-card.ui-sortable-helper {
    transform: rotate(3deg);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    z-index: 9999 !important;
}
.candidate-card.dragging {
    opacity: 0.7;
}

/* Status badge colors */
.status-badge {
    padding: 0.3rem 0.6rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.status-submitted { background-color: #e3f2fd; color: #0d6efd; }
.status-l1 { background-color: #fff3cd; color: #856404; }
.status-l2 { background-color: #d1ecf1; color: #0c5460; }
.status-customer { background-color: #d4edda; color: #155724; }
.status-offered { background-color: #cce5ff; color: #004085; }
.status-joined { background-color: #d1f2eb; color: #155724; }

/* Toast styles */
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
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Scroll indicators for drag */
.scroll-indicator {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: rgba(67, 97, 238, 0.8);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.scroll-indicator.left {
    left: 10px;
}
.scroll-indicator.right {
    right: 10px;
}
.scroll-indicator.visible {
    opacity: 1;
}
</style>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-kanban-fill me-2"></i>Recruitment Pipeline</h1>
        <div>
            <a href="view_job.php?id=<?php echo $selected_job_id; ?>" class="btn btn-outline-secondary <?php echo ($selected_job_id == 0) ? 'disabled' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> View Job Details
            </a>
            <a href="job_orders.php" class="btn btn-secondary">
                 <i class="bi bi-list-ul"></i> Back to Job List
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body row align-items-center">
            <label class="col-md-2 col-form-label"><strong>Viewing Pipeline For:</strong></label>
            <div class="col-md-10">
                <select id="job-selector" class="form-select">
                    <?php if (empty($active_jobs)) : ?>
                        <option>No active jobs found</option>
                    <?php else: ?>
                        <?php foreach ($active_jobs as $job): ?>
                            <option value="<?php echo $job['id']; ?>" <?php echo ($job['id'] == $selected_job_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['job_title'] . ' (' . $job['client_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div id="status-message-container" style="display:none;" class="alert alert-success mb-3"></div>

    <!-- Scroll indicators -->
    <div id="scroll-left" class="scroll-indicator left">
        <i class="bi bi-chevron-left"></i>
    </div>
    <div id="scroll-right" class="scroll-indicator right">
        <i class="bi bi-chevron-right"></i>
    </div>

    <div class="pipeline-board" id="pipeline-board">
        <?php 
        $stage_keys = array_keys($pipeline_stages);
        foreach ($pipeline_stages as $stage_name => $candidates): 
            $stage_index = array_search($stage_name, $stage_keys);
            $column_id = 'column-' . preg_replace('/[^a-z0-9]/', '-', strtolower($stage_name));
        ?>
            <div class="pipeline-column" data-status="<?php echo htmlspecialchars($stage_name); ?>" id="<?php echo $column_id; ?>">
                <h5>
                    <span><?php echo htmlspecialchars($stage_name); ?></span>
                    <span class="badge bg-secondary rounded-pill column-count"><?php echo count($candidates); ?></span>
                </h5>
                
                <div class="card-list sortable-list" data-status="<?php echo htmlspecialchars($stage_name); ?>" data-stage-index="<?php echo $stage_index; ?>">
                    <?php if (empty($candidates)): ?>
                        <div class="empty-column">
                            <i class="bi bi-person-x"></i>
                            <p class="small mb-0">No candidates in this stage</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($candidates as $candidate): ?>
                            <div class="candidate-card sortable-item" 
                                 id="card-<?php echo $candidate['submission_id']; ?>"
                                 data-submission-id="<?php echo $candidate['submission_id']; ?>"
                                 data-candidate-name="<?php echo htmlspecialchars($candidate['candidate_name']); ?>"
                                 data-candidate-email="<?php echo htmlspecialchars($candidate['email']); ?>"
                                 data-candidate-id="<?php echo $candidate['candidate_id']; ?>"
                                 data-job-title="<?php echo htmlspecialchars($candidate['job_title']); ?>"
                                 data-client-name="<?php echo htmlspecialchars($candidate['client_name']); ?>"
                                 data-current-status="<?php echo htmlspecialchars($stage_name); ?>"
                                 data-offered-ctc="<?php echo htmlspecialchars($candidate['offered_ctc'] ?? ''); ?>"
                                 data-expected-doj="<?php echo htmlspecialchars($candidate['expected_doj'] ?? ''); ?>"
                                 data-joined-ctc="<?php echo htmlspecialchars($candidate['joined_ctc'] ?? ''); ?>"
                                 data-actual-doj="<?php echo htmlspecialchars($candidate['actual_doj'] ?? ''); ?>"
                                 data-job-id="<?php echo $candidate['job_id']; ?>">
                                <strong><?php echo htmlspecialchars($candidate['candidate_name']); ?></strong>
                                <small><?php echo htmlspecialchars($candidate['email']); ?></small>
                                
                                <?php if (!empty($candidate['sourced_by_recruiter'])): ?>
                                    <div class="recruiter-info">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($candidate['sourced_by_recruiter']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($candidate['interview_datetime'])): ?>
                                    <div class="interview-info">
                                        <i class="bi bi-calendar-check"></i> 
                                        <?php echo htmlspecialchars($candidate['interview_round']); ?>: 
                                        <?php echo date('d-M h:i A', strtotime($candidate['interview_datetime'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($stage_name == 'Offered' && !empty($candidate['offered_ctc'])): ?>
                                    <div class="salary-info">
                                        <i class="bi bi-currency-dollar"></i> Offered: <?php echo htmlspecialchars($candidate['offered_ctc']); ?>
                                    </div>
                                    <?php if (!empty($candidate['expected_doj'])): ?>
                                        <div class="doj-info">
                                            <i class="bi bi-calendar"></i> Expected DOJ: <?php echo date('d-M-Y', strtotime($candidate['expected_doj'])); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($stage_name == 'Joined' && !empty($candidate['joined_ctc'])): ?>
                                    <div class="salary-info">
                                        <i class="bi bi-currency-dollar"></i> Joined: <?php echo htmlspecialchars($candidate['joined_ctc']); ?>
                                    </div>
                                    <?php if (!empty($candidate['actual_doj'])): ?>
                                        <div class="doj-info">
                                            <i class="bi bi-calendar-check"></i> Joined: <?php echo date('d-M-Y', strtotime($candidate['actual_doj'])); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="card-actions">
                                    <div class="move-actions">
                                        <?php if ($stage_index > 0): ?>
                                            <?php $prev_stage = $stage_keys[$stage_index - 1]; ?>
                                            <button class="btn btn-sm btn-outline-secondary btn-move move-left" 
                                                    data-submission-id="<?php echo $candidate['submission_id']; ?>"
                                                    data-candidate-name="<?php echo htmlspecialchars($candidate['candidate_name']); ?>"
                                                    data-current-status="<?php echo htmlspecialchars($stage_name); ?>"
                                                    data-new-status="<?php echo htmlspecialchars($prev_stage); ?>">
                                                <i class="bi bi-arrow-left"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($stage_index < count($stage_keys) - 1): ?>
                                            <?php $next_stage = $stage_keys[$stage_index + 1]; ?>
                                            <button class="btn btn-sm btn-outline-primary btn-move move-right" 
                                                    data-submission-id="<?php echo $candidate['submission_id']; ?>"
                                                    data-candidate-name="<?php echo htmlspecialchars($candidate['candidate_name']); ?>"
                                                    data-current-status="<?php echo htmlspecialchars($stage_name); ?>"
                                                    data-new-status="<?php echo htmlspecialchars($next_stage); ?>"
                                                    data-candidate-email="<?php echo htmlspecialchars($candidate['email']); ?>"
                                                    data-job-id="<?php echo $candidate['job_id']; ?>"
                                                    data-job-title="<?php echo htmlspecialchars($candidate['job_title']); ?>"
                                                    data-client-name="<?php echo htmlspecialchars($candidate['client_name']); ?>"
                                                    data-offered-ctc="<?php echo htmlspecialchars($candidate['offered_ctc'] ?? ''); ?>"
                                                    data-expected-doj="<?php echo htmlspecialchars($candidate['expected_doj'] ?? ''); ?>"
                                                    data-joined-ctc="<?php echo htmlspecialchars($candidate['joined_ctc'] ?? ''); ?>"
                                                    data-actual-doj="<?php echo htmlspecialchars($candidate['actual_doj'] ?? ''); ?>">
                                                <i class="bi bi-arrow-right"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="column-actions">
                                        <?php if (hasPermission('job_orders', 'edit')): ?>
                                            <?php if (in_array($stage_name, ['L1 interview', 'L2 interview', 'Customer Interview']) && !empty($candidate['interview_id'])): ?>
                                                <a href="edit_interview.php?id=<?php echo $candidate['interview_id']; ?>" class="btn btn-sm btn-info btn-move">
                                                    <i class="bi bi-pencil-square"></i> Edit Interview
                                                </a>
                                            <?php elseif ($stage_name == 'Offered'): ?>
                                                <button class="btn btn-sm btn-warning btn-move edit-offer" 
                                                        data-submission-id="<?php echo $candidate['submission_id']; ?>"
                                                        data-ctc="<?php echo htmlspecialchars($candidate['offered_ctc'] ?? ''); ?>"
                                                        data-doj="<?php echo htmlspecialchars($candidate['expected_doj'] ?? ''); ?>">
                                                    <i class="bi bi-pencil"></i> Edit Offer
                                                </button>
                                            <?php elseif ($stage_name == 'Joined'): ?>
                                                <button class="btn btn-sm btn-warning btn-move edit-joined" 
                                                        data-submission-id="<?php echo $candidate['submission_id']; ?>"
                                                        data-ctc="<?php echo htmlspecialchars($candidate['joined_ctc'] ?? ''); ?>"
                                                        data-doj="<?php echo htmlspecialchars($candidate['actual_doj'] ?? ''); ?>">
                                                    <i class="bi bi-pencil"></i> Edit Joined
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="candidate_details.php?id=<?php echo $candidate['candidate_id']; ?>" class="btn btn-sm btn-outline-secondary btn-move">
                                            <i class="bi bi-eye"></i> View
                                        </a>
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

<!-- Schedule Interview Modal -->
<div class="modal fade" id="scheduleInterviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus me-2"></i>
                    Schedule Interview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleInterviewForm" method="POST">
                <input type="hidden" name="schedule_interview" value="1">
                <input type="hidden" name="submission_id" id="modal_submission_id">
                <input type="hidden" name="job_id" id="modal_job_id">
                <input type="hidden" name="redirect_url" value="pipeline.php">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Candidate</label>
                        <input type="text" class="form-control" id="modal_candidate_name" readonly style="background: #f8f9fa;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Round <span class="text-danger">*</span></label>
                        <select name="interview_round" class="form-select" required id="modal_interview_round">
                            <option value="">-- Select Round --</option>
                            <option value="L1 interview">L1 interview</option>
                            <option value="L2 interview">L2 interview</option>
                            <option value="Customer Interview">Customer Interview</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="interview_date" required min="<?php echo date('Y-m-d'); ?>" id="modal_interview_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="interview_time" required id="modal_interview_time">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interviewers</label>
                        <input type="text" class="form-control" name="interviewers" placeholder="Names separated by commas">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Mode <span class="text-danger">*</span></label>
                        <select name="interview_mode" class="form-select" required>
                            <option value="Virtual">Virtual</option>
                            <option value="In-person">In-person</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location/Link</label>
                        <input type="text" class="form-control" name="location_or_link" placeholder="Zoom link or office address">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-calendar-check me-2"></i>Schedule Interview
                    </button>
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
            <form id="updateStatusForm">
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
                            <option value="No show">No show</option>
                            <option value="Not Interested">Not Interested</option>
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

<!-- Update Offer Modal -->
<div class="modal fade" id="updateOfferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Update Offer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="updateOfferForm">
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

<!-- Update Joined Modal -->
<div class="modal fade" id="updateJoinedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Update Joining Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="updateJoinedForm">
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="joined_submission_id">
                    <div class="mb-3">
                        <label class="form-label">Joined CTC</label>
                        <input type="text" class="form-control" name="joined_ctc" id="joined_ctc_edit_input" placeholder="e.g., 15 LPA">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Actual Joined Date</label>
                        <input type="date" class="form-control" name="actual_doj" id="joined_doj_input">
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

<!-- Toast Container -->
<div id="toast-container"></div>

<script>
$(document).ready(function() {
    // Job selector change handler
    $('#job-selector').on('change', function() {
        const selectedJobId = $(this).val();
        if(selectedJobId) {
            window.location.href = 'pipeline.php?job_id=' + selectedJobId;
        }
    });

    // Define interview stages that require scheduling
    const interviewStages = ['L1 interview', 'L2 interview', 'Customer Interview'];
    const offerStage = 'Offered';
    const joinedStage = 'Joined';
    
    // Store drag information
    let dragInfo = {
        originalColumn: null,
        originalCard: null,
        originalStatus: null,
        isDragging: false,
        startX: 0,
        startY: 0
    };

    // Auto-scroll variables
    let scrollInterval = null;
    const pipelineBoard = $('#pipeline-board');
    const scrollSpeed = 25;
    const scrollThreshold = 80;

    // Function to start auto-scrolling
    function startAutoScroll(direction) {
        if (scrollInterval) clearInterval(scrollInterval);
        
        scrollInterval = setInterval(function() {
            const currentScroll = pipelineBoard.scrollLeft();
            const maxScroll = pipelineBoard[0].scrollWidth - pipelineBoard.width();
            
            if (direction === 'right' && currentScroll < maxScroll - 10) {
                pipelineBoard.scrollLeft(currentScroll + scrollSpeed);
                showScrollIndicator('right');
            } else if (direction === 'left' && currentScroll > 10) {
                pipelineBoard.scrollLeft(currentScroll - scrollSpeed);
                showScrollIndicator('left');
            } else {
                hideScrollIndicators();
            }
        }, 16);
    }

    // Function to stop auto-scrolling
    function stopAutoScroll() {
        if (scrollInterval) {
            clearInterval(scrollInterval);
            scrollInterval = null;
        }
        hideScrollIndicators();
    }

    // Function to show scroll indicator
    function showScrollIndicator(direction) {
        if (direction === 'left') {
            $('#scroll-left').addClass('visible');
            $('#scroll-right').removeClass('visible');
        } else {
            $('#scroll-right').addClass('visible');
            $('#scroll-left').removeClass('visible');
        }
    }

    // Function to hide scroll indicators
    function hideScrollIndicators() {
        $('#scroll-left, #scroll-right').removeClass('visible');
    }

    // Mouse move handler for auto-scroll
    function handleMouseMove(e) {
        if (!dragInfo.isDragging) return;
        
        const boardRect = pipelineBoard[0].getBoundingClientRect();
        const mouseX = e.clientX || (e.touches && e.touches[0].clientX);
        
        if (!mouseX) return;
        
        if (mouseX > boardRect.right - scrollThreshold) {
            startAutoScroll('right');
        } else if (mouseX < boardRect.left + scrollThreshold) {
            startAutoScroll('left');
        } else {
            stopAutoScroll();
        }
    }

    // Initialize sortable with enhanced drag-and-drop
    $(".sortable-list").sortable({
        connectWith: ".sortable-list",
        placeholder: "ui-sortable-placeholder",
        cursor: "move",
        tolerance: "pointer",
        revert: 150,
        scroll: false,
        helper: function(e, item) {
            const helper = item.clone();
            helper.css({
                'width': item.outerWidth(),
                'opacity': 0.9,
                'pointer-events': 'none',
                'z-index': 10000,
                'position': 'fixed',
                'left': e.pageX - 50,
                'top': e.pageY - 20
            });
            helper.addClass('dragging-helper');
            $('body').append(helper);
            return helper;
        },
        appendTo: 'body',
        start: function(event, ui) {
            dragInfo.originalColumn = $(ui.item).parent();
            dragInfo.originalCard = ui.item;
            dragInfo.originalStatus = dragInfo.originalColumn.data('status');
            dragInfo.isDragging = true;
            dragInfo.startX = event.pageX;
            dragInfo.startY = event.pageY;
            
            $(ui.item).addClass('dragging');
            
            if (ui.helper) {
                ui.helper.css({
                    'position': 'fixed',
                    'left': event.pageX - 50,
                    'top': event.pageY - 20
                });
            }
            
            $(document).on('mousemove.dragscroll touchmove.dragscroll', handleMouseMove);
            
            pipelineBoard.css('overflow-x', 'auto');
        },
        sort: function(event, ui) {
            if (ui.helper) {
                ui.helper.css({
                    'left': event.pageX - 50,
                    'top': event.pageY - 20
                });
            }
        },
        stop: function(event, ui) {
            $(ui.item).removeClass('dragging');
            
            const newColumn = $(ui.item).parent();
            const newStatus = newColumn.data('status');
            const currentStatus = dragInfo.originalStatus;
            
            $(document).off('mousemove.dragscroll touchmove.dragscroll');
            stopAutoScroll();
            
            $('.dragging-helper').remove();
            
            if (newColumn[0] !== dragInfo.originalColumn[0] && newStatus !== currentStatus) {
                handleStatusChange(ui.item, currentStatus, newStatus);
            } else {
                if (dragInfo.originalColumn[0] === newColumn[0]) {
                    updateColumnCounts();
                } else {
                    ui.item.detach().appendTo(dragInfo.originalColumn);
                    updateColumnCounts();
                }
            }
            
            dragInfo.isDragging = false;
        }
    }).disableSelection();

    // Handle status change from drag and drop
    function handleStatusChange(cardElement, currentStatus, newStatus) {
        const submissionId = cardElement.data('submission-id');
        const candidateName = cardElement.data('candidate-name');
        const candidateEmail = cardElement.data('candidate-email');
        const jobId = cardElement.data('job-id');
        const jobTitle = cardElement.data('job-title');
        const clientName = cardElement.data('client-name');
        const offeredCtc = cardElement.data('offered-ctc');
        const expectedDoj = cardElement.data('expected-doj');
        const joinedCtc = cardElement.data('joined-ctc');
        const actualDoj = cardElement.data('actual-doj');
        
        if (interviewStages.includes(newStatus)) {
            showInterviewModal(submissionId, jobId, candidateName, newStatus, cardElement);
        } else if (newStatus === offerStage) {
            showUpdateStatusModal(submissionId, candidateName, 'Offered', cardElement, {
                offered_ctc: offeredCtc,
                expected_doj: expectedDoj
            });
        } else if (newStatus === joinedStage) {
            showUpdateStatusModal(submissionId, candidateName, 'Joined', cardElement, {
                joined_ctc: joinedCtc,
                actual_doj: actualDoj
            });
        } else {
            if (confirm(`Move ${candidateName} from ${currentStatus} to ${newStatus}?`)) {
                updateSubmissionStatus(submissionId, newStatus, candidateName, cardElement);
            } else {
                cardElement.detach().appendTo(dragInfo.originalColumn);
                updateColumnCounts();
            }
        }
    }

    // Move candidate to previous stage (left arrow)
    $('.move-left').on('click', function() {
        const submissionId = $(this).data('submission-id');
        const candidateName = $(this).data('candidate-name');
        const currentStatus = $(this).data('current-status');
        const newStatus = $(this).data('new-status');
        const cardElement = $(this).closest('.candidate-card');
        
        if (confirm(`Move ${candidateName} from ${currentStatus} to ${newStatus}?`)) {
            updateSubmissionStatus(submissionId, newStatus, candidateName, cardElement);
        }
    });

    // Move candidate to next stage (right arrow)
    $('.move-right').on('click', function() {
        const $button = $(this);
        const submissionId = $button.data('submission-id');
        const candidateName = $button.data('candidate-name');
        const candidateEmail = $button.data('candidate-email');
        const currentStatus = $button.data('current-status');
        const newStatus = $button.data('new-status');
        const jobId = $button.data('job-id');
        const jobTitle = $button.data('job-title');
        const clientName = $button.data('client-name');
        const cardElement = $button.closest('.candidate-card');
        
        if (interviewStages.includes(newStatus)) {
            showInterviewModal(submissionId, jobId, candidateName, newStatus, cardElement);
        } else if (newStatus === offerStage) {
            showUpdateStatusModal(submissionId, candidateName, 'Offered', cardElement, {
                offered_ctc: $button.data('offered-ctc'),
                expected_doj: $button.data('expected-doj')
            });
        } else if (newStatus === joinedStage) {
            showUpdateStatusModal(submissionId, candidateName, 'Joined', cardElement, {
                joined_ctc: $button.data('joined-ctc'),
                actual_doj: $button.data('actual-doj')
            });
        } else {
            if (confirm(`Move ${candidateName} from ${currentStatus} to ${newStatus}?`)) {
                updateSubmissionStatus(submissionId, newStatus, candidateName, cardElement);
            }
        }
    });

    // Edit offer button
    $('.edit-offer').on('click', function() {
        const submissionId = $(this).data('submission-id');
        const ctc = $(this).data('ctc');
        const doj = $(this).data('doj');
        
        $('#offer_submission_id').val(submissionId);
        $('#offer_ctc_input').val(ctc);
        $('#offer_doj_input').val(doj);
        
        const modal = new bootstrap.Modal(document.getElementById('updateOfferModal'));
        modal.show();
    });

    // Edit joined button
    $('.edit-joined').on('click', function() {
        const submissionId = $(this).data('submission-id');
        const ctc = $(this).data('ctc');
        const doj = $(this).data('doj');
        
        $('#joined_submission_id').val(submissionId);
        $('#joined_ctc_edit_input').val(ctc);
        $('#joined_doj_input').val(doj);
        
        const modal = new bootstrap.Modal(document.getElementById('updateJoinedModal'));
        modal.show();
    });

    // Function to show interview modal
    function showInterviewModal(submissionId, jobId, candidateName, interviewRound, cardElement) {
        $('#modal_submission_id').val(submissionId);
        $('#modal_job_id').val(jobId);
        $('#modal_candidate_name').val(candidateName);
        $('#modal_interview_round').val(interviewRound);
        
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        $('#modal_interview_date').val(tomorrow.toISOString().split('T')[0]);
        
        $('#modal_interview_time').val('10:00');
        
        $('#scheduleInterviewModal').data('card-element', cardElement);
        $('#scheduleInterviewModal').data('new-status', interviewRound);
        $('#scheduleInterviewModal').data('submission-id', submissionId);
        $('#scheduleInterviewModal').data('candidate-name', candidateName);
        
        $('#scheduleInterviewModal .alert').remove();
        
        const modal = new bootstrap.Modal(document.getElementById('scheduleInterviewModal'));
        modal.show();
        
        $('#scheduleInterviewModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
            if (dragInfo.isDragging && dragInfo.originalCard) {
                dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                updateColumnCounts();
            }
        });
    }

    // Function to show update status modal
    function showUpdateStatusModal(submissionId, candidateName, newStatus, cardElement, currentData = {}) {
        $('#status_submission_id').val(submissionId);
        $('#current_status').val(newStatus);
        $('#status_candidate_name').val(candidateName);
        $('#new_status_select').val(newStatus);
        
        if (newStatus === 'Joined') {
            $('#joined_ctc_input').val(currentData.joined_ctc || '');
            $('#actual_doj_input').val(currentData.actual_doj || '');
        } else if (newStatus === 'Offered' || newStatus === 'Hired') {
            $('#offered_ctc_status_input').val(currentData.offered_ctc || '');
            $('#expected_doj_status_input').val(currentData.expected_doj || '');
        }
        
        toggleStatusFields(newStatus);
        
        $('#updateStatusModal').data('card-element', cardElement);
        $('#updateStatusModal').data('new-status', newStatus);
        $('#updateStatusModal').data('submission-id', submissionId);
        $('#updateStatusModal').data('candidate-name', candidateName);
        
        const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
        modal.show();
        
        $('#updateStatusModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
            if (dragInfo.isDragging && dragInfo.originalCard) {
                dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                updateColumnCounts();
            }
        });
    }

    // Function to toggle status fields
    function toggleStatusFields(status) {
        $('#joined_fields, #offered_fields').hide();
        if (status === 'Joined') {
            $('#joined_fields').show();
        } else if (status === 'Offered' || status === 'Hired') {
            $('#offered_fields').show();
        }
    }

    // Handle interview form submission for pipeline
    $('#scheduleInterviewForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const cardElement = $('#scheduleInterviewModal').data('card-element');
        const newStatus = $('#scheduleInterviewModal').data('new-status');
        const submissionId = $('#scheduleInterviewModal').data('submission-id');
        const candidateName = $('#scheduleInterviewModal').data('candidate-name');
        
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => {
            if (data[key]) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        });
        
        data.schedule_interview = '1';
        
        const interviewDate = $('#modal_interview_date').val();
        const interviewTime = $('#modal_interview_time').val();
        if (interviewDate && interviewTime) {
            data.interview_datetime = interviewDate + ' ' + interviewTime + ':00';
        }
        
        showToast(`Scheduling interview for ${candidateName}...`, 'info');
        
        $.ajax({
            type: 'POST',
            url: 'schedule_interview.php',
            data: data,
            dataType: 'text',
            success: function(response) {
                if (response.includes('successfully') || response.includes('success')) {
                    bootstrap.Modal.getInstance(document.getElementById('scheduleInterviewModal')).hide();
                    
                    showToast(`Interview scheduled successfully for ${candidateName}!`, 'success');
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    let errorMsg = 'Failed to schedule interview.';
                    if (response.includes('error') || response.includes('Error')) {
                        const tempDiv = $('<div>').html(response);
                        const alertDiv = tempDiv.find('.alert-danger');
                        if (alertDiv.length) {
                            errorMsg = alertDiv.text().trim();
                        }
                    }
                    showToast(errorMsg, 'danger');
                    
                    if (dragInfo.originalCard) {
                        dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                        updateColumnCounts();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showToast('Network error. Please try again. Error: ' + error, 'danger');
                
                if (dragInfo.originalCard) {
                    dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                    updateColumnCounts();
                }
            }
        });
    });

    // Handle status update form submission
    $('#updateStatusForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const cardElement = $('#updateStatusModal').data('card-element');
        const newStatus = $('#updateStatusModal').data('new-status');
        const submissionId = $('#updateStatusModal').data('submission-id');
        const candidateName = $('#updateStatusModal').data('candidate-name');
        
        const formData = {};
        $(this).serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });
        
        const endpoint = (newStatus === 'Offered' || newStatus === 'Hired' || newStatus === 'Joined') 
            ? 'update_candidate_status.php' 
            : 'update_submission_status.php';
        
        $.ajax({
            type: 'POST',
            url: endpoint,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
                    
                    const additionalData = {};
                    if (newStatus === 'Offered' || newStatus === 'Hired') {
                        additionalData.offered_ctc = formData.offered_ctc || '';
                        additionalData.expected_doj = formData.expected_doj || '';
                    } else if (newStatus === 'Joined') {
                        additionalData.joined_ctc = formData.joined_ctc || '';
                        additionalData.actual_doj = formData.actual_doj || '';
                    }
                    
                    updateSubmissionStatus(submissionId, newStatus, candidateName, cardElement, additionalData);
                } else {
                    showToast('Error: ' + response.message, 'danger');
                    if (dragInfo.originalCard) {
                        dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                        updateColumnCounts();
                    }
                }
            },
            error: function() {
                showToast('Network error. Please try again.', 'danger');
                if (dragInfo.originalCard) {
                    dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                    updateColumnCounts();
                }
            }
        });
    });

    // Function to update submission status
    function updateSubmissionStatus(submissionId, newStatus, candidateName, cardElement, additionalData = {}) {
        showToast(`Updating status for ${candidateName} to "${newStatus}"...`, 'info');
        
        const data = {
            submission_id: submissionId,
            status: newStatus,
            ...additionalData
        };
        
        $.ajax({
            type: 'POST',
            url: 'update_submission_status.php',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    showToast(`Status updated successfully for ${candidateName}!`, 'success');
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Error: ' + response.message, 'danger');
                    if (dragInfo.originalCard) {
                        dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                        updateColumnCounts();
                    }
                }
            },
            error: function() {
                showToast('Network error. Please try again.', 'danger');
                if (dragInfo.originalCard) {
                    dragInfo.originalCard.detach().appendTo(dragInfo.originalColumn);
                    updateColumnCounts();
                }
            }
        });
    }

    // Function to update column counts
    function updateColumnCounts() {
        $('.pipeline-column').each(function() {
            const column = $(this);
            const count = column.find('.candidate-card').length;
            column.find('.column-count').text(count);
        });
    }

    // Event listener for status select change
    $('#new_status_select').on('change', function() {
        toggleStatusFields($(this).val());
    });

    // Function to show toast notifications
    function showToast(message, type = 'success') {
        const container = $('#toast-container');
        const toast = $(`
            <div class="toast-notification bg-${type}">
                ${message}
            </div>
        `);
        
        container.append(toast);
        
        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Add keyboard navigation for arrow buttons
    $(document).on('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }
        
        const focusedCard = $('.candidate-card:focus');
        if (focusedCard.length === 0) return;
        
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            focusedCard.find('.move-left').click();
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            focusedCard.find('.move-right').click();
        }
    });

    // Make candidate cards focusable
    $('.candidate-card').attr('tabindex', '0').on('focus', function() {
        $(this).css('box-shadow', '0 0 0 3px rgba(67, 97, 238, 0.3)');
    }).on('blur', function() {
        $(this).css('box-shadow', '');
    });
});
</script>

</body>
</html>
<?php include 'footer.php'; ?>