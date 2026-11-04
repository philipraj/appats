<?php
// candidate_details.php

// Start session and include necessary files
session_start();
require_once 'auth.php';

// 🔐 Permission check
if (!hasPermission('candidates', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// ✅ Validate candidate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Invalid candidate ID.";
    $_SESSION['msg_type'] = "danger";
    header("location: candidates.php");
    exit();
}

$candidate_id = (int)$_GET['id'];

// Database connection (adjust with your actual connection details)
$conn = new mysqli("localhost", "wwwmount_app_user", "mGraph@2021", "wwwmount_app");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 🔹 Fetch candidate details
$stmt = $conn->prepare("SELECT * FROM candidates WHERE id = ?");
if (!$stmt) {
    die("SQL Error (Candidate Query): " . $conn->error);
}
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['message'] = "Candidate not found.";
    $_SESSION['msg_type'] = "danger";
    header("location: candidates.php");
    exit();
}
$candidate = $result->fetch_assoc();
$stmt->close();

// 🔹 Fetch submissions with job order and client details
$sql_submissions = "
    SELECT 
        s.*,
        j.id as job_id,
        j.job_title,
        j.job_location_city,
        j.job_location_state,
        j.budget_min,
        j.budget_max,
        j.job_type,
        j.status as job_status,
        c.client_name,
        u.full_name as recruiter_name,
        -- Calculate submission status
        CASE 
            WHEN s.offered_ctc IS NOT NULL THEN 'Offered'
            WHEN s.joined_ctc IS NOT NULL THEN 'Joined'
            WHEN s.actual_doj IS NOT NULL THEN 'Joined'
            ELSE s.status
        END as submission_status
    FROM submissions s
    LEFT JOIN job_orders j ON s.job_id = j.id
    LEFT JOIN clients c ON j.client_id = c.id
    LEFT JOIN users u ON s.recruiter_id = u.id
    WHERE s.candidate_id = ?
    ORDER BY s.submitted_at DESC
";

