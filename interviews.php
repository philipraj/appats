<?php
require_once 'config.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = "You must be logged in to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: login.php");
    exit();
}

include 'header.php';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
?>

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

.interview-list {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.list-header {
    display: grid;
    grid-template-columns: 120px 1.5fr 1fr 1fr 1fr auto;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background-color: #f8f9fa;
    border-bottom: 2px solid var(--border-color);
    font-weight: 600;
    color: #495057;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.interview-item {
    display: grid;
    grid-template-columns: 120px 1.5fr 1fr 1fr 1fr auto;
    gap: 1rem;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s ease;
    background: white;
}

.interview-item:last-child {
    border-bottom: none;
}

.interview-item:hover {
    background: #f8f9ff;
}

.interview-item.completed {
    background: #f8fff9;
    opacity: 0.9;
}

.interview-time {
    text-align: center;
    padding: 0.5rem;
    background: linear-gradient(135deg, var(--primary-color), #3a56d4);
    color: white;
    border-radius: 8px;
    font-weight: 600;
}

.interview-item.completed .interview-time {
    background: linear-gradient(135deg, var(--success-color), #05c391);
}

.time-day {
    font-size: 0.75rem;
    text-transform: uppercase;
    opacity: 0.9;
}

.time-date {
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1;
    margin: 0.2rem 0;
}

.time-hour {
    font-size: 0.8rem;
    opacity: 0.9;
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

.interview-meta {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.meta-icon {
    width: 16px;
    text-align: center;
    color: var(--primary-color);
}

.meta-value {
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

.status-upcoming {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-completed {
    background-color: #d1f2eb;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-urgent {
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

.btn-schedule {
    background: var(--success-color);
    color: white;
}

.btn-schedule:hover {
    background: #05c391;
    color: white;
}

.btn-reschedule {
    background: var(--primary-color);
    color: white;
}

.btn-reschedule:hover {
    background: #3a56d4;
    color: white;
}

.btn-cancel {
    background: var(--danger-color);
    color: white;
}

.btn-cancel:hover {
    background: #d93654;
    color: white;
}

.btn-view {
    background: transparent;
    border: 1px solid var(--border-color);
    color: #6c757d;
}

.btn-view:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.btn-status {
    background: var(--warning-color);
    color: #000;
}

.btn-status:hover {
    background: #ffc107;
    color: #000;
}

/* Header and controls */
.page-header {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
}

.view-toggle {
    background: var(--light-color);
    border-radius: 8px;
    padding: 0.25rem;
    display: inline-flex;
}

.view-toggle .btn {
    border-radius: 6px;
    padding: 0.5rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 500;
}

.stats-grid {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.stat-badge {
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    font-size: 0.85rem;
    font-weight: 500;
}

.stat-number {
    font-weight: 700;
    color: var(--primary-color);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Pagination */
.pagination-modern .page-item .page-link {
    border: none;
    border-radius: 6px;
    margin: 0 0.2rem;
    font-size: 0.85rem;
    font-weight: 500;
}

.pagination-modern .page-item.active .page-link {
    background: var(--primary-color);
    color: white;
}

/* Modal enhancements */
.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color), #3a56d4);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 1.25rem;
}

.modal-header .btn-close {
    filter: invert(1);
}

.level-badge {
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.level-l1 { background: #e3f2fd; color: #1976d2; }
.level-l2 { background: #e8f5e8; color: #2e7d32; }
.level-l3 { background: #fff3e0; color: #f57c00; }
.level-hr { background: #fce4ec; color: #c2185b; }
.level-manager { background: #f3e5f5; color: #7b1fa2; }
.level-client { background: #e0f2f1; color: #00796b; }

.status-select {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    font-size: 0.8rem;
    font-weight: 500;
    background: white;
    min-width: 140px;
    cursor: pointer;
}

.status-select:focus {
    border-color: var(--primary-color);
    outline: none;
}

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
}

.fade-out {
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.5s ease-in-out;
}
</style>

<?php
// --- Filter & Pagination Logic ---
$view = $_GET['view'] ?? 'upcoming';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Define allowed interview statuses
$allowed_interview_statuses = ['L1 interview', 'L2 interview', 'Customer Interview'];

// Build SQL condition based on view and allowed statuses
if ($view == 'completed') {
    $sql_condition = "i.interview_datetime < ? AND s.status IN ('" . implode("','", $allowed_interview_statuses) . "')";
} else {
    $sql_condition = "i.interview_datetime >= ? AND s.status IN ('" . implode("','", $allowed_interview_statuses) . "')";
}

$order_by = ($view == 'completed') ? "DESC" : "ASC";
$now = date('Y-m-d H:i:s');

$params = [$now];
$types = 's';

// Get total count for pagination
$total_sql = "SELECT COUNT(*) as total FROM interviews i 
              JOIN submissions s ON i.submission_id = s.id 
              WHERE $sql_condition";
$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param($types, ...$params);
$total_stmt->execute();
$total_results = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);
$total_stmt->close();

// Get interviews for the current page with recruiter info and candidate status
$interviews_sql = "
    SELECT 
        i.id as interview_id, 
        s.id as submission_id, 
        c.id as candidate_id,
        s.status as candidate_status,
        i.interview_datetime, 
        i.interview_round, 
        i.interviewers, 
        i.location_or_link, 
        i.interview_mode,
        i.feedback,
        c.candidate_name, 
        c.email as candidate_email, 
        j.job_title, 
        j.id as job_id,
        cl.client_name,
        u.full_name as recruiter_name
    FROM interviews i
    JOIN submissions s ON i.submission_id = s.id
    JOIN candidates c ON s.candidate_id = c.id
    JOIN job_orders j ON s.job_id = j.id
    JOIN clients cl ON j.client_id = cl.id
    LEFT JOIN users u ON s.recruiter_id = u.id
    WHERE $sql_condition
    ORDER BY i.interview_datetime $order_by
    LIMIT ? OFFSET ?
";
$params[] = $limit; $types .= 'i';
$params[] = $offset; $types .= 'i';
$stmt = $conn->prepare($interviews_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$interviews_result = $stmt->get_result();
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-1 text-dark fw-bold">
                <i class="bi bi-calendar2-event me-2"></i>Scheduled Interviews
            </h1>
            <p class="text-muted mb-0">List view • <?php echo $total_results; ?> <?php echo $view; ?> interviews</p>
        </div>
        <div class="view-toggle">
            <a href="interviews.php?view=upcoming" class="btn <?php echo ($view == 'upcoming') ? 'btn-primary' : 'btn-light'; ?>">
                <i class="bi bi-clock me-1"></i>Upcoming
            </a>
            <a href="interviews.php?view=completed" class="btn <?php echo ($view == 'completed') ? 'btn-primary' : 'btn-light'; ?>">
                <i class="bi bi-check-circle me-1"></i>Completed
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-badge">
            <span class="stat-number"><?php echo $total_results; ?></span> total
        </div>
        <div class="stat-badge">
            Page <span class="stat-number"><?php echo $page; ?></span> of <?php echo $total_pages; ?>
        </div>
        <div class="stat-badge">
            <span class="stat-number"><?php echo $limit; ?></span> per page
        </div>
    </div>
</div>

<?php if ($interviews_result && $interviews_result->num_rows > 0): ?>
    <div class="interview-list">
        <div class="list-header">
            <div>Time</div>
            <div>Candidate</div>
            <div>Job & Client</div>
            <div>Interview Details</div>
            <div>Status</div>
            <div>Actions</div>
        </div>
        <?php while ($row = $interviews_result->fetch_assoc()): 
            $is_completed = $view == 'completed';
            $time_remaining = strtotime($row['interview_datetime']) - time();
            $hours_remaining = floor($time_remaining / (60 * 60));
            $is_urgent = !$is_completed && $hours_remaining < 24;
            
            // Determine level badge class
            $level_class = 'level-l1';
            if (strpos(strtolower($row['interview_round']), 'l2') !== false) $level_class = 'level-l2';
            if (strpos(strtolower($row['interview_round']), 'l3') !== false) $level_class = 'level-l3';
            if (strpos(strtolower($row['interview_round']), 'hr') !== false) $level_class = 'level-hr';
            if (strpos(strtolower($row['interview_round']), 'manager') !== false) $level_class = 'level-manager';
            if (strpos(strtolower($row['interview_round']), 'client') !== false) $level_class = 'level-client';
        ?>
            <div class="interview-item <?php echo $is_completed ? 'completed' : ''; ?>" id="interview-item-<?php echo $row['interview_id']; ?>">
                <!-- Time Column -->
                <div class="interview-time">
                    <div class="time-day"><?php echo date('D', strtotime($row['interview_datetime'])); ?></div>
                    <div class="time-date"><?php echo date('d', strtotime($row['interview_datetime'])); ?></div>
                    <div class="time-hour"><?php echo date('h:i A', strtotime($row['interview_datetime'])); ?></div>
                </div>

                <!-- Candidate Info Column -->
                <div class="candidate-main-info">
                    <div class="candidate-name"><?php echo htmlspecialchars($row['candidate_name']); ?></div>
                    <div class="candidate-contact">
                        <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($row['candidate_email']); ?>
                    </div>
                    <div class="candidate-contact">
                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($row['recruiter_name'] ?? 'Unassigned'); ?>
                    </div>
                </div>

                <!-- Job & Client Column -->
                <div class="candidate-job">
                    <div class="job-title"><?php echo htmlspecialchars($row['job_title']); ?></div>
                    <div class="client-name"><?php echo htmlspecialchars($row['client_name']); ?></div>
                </div>

                <!-- Interview Details Column -->
                <div class="interview-meta">
                    <div class="meta-item">
                        <span class="meta-icon"><i class="bi bi-person-badge"></i></span>
                        <span class="meta-value">
                            <span class="level-badge <?php echo $level_class; ?>">
                                <?php echo htmlspecialchars($row['interview_round']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-icon"><i class="bi bi-display"></i></span>
                        <span class="meta-value"><?php echo htmlspecialchars($row['interview_mode']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-icon"><i class="bi bi-people"></i></span>
                        <span class="meta-value"><?php echo htmlspecialchars($row['interviewers'] ?? 'Not set'); ?></span>
                    </div>
                </div>

                <!-- Status Column -->
                <div>
                    <?php if ($is_urgent && !$is_completed): ?>
                        <span class="status-badge status-urgent">
                            <i class="bi bi-exclamation-triangle me-1"></i>Today
                        </span>
                    <?php else: ?>
                        <select class="status-select candidate-status-select" 
                                data-submission-id="<?php echo $row['submission_id']; ?>"
                                data-current-status="<?php echo $row['candidate_status']; ?>"
                                data-interview-id="<?php echo $row['interview_id']; ?>">
                            <option value="Sourced" <?php echo $row['candidate_status'] == 'Sourced' ? 'selected' : ''; ?>>Sourced</option>
                            <option value="Screen Reject" <?php echo $row['candidate_status'] == 'Screen Reject' ? 'selected' : ''; ?>>Screen Reject</option>
                            <option value="Submitted" <?php echo $row['candidate_status'] == 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="Submitted to client" <?php echo $row['candidate_status'] == 'Submitted to client' ? 'selected' : ''; ?>>Submitted to client</option>
                            <option value="L1 interview" <?php echo $row['candidate_status'] == 'L1 interview' ? 'selected' : ''; ?>>L1 interview</option>
                            <option value="L1 reject" <?php echo $row['candidate_status'] == 'L1 reject' ? 'selected' : ''; ?>>L1 reject</option>
                            <option value="L2 interview" <?php echo $row['candidate_status'] == 'L2 interview' ? 'selected' : ''; ?>>L2 interview</option>
                            <option value="L2 Reject" <?php echo $row['candidate_status'] == 'L2 Reject' ? 'selected' : ''; ?>>L2 Reject</option>
                            <option value="Customer Interview" <?php echo $row['candidate_status'] == 'Customer Interview' ? 'selected' : ''; ?>>Customer Interview</option>
                            <option value="Customer reject" <?php echo $row['candidate_status'] == 'Customer reject' ? 'selected' : ''; ?>>Customer reject</option>
                            <option value="Awaiting Feedback" <?php echo $row['candidate_status'] == 'Awaiting Feedback' ? 'selected' : ''; ?>>Awaiting Feedback</option>
                            <option value="Selected" <?php echo $row['candidate_status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                            <option value="Offered" <?php echo $row['candidate_status'] == 'Offered' ? 'selected' : ''; ?>>Offered</option>
                            <option value="Joined" <?php echo $row['candidate_status'] == 'Joined' ? 'selected' : ''; ?>>Joined</option>
                            <option value="No show" <?php echo $row['candidate_status'] == 'No show' ? 'selected' : ''; ?>>No show</option>
                            <option value="Not Interested" <?php echo $row['candidate_status'] == 'Not Interested' ? 'selected' : ''; ?>>Not Interested</option>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Actions Column -->
                <div class="candidate-actions">
                    <?php if (hasPermission('job_orders', 'edit')): ?>
                        <?php if (!$is_completed): ?>
                            <a href="edit_interview.php?id=<?php echo $row['interview_id']; ?>" class="btn btn-action btn-reschedule">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                            <button class="btn btn-action btn-cancel cancel-interview-btn" 
                                    data-interview-id="<?php echo $row['interview_id']; ?>"
                                    data-candidate-name="<?php echo htmlspecialchars($row['candidate_name']); ?>"
                                    data-interview-round="<?php echo htmlspecialchars($row['interview_round']); ?>"
                                    data-interview-date="<?php echo date('M j, Y h:i A', strtotime($row['interview_datetime'])); ?>">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                        <?php else: ?>
                            <button class="btn btn-action btn-schedule schedule-next-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#scheduleInterviewModal" 
                                    data-submission-id="<?php echo $row['submission_id']; ?>" 
                                    data-job-id="<?php echo $row['job_id']; ?>"
                                    data-candidate-name="<?php echo htmlspecialchars($row['candidate_name']); ?>"
                                    data-current-status="<?php echo $row['candidate_status']; ?>">
                                <i class="bi bi-calendar-plus"></i> Next
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <a href="candidate_details.php?id=<?php echo $row['candidate_id']; ?>" class="btn btn-action btn-view">
                        <i class="bi bi-eye"></i>
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="bi bi-calendar2-x empty-state-icon"></i>
        <h5 class="text-muted">No <?php echo $view; ?> interviews found</h5>
        <p class="text-muted">When interviews are scheduled, they will appear here.</p>
        <?php if ($view == 'upcoming'): ?>
            <a href="submissions.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i> Schedule an Interview
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-modern justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++):
                $_GET['page'] = $i;
                $href = '?' . http_build_query($_GET);
            ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo $href; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Schedule Interview Modal -->
<div class="modal fade" id="scheduleInterviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-plus me-2"></i>
                    Schedule Next Interview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="schedule_interview.php" method="POST" id="scheduleInterviewForm">
                <div class="modal-body">
                    <input type="hidden" name="submission_id" id="modal_submission_id">
                    <input type="hidden" name="job_id" id="modal_job_id">
                    <input type="hidden" name="redirect_url" value="interviews.php">
                    
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
                            <input type="date" class="form-control" name="interview_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="interview_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interviewers</label>
                        <input type="text" class="form-control" name="interviewers" placeholder="Names separated by commas">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="schedule_interview" class="btn btn-primary">
                        <i class="bi bi-calendar-check me-2"></i>Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Interview Modal -->
<div class="modal fade" id="cancelInterviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle me-2"></i>
                    Cancel Interview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="cancel_interview.php" method="POST" id="cancelInterviewForm">
                <div class="modal-body">
                    <input type="hidden" name="interview_id" id="cancel_interview_id">
                    <input type="hidden" name="redirect_url" value="interviews.php">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Candidate</label>
                        <input type="text" class="form-control" id="cancel_candidate_name" readonly style="background: #f8f9fa;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Round</label>
                        <input type="text" class="form-control" id="cancel_interview_round" readonly style="background: #f8f9fa;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Scheduled Date</label>
                        <input type="text" class="form-control" id="cancel_interview_date" readonly style="background: #f8f9fa;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="cancellation_reason" rows="3" 
                                  placeholder="Please provide reason for cancellation..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="cancel_interview" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<script>
$(document).ready(function() {
    const currentView = '<?php echo $view; ?>'; // Get the current tab view from PHP
    const allowedInterviewStatuses = ['L1 interview', 'L2 interview', 'Customer Interview'];

    // Schedule next round button
    $('.schedule-next-btn').on('click', function() {
        const submissionId = $(this).data('submission-id');
        const jobId = $(this).data('job-id');
        const candidateName = $(this).data('candidate-name');
        const currentStatus = $(this).data('current-status');
        
        $('#modal_submission_id').val(submissionId);
        $('#modal_job_id').val(jobId);
        $('#modal_candidate_name').val(candidateName);
        
        // Auto-select next logical round based on current status
        const statusMap = {
            'L1 interview': 'L2 interview',
            'L2 interview': 'Customer Interview',
            'Customer Interview': 'Customer Interview' // Fallback
        };
        
        if (statusMap[currentStatus]) {
            $('#modal_interview_round').val(statusMap[currentStatus]);
        } else {
            // Default to L1 if no mapping found
            $('#modal_interview_round').val('L1 interview');
        }
    });

    // Cancel interview button handler
    $('.cancel-interview-btn').on('click', function() {
        const interviewId = $(this).data('interview-id');
        const candidateName = $(this).data('candidate-name');
        const interviewRound = $(this).data('interview-round');
        const interviewDate = $(this).data('interview-date');
        
        $('#cancel_interview_id').val(interviewId);
        $('#cancel_candidate_name').val(candidateName);
        $('#cancel_interview_round').val(interviewRound);
        $('#cancel_interview_date').val(interviewDate);
        
        const cancelModal = new bootstrap.Modal(document.getElementById('cancelInterviewModal'));
        cancelModal.show();
    });

    // Status change handler - Using the same submissions.status structure as view_job.php
    $('.candidate-status-select').on('change', function() {
        const $this = $(this);
        const submissionId = $this.data('submission-id');
        const newStatus = $this.val();
        const currentStatus = $this.data('current-status');
        const interviewId = $this.data('interview-id');
        
        if (newStatus !== currentStatus) {
            if (confirm('Are you sure you want to update the candidate status from "' + currentStatus + '" to "' + newStatus + '"?')) {
                // Use the same update function as view_job.php
                updateSubmissionStatus(submissionId, newStatus, function(response) {
                    showToast('Status updated successfully!', 'success');
                    // Update the current status data attribute for future changes
                    $this.data('current-status', newStatus);
                    
                    // Check if the new status is NOT an interview status
                    const isNoLongerInterviewStatus = !allowedInterviewStatuses.includes(newStatus);
                    
                    // If status changed to non-interview status OR in completed tab, remove the item
                    if (isNoLongerInterviewStatus || currentView === 'completed') {
                        const interviewItem = $('#interview-item-' + interviewId);
                        interviewItem.addClass('fade-out');
                        setTimeout(function() {
                            interviewItem.remove();
                            // Update total count display
                            updateCompletedCount();
                            
                            // If no items left, show empty state
                            if ($('.interview-item').length === 0) {
                                showEmptyState();
                            }
                        }, 500);
                    }
                }, function(error) {
                    // Error callback - reset dropdown
                    $this.val(currentStatus);
                    showToast('Failed to update status. Please try again.', 'danger');
                });
            } else {
                $this.val(currentStatus); // Reset to original value
            }
        }
    });
});

// Use the same updateSubmissionStatus function as view_job.php
function updateSubmissionStatus(submissionId, newStatus, successCallback, errorCallback) {
    $.ajax({
        type: 'POST', 
        url: 'update_submission_status.php', // Same file used by view_job.php
        data: { 
            submission_id: submissionId, 
            status: newStatus 
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                if (successCallback) successCallback(response);
            } else {
                showToast(response.message || 'Update failed', 'danger');
                if (errorCallback) errorCallback(response);
            }
        },
        error: function(xhr, status, error) { 
            showToast('An unexpected error occurred.', 'danger');
            if (errorCallback) errorCallback(error);
        }
    });
}

// Update the completed count display
function updateCompletedCount() {
    const remainingItems = $('.interview-item').length;
    $('.stat-badge:first .stat-number').text(remainingItems);
    
    // Update the page header text
    $('.page-header .text-muted').text('List view • ' + remainingItems + ' completed interviews');
}

// Show empty state when no interviews left
function showEmptyState() {
    const emptyStateHTML = `
        <div class="empty-state">
            <i class="bi bi-calendar2-x empty-state-icon"></i>
            <h5 class="text-muted">No interviews found</h5>
            <p class="text-muted">When interviews are scheduled, they will appear here.</p>
        </div>
    `;
    $('.interview-list').html(emptyStateHTML);
}

// Use the same toast function as view_job.php
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast-notification bg-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => { toast.remove(); }, 4500);
}
</script>

<?php include 'footer.php'; ?>