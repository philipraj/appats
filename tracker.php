<?php
// Start output buffering at the VERY FIRST LINE
ob_start();

include 'header.php';
require_once 'auth.php';

// Permission check
if (!hasPermission('job_orders', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// Excel Export functionality - MUST BE PROCESSED BEFORE ANY OUTPUT
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    if (!hasPermission('job_orders', 'export')) {
        $_SESSION['message'] = "You do not have permission to export data.";
        $_SESSION['msg_type'] = "danger";
        header("location: tracker.php");
        exit();
    }
    
    // Status configuration for export (same as main page)
    $pipeline_statuses = ['Submitted to client', 'L1 interview', 'L2 interview', 'Customer Interview', 'Selected'];
    $rejection_statuses = ['Screen Reject', 'L1 reject', 'L2 Reject', 'Customer reject', 'No show'];
    $all_tracked_statuses = array_merge($pipeline_statuses, $rejection_statuses);
    $status_placeholders = implode(',', array_fill(0, count($all_tracked_statuses), '?'));
    
    // Build export query - ADDED: Exclude submissions from closed job orders
    $export_query = "
        SELECT 
            s.id as submission_id,
            s.status,
            s.submitted_at,
            c.id as candidate_id,
            c.candidate_name,
            c.email,
            c.phone,
            c.current_location,
            c.current_ctc,
            c.expected_ctc,
            c.notice_period,
            j.id as job_id,
            j.job_title,
            j.client_id,
            j.contact_id,
            j.status as job_status,  -- Added job status
            cl.client_name,
            con.contact_name as client_poc,
            con.email as client_poc_email,
            con.phone as client_poc_phone,
            u.full_name as recruiter_name,
            s.updated_at as last_status_change,
            c.resume_filename
        FROM submissions s
        JOIN candidates c ON s.candidate_id = c.id
        JOIN job_orders j ON s.job_id = j.id
        LEFT JOIN clients cl ON j.client_id = cl.id
        LEFT JOIN contacts con ON j.contact_id = con.id
        LEFT JOIN users u ON s.recruiter_id = u.id
        WHERE s.status IN ($status_placeholders)
        AND j.status != 'Closed'  -- ADDED: Exclude submissions from closed jobs
    ";
    
    // Apply same filters as the main page
    $where_conditions = [];
    $params = $all_tracked_statuses;
    $types = str_repeat('s', count($all_tracked_statuses));
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where_conditions[] = "s.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    if (isset($_GET['job_id']) && !empty($_GET['job_id'])) {
        $where_conditions[] = "s.job_id = ?";
        $params[] = (int)$_GET['job_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
        $where_conditions[] = "j.client_id = ?";
        $params[] = (int)$_GET['client_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['client_poc']) && !empty($_GET['client_poc'])) {
        $where_conditions[] = "con.contact_name LIKE ?";
        $params[] = "%" . $_GET['client_poc'] . "%";
        $types .= 's';
    }
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = "%{$_GET['search']}%";
        $where_conditions[] = "(c.candidate_name LIKE ? OR c.email LIKE ? OR j.job_title LIKE ? OR cl.client_name LIKE ? OR con.contact_name LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'sssss';
    }
    
    if (!empty($where_conditions)) {
        $export_query .= " AND " . implode(" AND ", $where_conditions);
    }
    
    $export_query .= " ORDER BY s.updated_at DESC, s.submitted_at DESC";
    
    try {
        $stmt = $conn->prepare($export_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $export_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Clear any previous output and generate Excel file
        ob_clean(); // Clear the output buffer
        
        // Set headers for Excel download
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="pipeline_tracker_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // Headers
        $headers = [
            'Submission ID',
            'Candidate Name',
            'Email',
            'Phone',
            'Current Location',
            'Current CTC',
            'Expected CTC',
            'Notice Period',
            'Job Title',
            'Job Status',
            'Client Name',
            'Client POC',
            'Client POC Email',
            'Client POC Phone',
            'Status',
            'Recruiter',
            'Submitted Date',
            'Last Updated'
        ];
        
        fputcsv($output, $headers, "\t");
        
        // Data rows
        foreach ($export_data as $row) {
            $excel_row = [
                $row['submission_id'],
                $row['candidate_name'],
                $row['email'],
                $row['phone'],
                $row['current_location'],
                $row['current_ctc'],
                $row['expected_ctc'],
                $row['notice_period'],
                $row['job_title'],
                $row['job_status'],
                $row['client_name'],
                $row['client_poc'],
                $row['client_poc_email'],
                $row['client_poc_phone'],
                $row['status'],
                $row['recruiter_name'],
                date('d M Y', strtotime($row['submitted_at'])),
                date('d M Y', strtotime($row['last_status_change']))
            ];
            
            fputcsv($output, $excel_row, "\t");
        }
        
        fclose($output);
        exit(); // Stop script execution after export
        
    } catch (Exception $e) {
        // If export fails, clear buffer and show error
        ob_clean();
        error_log("Export error in tracker.php: " . $e->getMessage());
        $_SESSION['message'] = "Export failed: " . $e->getMessage();
        $_SESSION['msg_type'] = "danger";
        // Continue with normal page display
    }
}

// Rest of your existing code for the main page display...
// Status configuration
$pipeline_statuses = ['Submitted to client', 'L1 interview', 'L2 interview', 'Customer Interview', 'Selected'];
$rejection_statuses = ['Screen Reject', 'L1 reject', 'L2 Reject', 'Customer reject', 'No show'];

// Combine for filtering
$all_tracked_statuses = array_merge($pipeline_statuses, $rejection_statuses);
$status_placeholders = implode(',', array_fill(0, count($all_tracked_statuses), '?'));

// Check if user wants to show closed jobs
$show_closed_jobs = isset($_GET['show_closed']) && $_GET['show_closed'] == '1';

// Build base query - Get the specific contact assigned to the job order
$base_query = "
    SELECT 
        s.id as submission_id,
        s.status,
        s.submitted_at,
        c.id as candidate_id,
        c.candidate_name,
        c.email,
        c.phone,
        c.current_location,
        c.current_ctc,
        c.expected_ctc,
        c.notice_period,
        j.id as job_id,
        j.job_title,
        j.client_id,
        j.contact_id,
        j.status as job_status,
        cl.client_name,
        con.contact_name as client_poc,
        con.email as client_poc_email,
        con.phone as client_poc_phone,
        u.full_name as recruiter_name,
        s.updated_at as last_status_change,
        c.resume_filename
    FROM submissions s
    JOIN candidates c ON s.candidate_id = c.id
    JOIN job_orders j ON s.job_id = j.id
    LEFT JOIN clients cl ON j.client_id = cl.id
    LEFT JOIN contacts con ON j.contact_id = con.id
    LEFT JOIN users u ON s.recruiter_id = u.id
    WHERE s.status IN ($status_placeholders)
";

// Conditionally filter out closed jobs if not showing them
if (!$show_closed_jobs) {
    $base_query .= " AND j.status != 'Closed'";
}

// Handle filters
$where_conditions = [];
$params = $all_tracked_statuses;
$types = str_repeat('s', count($all_tracked_statuses));

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "s.status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

if (isset($_GET['job_id']) && !empty($_GET['job_id'])) {
    $where_conditions[] = "s.job_id = ?";
    $params[] = (int)$_GET['job_id'];
    $types .= 'i';
}

if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
    $where_conditions[] = "j.client_id = ?";
    $params[] = (int)$_GET['client_id'];
    $types .= 'i';
}

if (isset($_GET['client_poc']) && !empty($_GET['client_poc'])) {
    $where_conditions[] = "con.contact_name LIKE ?";
    $params[] = "%" . $_GET['client_poc'] . "%";
    $types .= 's';
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%{$_GET['search']}%";
    $where_conditions[] = "(c.candidate_name LIKE ? OR c.email LIKE ? OR j.job_title LIKE ? OR cl.client_name LIKE ? OR con.contact_name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sssss';
}

if (!empty($where_conditions)) {
    $base_query .= " AND " . implode(" AND ", $where_conditions);
}

$base_query .= " ORDER BY s.updated_at DESC, s.submitted_at DESC";

// Execute query with error handling
$all_submissions = [];
try {
    $stmt = $conn->prepare($base_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $all_submissions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database error in tracker.php: " . $e->getMessage());
    $_SESSION['message'] = "Database error: " . $e->getMessage();
    $_SESSION['msg_type'] = "danger";
    $all_submissions = [];
}

// Get filter options - Filter out closed jobs from dropdowns when not showing closed jobs
$jobs_query = "SELECT id, job_title FROM job_orders";
if (!$show_closed_jobs) {
    $jobs_query .= " WHERE status != 'Closed'";
}
$jobs_query .= " ORDER BY job_title";
$jobs_result = $conn->query($jobs_query);

$clients_result = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name");
$client_pocs_result = $conn->query("SELECT DISTINCT contact_name FROM contacts WHERE contact_name IS NOT NULL AND contact_name != '' ORDER BY contact_name");

// Group submissions
$pipeline_submissions = array_filter($all_submissions, function($sub) use ($pipeline_statuses) {
    return in_array($sub['status'], $pipeline_statuses);
});

$rejection_submissions = array_filter($all_submissions, function($sub) use ($rejection_statuses) {
    return in_array($sub['status'], $rejection_statuses);
});

$status_options = ['Screen Reject', 'Submitted', 'Submitted to client', 'L1 interview', 'L1 reject', 'L2 interview', 'L2 Reject', 'Customer Interview', 'Customer reject', 'Selected', 'Offered', 'Joined', 'No show'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .pipeline-table { font-size: 0.85rem; }
        .pipeline-table th { background-color: #f8f9fa; position: sticky; top: 0; white-space: nowrap; }
        .status-dropdown { font-size: 0.8rem; padding: 0.25rem 0.5rem; border-radius: 15px; border: 1px solid #dee2e6; width: 180px; }
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,.025); }
        .filter-section { background: #f8f9fa; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; }
        .nav-tabs .nav-link.active { font-weight: 600; }
        .candidate-name { font-weight: 600; color: #2c3e50; }
        .candidate-details { font-size: 0.8rem; color: #6c757d; line-height: 1.3; }
        .contact-info { line-height: 1.3; }
        .job-info { line-height: 1.3; }
        .client-poc { font-size: 0.75rem; color: #e74c3c; font-weight: 500; margin-top: 2px; }
        .actions-column { white-space: nowrap; }
        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .toast-notification { padding: 12px 20px; margin-bottom: 10px; border-radius: 5px; color: white; }
        .export-btn { background-color: #28a745; border-color: #28a745; }
        .export-btn:hover { background-color: #218838; border-color: #1e7e34; }
        .closed-job-badge { font-size: 0.7em; vertical-align: middle; }
        .job-closed-warning { background-color: #fff3cd; border-left: 4px solid #ffc107; }
        .table-row-closed { background-color: #f8f9fa; opacity: 0.7; }
        .closed-job-indicator { 
            font-size: 0.75rem; 
            background-color: #6c757d; 
            color: white; 
            padding: 1px 6px; 
            border-radius: 3px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3">Pipeline Tracking</h1>
                <p class="text-muted mb-0">
                    <?php if (!$show_closed_jobs): ?>
                        Showing candidates from active job orders only. 
                        <a href="tracker.php?<?php echo http_build_query(array_merge($_GET, ['show_closed' => '1'])); ?>" class="text-primary">
                            <i class="bi bi-eye"></i> Show closed job candidates
                        </a>
                    <?php else: ?>
                        <span class="text-warning">
                            <i class="bi bi-exclamation-triangle"></i> Showing ALL candidates including closed jobs
                        </span>
                        <a href="tracker.php?<?php 
                            $params = $_GET;
                            unset($params['show_closed']);
                            echo http_build_query($params);
                        ?>" class="text-primary ms-2">
                            <i class="bi bi-eye-slash"></i> Hide closed job candidates
                        </a>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <span class="badge bg-primary">Pipeline: <?php echo count($pipeline_submissions); ?></span>
                <span class="badge bg-danger ms-2">Rejections: <?php echo count($rejection_submissions); ?></span>
            </div>
        </div>

        <!-- Display error message if any -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
        <?php endif; ?>

        <!-- Warning message when showing closed jobs -->
        <?php if ($show_closed_jobs): ?>
            <div class="alert alert-warning alert-dismissible fade show job-closed-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Note:</strong> You are viewing candidates from both active and closed job orders. 
                Candidates from closed jobs are typically hidden from the pipeline tracker.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <?php if ($show_closed_jobs): ?>
                    <input type="hidden" name="show_closed" value="1">
                <?php endif; ?>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <optgroup label="Pipeline">
                            <?php foreach($pipeline_statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == $status) ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Rejections">
                            <?php foreach($rejection_statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] == $status) ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Job</label>
                    <select name="job_id" class="form-select">
                        <option value="">All Jobs</option>
                        <?php 
                        // Reset the jobs result pointer
                        if ($jobs_result) {
                            $jobs_result->data_seek(0);
                            while($job = $jobs_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $job['id']; ?>" <?php echo (isset($_GET['job_id']) && $_GET['job_id'] == $job['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['job_title']); ?>
                            </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                        <?php if ($show_closed_jobs): ?>
                            <?php
                            // Query for closed jobs separately
                            $closed_jobs_query = "SELECT id, job_title FROM job_orders WHERE status = 'Closed' ORDER BY job_title";
                            $closed_jobs_result = $conn->query($closed_jobs_query);
                            if ($closed_jobs_result && $closed_jobs_result->num_rows > 0):
                            ?>
                            <optgroup label="Closed Jobs">
                                <?php while($closed_job = $closed_jobs_result->fetch_assoc()): ?>
                                    <option value="<?php echo $closed_job['id']; ?>" <?php echo (isset($_GET['job_id']) && $_GET['job_id'] == $closed_job['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($closed_job['job_title']); ?> (Closed)
                                    </option>
                                <?php endwhile; ?>
                            </optgroup>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">All Clients</option>
                        <?php 
                        // Reset the clients result pointer
                        if ($clients_result) {
                            $clients_result->data_seek(0);
                            while($client = $clients_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo (isset($_GET['client_id']) && $_GET['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['client_name']); ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Client POC</label>
                    <select name="client_poc" class="form-select">
                        <option value="">All POCs</option>
                        <?php 
                        // Reset the client pocs result pointer
                        if ($client_pocs_result) {
                            $client_pocs_result->data_seek(0);
                            while($poc = $client_pocs_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $poc['contact_name']; ?>" <?php echo (isset($_GET['client_poc']) && $_GET['client_poc'] == $poc['contact_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($poc['contact_name']); ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Candidate, Email, Job, Client..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <?php if ($show_closed_jobs): ?>
                            <a href="tracker.php?show_closed=1" class="btn btn-secondary">Clear</a>
                        <?php else: ?>
                            <a href="tracker.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                        <?php if (hasPermission('job_orders', 'export') && !empty($all_submissions)): ?>
                            <a href="tracker.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" 
                               class="btn btn-success export-btn">
                                <i class="bi bi-download"></i> Excel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Export Info -->
        <?php if (!empty($all_submissions) && hasPermission('job_orders', 'export')): ?>
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-info-circle"></i>
                    Export includes all filtered data (<?php echo count($all_submissions); ?> records)
                    <?php if (!$show_closed_jobs): ?>
                        <span class="text-muted">(excluding closed jobs)</span>
                    <?php else: ?>
                        <span class="text-warning">(including closed jobs)</span>
                    <?php endif; ?>
                </div>
                <small>Click "Excel" button to download</small>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="pipelineTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pipeline-tab" data-bs-toggle="tab" data-bs-target="#pipeline-pane" type="button" role="tab">
                            <i class="bi bi-diagram-3 me-1"></i>Pipeline
                            <span class="badge bg-primary ms-1"><?php echo count($pipeline_submissions); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rejections-tab" data-bs-toggle="tab" data-bs-target="#rejections-pane" type="button" role="tab">
                            <i class="bi bi-x-circle me-1"></i>Rejections
                            <span class="badge bg-danger ms-1"><?php echo count($rejection_submissions); ?></span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="pipelineTabsContent">
                    
                    <!-- Pipeline Tab -->
                    <div class="tab-pane fade show active" id="pipeline-pane" role="tabpanel">
                        <?php if (empty($pipeline_submissions)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">
                                    <?php if ($show_closed_jobs): ?>
                                        No candidates in pipeline (including closed jobs)
                                    <?php else: ?>
                                        No candidates in pipeline from active job orders
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover pipeline-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Candidate</th>
                                            <th>Contact</th>
                                            <th>Job & Client</th>
                                            <th>Current Status</th>
                                            <th>Recruiter</th>
                                            <th>Submitted</th>
                                            <th>Last Updated</th>
                                            <th class="actions-column">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pipeline_submissions as $sub): 
                                            $is_closed_job = ($sub['job_status'] ?? '') === 'Closed';
                                        ?>
                                            <tr <?php if ($is_closed_job) echo 'class="table-row-closed"'; ?>>
                                                <td>
                                                    <div class="candidate-name">
                                                        <?php echo htmlspecialchars($sub['candidate_name']); ?>
                                                        <?php if ($is_closed_job): ?>
                                                            <span class="closed-job-indicator" title="Job is closed">Closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="candidate-details">
                                                        <?php echo htmlspecialchars($sub['current_location']); ?> | 
                                                        CTC: <?php echo htmlspecialchars($sub['current_ctc']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="contact-info"><?php echo htmlspecialchars($sub['email']); ?></div>
                                                    <div class="candidate-details"><?php echo htmlspecialchars($sub['phone']); ?></div>
                                                </td>
                                                <td>
                                                    <div class="job-info">
                                                        <strong><?php echo htmlspecialchars($sub['job_title']); ?></strong>
                                                        <?php if ($is_closed_job): ?>
                                                            <span class="badge bg-secondary closed-job-badge ms-1">Job Closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="candidate-details">
                                                        <?php echo htmlspecialchars($sub['client_name']); ?>
                                                        <?php if (!empty($sub['client_poc'])): ?>
                                                            <div class="client-poc">
                                                                <strong>POC:</strong> <?php echo htmlspecialchars($sub['client_poc']); ?>
                                                                <?php if (!empty($sub['client_poc_email'])): ?>
                                                                    | <?php echo htmlspecialchars($sub['client_poc_email']); ?>
                                                                <?php endif; ?>
                                                                <?php if (!empty($sub['client_poc_phone'])): ?>
                                                                    | <?php echo htmlspecialchars($sub['client_poc_phone']); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (hasPermission('job_orders', 'edit') && !$is_closed_job): ?>
                                                        <select class="form-select status-dropdown" 
                                                                data-submission-id="<?php echo $sub['submission_id']; ?>"
                                                                data-old-status="<?php echo htmlspecialchars($sub['status']); ?>">
                                                            <?php foreach($status_options as $status): ?>
                                                                <option value="<?php echo $status; ?>" <?php if($sub['status'] == $status) echo 'selected'; ?>>
                                                                    <?php echo $status; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <?php if ($is_closed_job): ?>
                                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($sub['status']); ?></span>
                                                            <small class="text-muted d-block">(Job closed)</small>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?php echo htmlspecialchars($sub['status']); ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($sub['recruiter_name']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($sub['submitted_at'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($sub['last_status_change'])); ?></td>
                                                <td class="actions-column">
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if (!empty($sub['resume_filename'])): ?>
                                                            <button class="btn btn-outline-secondary view-resume-btn" 
                                                                    data-candidate-id="<?php echo $sub['candidate_id']; ?>"
                                                                    data-resume-filename="<?php echo htmlspecialchars($sub['resume_filename']); ?>"
                                                                    data-candidate-name="<?php echo htmlspecialchars($sub['candidate_name']); ?>"
                                                                    title="View Resume">
                                                                <i class="bi bi-file-earmark-text"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary" title="No Resume Available" disabled>
                                                                <i class="bi bi-file-earmark-x"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (!$is_closed_job): ?>
                                                            <button class="btn btn-outline-primary schedule-btn" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#scheduleInterviewModal" 
                                                                    data-submission-id="<?php echo $sub['submission_id']; ?>"
                                                                    data-candidate-name="<?php echo htmlspecialchars($sub['candidate_name']); ?>"
                                                                    data-job-title="<?php echo htmlspecialchars($sub['job_title']); ?>"
                                                                    data-job-id="<?php echo $sub['job_id']; ?>"
                                                                    title="Schedule Interview">
                                                                <i class="bi bi-calendar-plus"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary" disabled title="Cannot schedule - Job is closed">
                                                                <i class="bi bi-calendar-plus"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <a href="view_job.php?id=<?php echo $sub['job_id']; ?>" 
                                                           class="btn btn-outline-info" title="View Job">
                                                            <i class="bi bi-briefcase"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rejections Tab -->
                    <div class="tab-pane fade" id="rejections-pane" role="tabpanel">
                        <?php if (empty($rejection_submissions)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">
                                    <?php if ($show_closed_jobs): ?>
                                        No rejected candidates (including closed jobs)
                                    <?php else: ?>
                                        No rejected candidates from active job orders
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover pipeline-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Candidate</th>
                                            <th>Contact</th>
                                            <th>Job & Client</th>
                                            <th>Rejection Status</th>
                                            <th>Recruiter</th>
                                            <th>Submitted</th>
                                            <th>Last Updated</th>
                                            <th class="actions-column">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($rejection_submissions as $sub): 
                                            $is_closed_job = ($sub['job_status'] ?? '') === 'Closed';
                                        ?>
                                            <tr <?php if ($is_closed_job) echo 'class="table-row-closed"'; ?>>
                                                <td>
                                                    <div class="candidate-name">
                                                        <?php echo htmlspecialchars($sub['candidate_name']); ?>
                                                        <?php if ($is_closed_job): ?>
                                                            <span class="closed-job-indicator" title="Job is closed">Closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="candidate-details">
                                                        <?php echo htmlspecialchars($sub['current_location']); ?> | 
                                                        CTC: <?php echo htmlspecialchars($sub['current_ctc']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="contact-info"><?php echo htmlspecialchars($sub['email']); ?></div>
                                                    <div class="candidate-details"><?php echo htmlspecialchars($sub['phone']); ?></div>
                                                </td>
                                                <td>
                                                    <div class="job-info">
                                                        <strong><?php echo htmlspecialchars($sub['job_title']); ?></strong>
                                                        <?php if ($is_closed_job): ?>
                                                            <span class="badge bg-secondary closed-job-badge ms-1">Job Closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="candidate-details">
                                                        <?php echo htmlspecialchars($sub['client_name']); ?>
                                                        <?php if (!empty($sub['client_poc'])): ?>
                                                            <div class="client-poc">
                                                                <strong>POC:</strong> <?php echo htmlspecialchars($sub['client_poc']); ?>
                                                                <?php if (!empty($sub['client_poc_email'])): ?>
                                                                    | <?php echo htmlspecialchars($sub['client_poc_email']); ?>
                                                                <?php endif; ?>
                                                                <?php if (!empty($sub['client_poc_phone'])): ?>
                                                                    | <?php echo htmlspecialchars($sub['client_poc_phone']); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (hasPermission('job_orders', 'edit') && !$is_closed_job): ?>
                                                        <select class="form-select status-dropdown" 
                                                                data-submission-id="<?php echo $sub['submission_id']; ?>"
                                                                data-old-status="<?php echo htmlspecialchars($sub['status']); ?>">
                                                            <?php foreach($status_options as $status): ?>
                                                                <option value="<?php echo $status; ?>" <?php if($sub['status'] == $status) echo 'selected'; ?>>
                                                                    <?php echo $status; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <?php if ($is_closed_job): ?>
                                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($sub['status']); ?></span>
                                                            <small class="text-muted d-block">(Job closed)</small>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger"><?php echo htmlspecialchars($sub['status']); ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($sub['recruiter_name']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($sub['submitted_at'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($sub['last_status_change'])); ?></td>
                                                <td class="actions-column">
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if (!empty($sub['resume_filename'])): ?>
                                                            <button class="btn btn-outline-secondary view-resume-btn" 
                                                                    data-candidate-id="<?php echo $sub['candidate_id']; ?>"
                                                                    data-resume-filename="<?php echo htmlspecialchars($sub['resume_filename']); ?>"
                                                                    data-candidate-name="<?php echo htmlspecialchars($sub['candidate_name']); ?>"
                                                                    title="View Resume">
                                                                <i class="bi bi-file-earmark-text"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary" title="No Resume Available" disabled>
                                                                <i class="bi bi-file-earmark-x"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (!$is_closed_job): ?>
                                                            <button class="btn btn-outline-primary schedule-btn" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#scheduleInterviewModal" 
                                                                    data-submission-id="<?php echo $sub['submission_id']; ?>"
                                                                    data-candidate-name="<?php echo htmlspecialchars($sub['candidate_name']); ?>"
                                                                    data-job-title="<?php echo htmlspecialchars($sub['job_title']); ?>"
                                                                    data-job-id="<?php echo $sub['job_id']; ?>"
                                                                    title="Schedule Interview">
                                                                <i class="bi bi-calendar-plus"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary" disabled title="Cannot schedule - Job is closed">
                                                                <i class="bi bi-calendar-plus"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <a href="view_job.php?id=<?php echo $sub['job_id']; ?>" 
                                                           class="btn btn-outline-info" title="View Job">
                                                            <i class="bi bi-briefcase"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Interview Modal -->
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
                        <input type="hidden" name="job_id" id="modal_job_id">
                        <input type="hidden" name="redirect_url" value="tracker.php">
                        
                        <div class="mb-3">
                            <label class="form-label">Candidate</label>
                            <input type="text" class="form-control" id="modal_candidate_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Job</label>
                            <input type="text" class="form-control" id="modal_job_title" readonly>
                        </div>
                        
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
                            <input type="text" class="form-control" name="interviewers" placeholder="Enter interviewer names">
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

    <!-- Resume Viewer Modal -->
    <div class="modal fade" id="resumeViewerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCandidateName">Resume</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalResumeBody" style="min-height: 500px;">
                    <div class="text-center p-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading resume...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>

    <script>
    $(document).ready(function() {
        // Status dropdown change handler
        $(document).on('change', '.status-dropdown', function() {
            const submissionId = $(this).data('submission-id');
            const newStatus = $(this).val();
            const oldStatus = $(this).data('old-status');
            
            // Check if this is a closed job submission
            const isClosedJob = $(this).closest('tr').find('.closed-job-indicator').length > 0;
            
            if (isClosedJob) {
                alert('Cannot update status for candidates from closed jobs.');
                $(this).val(oldStatus);
                return;
            }
            
            if (confirm(`Are you sure you want to change status from "${oldStatus}" to "${newStatus}"?`)) {
                updateSubmissionStatus(submissionId, newStatus, $(this));
            } else {
                // Reset to original value
                $(this).val(oldStatus);
            }
        });

        // Schedule button handler
        $(document).on('click', '.schedule-btn', function() {
            const submissionId = $(this).data('submission-id');
            const candidateName = $(this).data('candidate-name');
            const jobTitle = $(this).data('job-title');
            const jobId = $(this).data('job-id');
            
            $('#modal_submission_id').val(submissionId);
            $('#modal_candidate_name').val(candidateName);
            $('#modal_job_title').val(jobTitle);
            $('#modal_job_id').val(jobId);
        });

        // Resume viewer
        $(document).on('click', '.view-resume-btn', function() {
            const candidateName = $(this).data('candidate-name');
            const resumeFilename = $(this).data('resume-filename');
            
            $('#modalCandidateName').text(candidateName + "'s Resume");
            
            // Show the modal immediately
            $('#resumeViewerModal').modal('show');
            
            // Load the resume
            loadResume(resumeFilename);
        });
    });

    function updateSubmissionStatus(submissionId, newStatus, dropdownElement) {
        // Show loading state
        dropdownElement.prop('disabled', true);
        const originalValue = dropdownElement.val();
        
        console.log('Updating status:', { submissionId, newStatus });
        
        $.ajax({
            type: 'POST', 
            url: 'update_submission_status.php',
            data: { 
                submission_id: submissionId, 
                status: newStatus 
            },
            dataType: 'json',
            success: function(response) {
                console.log('Response:', response);
                
                if (response.status === 'success') {
                    showToast('Status updated successfully!', 'success');
                    // Update the old status data attribute
                    dropdownElement.data('old-status', newStatus);
                    // Refresh after 1 second to show updated data
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    showToast(response.message || 'Update failed', 'danger');
                    // Reset dropdown to original value
                    dropdownElement.val(originalValue);
                    dropdownElement.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', error);
                console.log('Response Text:', xhr.responseText);
                showToast('Network error occurred. Please try again.', 'danger');
                // Reset dropdown to original value
                dropdownElement.val(originalValue);
                dropdownElement.prop('disabled', false);
            }
        });
    }

    function loadResume(resumeFilename) {
        const resumePath = 'uploads/' + resumeFilename;
        const modalBody = $('#modalResumeBody');
        
        // Check if file is PDF
        if (resumeFilename.toLowerCase().endsWith('.pdf')) {
            modalBody.html(`
                <iframe src="${resumePath}" 
                        style="width: 100%; height: 600px; border: none;" 
                        onload="resumeLoaded()"
                        onerror="resumeFailed()">
                </iframe>
            `);
        } else {
            // For non-PDF files, show download option
            modalBody.html(`
                <div class="text-center p-5">
                    <i class="bi bi-file-earmark-text fs-1 text-warning"></i>
                    <h5 class="mt-3">Resume Preview Not Available</h5>
                    <p class="text-muted">This file type cannot be previewed in the browser.</p>
                    <a href="${resumePath}" class="btn btn-primary mt-2" download target="_blank">
                        <i class="bi bi-download"></i> Download Resume
                    </a>
                </div>
            `);
        }
    }

    function resumeLoaded() {
        console.log('Resume loaded successfully');
    }

    function resumeFailed() {
        $('#modalResumeBody').html(`
            <div class="text-center p-5">
                <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                <h5 class="mt-3">Failed to Load Resume</h5>
                <p class="text-muted">The resume file could not be loaded. Please check if the file exists.</p>
                <button class="btn btn-secondary mt-2" onclick="$('#resumeViewerModal').modal('hide')">
                    Close
                </button>
            </div>
        `);
    }

    function showToast(message, type = 'success') {
        const bgColor = type === 'success' ? 'bg-success' : 'bg-danger';
        const container = $('#toast-container');
        const toast = $(`
            <div class="toast-notification ${bgColor}">
                ${message}
            </div>
        `);
        container.append(toast);
        
        // Auto remove after 4 seconds
        setTimeout(() => {
            toast.remove();
        }, 4000);
    }
    </script>
</body>
</html>
<?php
// End output buffering and flush the content
ob_end_flush();
?>