$stmt_sub = $conn->prepare($sql_submissions);
if (!$stmt_sub) {
    die("SQL Error (Submissions Query): " . $conn->error);
}
$stmt_sub->bind_param("i", $candidate_id);
$stmt_sub->execute();
$result_submissions = $stmt_sub->get_result();
$submissions = [];
while ($row = $result_submissions->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt_sub->close();

// 🔹 Fetch interviews with submission and job details
$sql_interviews = "
    SELECT 
        i.*,
        s.id as submission_id,
        s.job_id,
        j.job_title,
        c.client_name,
        u.full_name as scheduled_by_name
    FROM interviews i 
    LEFT JOIN submissions s ON i.submission_id = s.id
    LEFT JOIN job_orders j ON s.job_id = j.id
    LEFT JOIN clients c ON j.client_id = c.id
    LEFT JOIN users u ON s.recruiter_id = u.id
    WHERE s.candidate_id = ? 
    ORDER BY i.interview_datetime DESC
";

$stmt_int = $conn->prepare($sql_interviews);
if (!$stmt_int) {
    die("SQL Error (Interviews Query): " . $conn->error);
}
$stmt_int->bind_param("i", $candidate_id);
$stmt_int->execute();
$result_interviews = $stmt_int->get_result();
$interviews = [];
while ($row = $result_interviews->fetch_assoc()) {
    $interviews[] = $row;
}
$stmt_int->close();

// 🔹 Fetch job orders where candidate is assigned (from job_order_user table)
$sql_job_orders = "
    SELECT 
        j.*,
        c.client_name,
        ju.created_at as assigned_date
    FROM job_order_user ju
    JOIN job_orders j ON ju.job_order_id = j.id
    JOIN clients c ON j.client_id = c.id
    WHERE ju.user_id = ?
    ORDER BY ju.created_at DESC
";

$stmt_jobs = $conn->prepare($sql_job_orders);
if ($stmt_jobs) {
    $stmt_jobs->bind_param("i", $candidate_id);
    $stmt_jobs->execute();
    $result_job_orders = $stmt_jobs->get_result();
    $job_orders = [];
    while ($row = $result_job_orders->fetch_assoc()) {
        $job_orders[] = $row;
    }
    $stmt_jobs->close();
} else {
    $job_orders = [];
}

// Calculate statistics
$offers_count = 0;
$joined_count = 0;
foreach ($submissions as $submission) {
    if (!empty($submission['offered_ctc'])) {
        $offers_count++;
    }
    if (!empty($submission['joined_ctc']) || !empty($submission['actual_doj'])) {
        $joined_count++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Details - <?= htmlspecialchars($candidate['candidate_name'] ?? 'Unknown') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .resume-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
            font-size: 0.9rem;
        }
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #667eea;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 3px solid white;
        }
        .timeline-submission { border-left-color: #007bff; }
        .timeline-submission::before { background: #007bff; }
        .timeline-interview { border-left-color: #ffc107; }
        .timeline-interview::before { background: #ffc107; }
        .timeline-offer { border-left-color: #28a745; }
        .timeline-offer::before { background: #28a745; }
        .timeline-joined { border-left-color: #20c997; }
        .timeline-joined::before { background: #20c997; }
        .timeline-joborder { border-left-color: #6f42c1; }
        .timeline-joborder::before { background: #6f42c1; }
        
        .badge-status {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            border: none;
            border-radius: 10px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .activity-log {
            max-height: 600px;
            overflow-y: auto;
        }
        .activity-icon {
            width: 40px;
            text-align: center;
        }
        .offer-details {
            background: #e8f5e8;
            border-radius: 5px;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 0;
        }
    </style>
</head>
<body>
    <!-- Simple Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-chart-line me-2"></i>MountGraph
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="candidates.php">
                    <i class="fas fa-users me-1"></i>Candidates
                </a>
                <a class="nav-link" href="jobs.php">
                    <i class="fas fa-briefcase me-1"></i>Jobs
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="candidate-photo-placeholder bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                         style="width: 150px; height: 150px; border: 5px solid rgba(255,255,255,0.3);">
                        <i class="fas fa-user fa-4x text-muted"></i>
                    </div>
                </div>
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold"><?= htmlspecialchars($candidate['candidate_name'] ?? 'Unknown Candidate') ?></h1>
                    <p class="lead mb-1">
                        <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($candidate['email'] ?? 'N/A') ?>
                    </p>
                    <p class="lead mb-1">
                        <i class="fas fa-phone me-2"></i><?= htmlspecialchars($candidate['phone'] ?? 'N/A') ?>
                    </p>
                    <p class="lead mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($candidate['current_location'] ?? 'Location not specified') ?>
                    </p>
                </div>
                <div class="col-md-2 text-end">
                    <div class="btn-group">
                        <a href="edit_candidate.php?id=<?= $candidate_id ?>" class="btn btn-light">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                        <a href="candidates.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Candidate Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary rounded-circle p-3 me-3">
                            <i class="fas fa-paper-plane fa-2x text-white"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?= count($submissions) ?></h4>
                            <p class="text-muted mb-0">Submissions</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="bg-success rounded-circle p-3 me-3">
                            <i class="fas fa-calendar-check fa-2x text-white"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?= count($interviews) ?></h4>
                            <p class="text-muted mb-0">Interviews</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="bg-warning rounded-circle p-3 me-3">
                            <i class="fas fa-file-invoice-dollar fa-2x text-white"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?= $offers_count ?></h4>
                            <p class="text-muted mb-0">Offers</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="bg-info rounded-circle p-3 me-3">
                            <i class="fas fa-user-check fa-2x text-white"></i>
                        </div>
                        <div>
                            <h4 class="mb-0"><?= $joined_count ?></h4>
                            <p class="text-muted mb-0">Joined</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Candidate Details -->
            <div class="col-lg-4">
                <!-- Personal Information -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-user-circle me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td width="40%"><strong>Email:</strong></td>
                                <td><?= htmlspecialchars($candidate['email'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td><?= htmlspecialchars($candidate['phone'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Location:</strong></td>
                                <td><?= htmlspecialchars($candidate['current_location'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Relocation:</strong></td>
                                <td>
                                    <span class="badge <?= ($candidate['can_relocate'] ?? '0') == '1' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ($candidate['can_relocate'] ?? '0') == '1' ? 'Yes' : 'No' ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Current CTC:</strong></td>
                                <td><?= htmlspecialchars($candidate['current_ctc'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Expected CTC:</strong></td>
                                <td><?= htmlspecialchars($candidate['expected_ctc'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Notice Period:</strong></td>
                                <td><?= htmlspecialchars($candidate['notice_period'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Created By:</strong></td>
                                <td>User <?= htmlspecialchars($candidate['created_by_id'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Created On:</strong></td>
                                <td><?= htmlspecialchars($candidate['created_at'] ?? 'N/A') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Resume Section -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-file-pdf me-2"></i>Resume</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($candidate['resume_filename'])): ?>
                            <div class="d-grid gap-2">
                                <a href="uploads/resumes/<?= htmlspecialchars($candidate['resume_filename']) ?>" 
                                   class="btn btn-outline-success" target="_blank">
                                    <i class="fas fa-download me-2"></i>Download Resume
                                </a>
                            </div>
                            <?php if (!empty($candidate['resume_text'])): ?>
                                <div class="mt-3">
                                    <h6>Resume Preview:</h6>
                                    <div class="resume-preview">
                                        <p class="small"><?= nl2br(htmlspecialchars(substr($candidate['resume_text'], 0, 1000))) ?>...</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-excel"></i>
                                <p>No resume uploaded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Comments -->
                <?php if (!empty($candidate['comments'])): ?>
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-comments me-2"></i>Comments & Notes</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?= nl2br(htmlspecialchars($candidate['comments'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Activity History -->
            <div class="col-lg-8">
                <!-- Complete Activity Timeline -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Complete Activity Timeline
                            <span class="badge bg-light text-dark ms-2">
                                <?= count($submissions) + count($interviews) + count($job_orders) ?> Activities
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-log">
                            <?php if (count($submissions) > 0 || count($interviews) > 0 || count($job_orders) > 0): ?>
                                <div class="timeline">
                                    <?php
                                    // Combine all activities and sort by date
                                    $all_activities = [];
                                    
                                    // Add submissions
                                    foreach ($submissions as $submission) {
                                        $all_activities[] = [
                                            'type' => 'submission',
                                            'date' => $submission['submitted_at'],
                                            'data' => $submission
                                        ];
                                    }
                                    
                                    // Add interviews
                                    foreach ($interviews as $interview) {
                                        $all_activities[] = [
                                            'type' => 'interview',
                                            'date' => $interview['interview_datetime'],
                                            'data' => $interview
                                        ];
                                    }
                                    
                                    // Add job orders
                                    foreach ($job_orders as $job) {
                                        $all_activities[] = [
                                            'type' => 'joborder',
                                            'date' => $job['assigned_date'],
                                            'data' => $job
                                        ];
                                    }
                                    
                                    // Sort by date (newest first)
                                    usort($all_activities, function($a, $b) {
                                        return strtotime($b['date']) - strtotime($a['date']);
                                    });
                                    
                                    // Display activities
                                    foreach ($all_activities as $activity):
                                        $item = $activity['data'];
                                        if ($activity['type'] === 'submission'):
                                            $timeline_class = 'timeline-submission';
                                            if (!empty($item['joined_ctc']) || !empty($item['actual_doj'])) {
                                                $timeline_class = 'timeline-joined';
                                            } elseif (!empty($item['offered_ctc'])) {
                                                $timeline_class = 'timeline-offer';
                                            }
                                    ?>
                                        <div class="timeline-item <?= $timeline_class ?>">
                                            <div class="row">
                                                <div class="col-md-1 activity-icon">
                                                    <?php if (!empty($item['joined_ctc']) || !empty($item['actual_doj'])): ?>
                                                        <i class="fas fa-user-check fa-2x text-success"></i>
                                                    <?php elseif (!empty($item['offered_ctc'])): ?>
                                                        <i class="fas fa-file-invoice-dollar fa-2x text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-paper-plane fa-2x text-primary"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <?php if (!empty($item['joined_ctc']) || !empty($item['actual_doj'])): ?>
                                                        <h6 class="mb-2 text-success">Successfully Joined</h6>
                                                    <?php elseif (!empty($item['offered_ctc'])): ?>
                                                        <h6 class="mb-2 text-success">Offer Received</h6>
                                                    <?php else: ?>
                                                        <h6 class="mb-2">Job Submission</h6>
                                                    <?php endif; ?>
                                                    
                                                    <p class="mb-1"><strong>Client:</strong> <?= htmlspecialchars($item['client_name'] ?? 'N/A') ?></p>
                                                    <p class="mb-1">
    <strong>Position:</strong> 
    <?php if (!empty($item['job_id'])): ?>
        <a href="view_job.php?id=<?= $item['job_id'] ?>" class="text-decoration-none" target="_blank">
            <?= htmlspecialchars($item['job_title'] ?? 'N/A') ?>
            <i class="fas fa-external-link-alt ms-1 small"></i>
        </a>
    <?php else: ?>
        <?= htmlspecialchars($item['job_title'] ?? 'N/A') ?>
    <?php endif; ?>
</p>
                                                    <p class="mb-1 text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        Submitted: <?= date('M j, Y', strtotime($item['submitted_at'])) ?>
                                                    </p>
                                                    
                                                    <?php if (!empty($item['recruiter_name'])): ?>
                                                        <p class="mb-1 text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            Recruiter: <?= htmlspecialchars($item['recruiter_name']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Offer Details -->
                                                    <?php if (!empty($item['offered_ctc']) || !empty($item['expected_doj'])): ?>
                                                        <div class="offer-details">
                                                            <?php if (!empty($item['offered_ctc'])): ?>
                                                                <p class="mb-1"><strong>Offered CTC:</strong> ₹<?= htmlspecialchars($item['offered_ctc']) ?> LPA</p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($item['expected_doj'])): ?>
                                                                <p class="mb-1"><strong>Expected DOJ:</strong> <?= date('M j, Y', strtotime($item['expected_doj'])) ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Joining Details -->
                                                    <?php if (!empty($item['joined_ctc']) || !empty($item['actual_doj'])): ?>
                                                        <div class="offer-details bg-success text-white">
                                                            <?php if (!empty($item['joined_ctc'])): ?>
                                                                <p class="mb-1"><strong>Joined CTC:</strong> ₹<?= htmlspecialchars($item['joined_ctc']) ?> LPA</p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($item['actual_doj'])): ?>
                                                                <p class="mb-1"><strong>Actual DOJ:</strong> <?= date('M j, Y', strtotime($item['actual_doj'])) ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <?php if (!empty($item['joined_ctc']) || !empty($item['actual_doj'])): ?>
                                                        <span class="badge bg-success badge-status">Joined</span>
                                                    <?php elseif (!empty($item['offered_ctc'])): ?>
                                                        <span class="badge bg-success badge-status">Offered</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary badge-status">Submitted</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    
                                    <?php elseif ($activity['type'] === 'interview'): ?>
                                        <div class="timeline-item timeline-interview">
                                            <div class="row">
                                                <div class="col-md-1 activity-icon">
                                                    <i class="fas fa-calendar-alt fa-2x text-warning"></i>
                                                </div>
                                                <div class="col-md-8">
                                                    <h6 class="mb-2">Interview Scheduled</h6>
                                                    <p class="mb-1"><strong>Client:</strong> <?= htmlspecialchars($item['client_name'] ?? 'N/A') ?></p>
                                                    <p class="mb-1"><strong>Position:</strong> <?= htmlspecialchars($item['job_title'] ?? 'N/A') ?></p>
                                                    <?php if (!empty($item['interview_datetime'])): ?>
                                                        <p class="mb-1 text-muted">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?= date('M j, Y g:i A', strtotime($item['interview_datetime'])) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['interview_round'])): ?>
                                                        <p class="mb-1 text-muted">
                                                            <i class="fas fa-flag me-1"></i>
                                                            Round: <?= htmlspecialchars($item['interview_round']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['interview_mode'])): ?>
                                                        <p class="mb-1 text-muted">
                                                            <i class="fas fa-video me-1"></i>
                                                            Mode: <?= htmlspecialchars($item['interview_mode']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['feedback'])): ?>
                                                        <div class="mt-2 p-2 bg-white rounded">
                                                            <strong>Feedback:</strong> 
                                                            <?= nl2br(htmlspecialchars($item['feedback'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <span class="badge bg-warning text-dark badge-status">Interview</span>
                                                </div>
                                            </div>
                                        </div>
                                    
                                    <?php elseif ($activity['type'] === 'joborder'): ?>
                                        <div class="timeline-item timeline-joborder">
                                            <div class="row">
                                                <div class="col-md-1 activity-icon">
                                                    <i class="fas fa-briefcase fa-2x text-info"></i>
                                                </div>
                                                <div class="col-md-8">
                                                    <h6 class="mb-2">Assigned to Job Order</h6>
                                                    <p class="mb-1"><strong>Client:</strong> <?= htmlspecialchars($item['client_name']) ?></p>
                                                    <p class="mb-1">
    <strong>Position:</strong> 
    <?php if (!empty($item['id'])): ?>
        <a href="view_job.php?id=<?= $item['id'] ?>" class="text-decoration-none" target="_blank">
            <?= htmlspecialchars($item['job_title']) ?>
            <i class="fas fa-external-link-alt ms-1 small"></i>
        </a>
    <?php else: ?>
        <?= htmlspecialchars($item['job_title']) ?>
    <?php endif; ?>
</p>
                                                    <p class="mb-1 text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= htmlspecialchars($item['job_location_city'] ?? 'N/A') ?>, <?= htmlspecialchars($item['job_location_state'] ?? 'N/A') ?>
                                                    </p>
                                                    <?php if (!empty($item['assigned_date'])): ?>
                                                        <p class="mb-1 text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Assigned: <?= date('M j, Y', strtotime($item['assigned_date'])) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <span class="badge bg-info badge-status">Job Order</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <p>No activity recorded yet</p>
                                    <p class="small">Submissions, interviews, and job orders will appear here</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>