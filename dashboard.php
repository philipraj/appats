<?php
// For development only: shows detailed errors. Remove for production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'auth.php';
include 'header.php';

// --- HELPER FUNCTION FOR DATE CALCULATION ---
function get_yesterday_range(): array {
    $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $interval = ($dt->format('N') === '1') ? 'P3D' : 'P1D';
    $dt->sub(new DateInterval($interval));
    return [
        'start' => $dt->format('Y-m-d 00:00:00'),
        'end'   => $dt->format('Y-m-d 23:59:59'),
    ];
}

// --- DATA FOR ATTENDANCE WIDGET ---
$user_id = $_SESSION['user_id'];
$today_start = date('Y-m-d 00:00:00');
$stmt = $conn->prepare("SELECT id, check_in_time FROM attendance WHERE user_id = ? AND check_in_time >= ? AND check_out_time IS NULL");
$stmt->bind_param("is", $user_id, $today_start);
$stmt->execute();
$open_checkin = $stmt->get_result()->fetch_assoc();
$stmt->close();


// --- DATA FOR TOP CARDS (PERFORMANCE OPTIMIZED) ---
$today_end = date('Y-m-d 23:59:59');
$yesterday_range = get_yesterday_range();
$status_offered = 'Offered';
$status_submitted = 'Submitted to client';

$kpi_sql = "
    SELECT
        (SELECT COUNT(id) FROM job_orders WHERE status = 'Active') as job_count,
        (SELECT COUNT(id) FROM submissions WHERE status = ? AND updated_at BETWEEN ? AND ?) as submission_count,
        (SELECT COUNT(id) FROM interviews WHERE interview_datetime BETWEEN ? AND ?) as interview_count,
        (SELECT COUNT(id) FROM submissions WHERE status = ? AND updated_at BETWEEN ? AND ?) as offered_count
";
$stmt = $conn->prepare($kpi_sql);

// FINAL FIX: The type string must be "ssssssss" (8 's' characters) to match the 8 placeholders in the SQL query.
$stmt->bind_param(
    "ssssssss",
    $status_submitted, $yesterday_range['start'], $yesterday_range['end'], // for submission_count (3)
    $today_start, $today_end,                                            // for interview_count (2)
    $status_offered, $today_start, $today_end                           // for offered_count (3)
);

$stmt->execute();
$result = $stmt->get_result();
$top_card_counts = $result->fetch_assoc();
$stmt->close();


// --- DATA FOR MAIN CHARTS ---
$full_dataset_stmt = $conn->query("
    SELECT s.status, r.full_name as recruiter_name, DATE(s.submitted_at) as submission_date
    FROM submissions s JOIN users r ON s.recruiter_id = r.id
");
$full_dataset = $full_dataset_stmt->fetch_all(MYSQLI_ASSOC);
$full_dataset_json = json_encode($full_dataset);

$all_recruiters_stmt = $conn->query("SELECT id, full_name FROM users WHERE role IN ('recruiter', 'team_lead', 'admin') ORDER BY full_name");
$all_recruiters = $all_recruiters_stmt->fetch_all(MYSQLI_ASSOC);

// --- DATA FOR SUBMISSION VALIDATION PANEL (for Admins/Team Leads) ---
$submissions_for_review = [];
$total_pages = 0;
$total_results = 0;
if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'team_lead')) {
    $filter_job = $_GET['filter_job'] ?? '';
    $filter_client = $_GET['filter_client'] ?? '';
    $filter_recruiter = $_GET['filter_recruiter'] ?? '';
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $base_review_sql = "FROM submissions s JOIN candidates c ON s.candidate_id = c.id JOIN job_orders j ON s.job_id = j.id JOIN clients cl ON j.client_id = cl.id JOIN users r ON s.recruiter_id = r.id WHERE s.status = 'Submitted'";
    $where_clauses = [];
    $count_params = [];
    $types = '';

    if (!empty($filter_job))     { $where_clauses[] = "j.job_title LIKE ?";    $count_params[] = "%$filter_job%";     $types .= 's'; }
    if (!empty($filter_client))   { $where_clauses[] = "cl.client_name LIKE ?"; $count_params[] = "%$filter_client%";   $types .= 's'; }
    if (!empty($filter_recruiter)){ $where_clauses[] = "r.full_name LIKE ?";    $count_params[] = "%$filter_recruiter%"; $types .= 's'; }

    $where_sql = !empty($where_clauses) ? " AND " . implode(" AND ", $where_clauses) : '';

    // Get total count for pagination
    $total_results_sql = "SELECT COUNT(s.id) as total " . $base_review_sql . $where_sql;
    $stmt_count = $conn->prepare($total_results_sql);
    if (!empty($count_params)) {
        $stmt_count->bind_param($types, ...$count_params);
    }
    $stmt_count->execute();
    $total_results = $stmt_count->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_results / $limit);
    $stmt_count->close();

    // Get results for the current page
    $results_sql = "SELECT s.id as submission_id, c.candidate_name, j.job_title, cl.client_name, r.full_name as recruiter_name, s.submitted_at "
        . $base_review_sql . $where_sql . " ORDER BY s.submitted_at DESC LIMIT ? OFFSET ?";
    
    $results_params = $count_params; // Use the same filter params
    $results_params[] = $limit;
    $results_params[] = $offset;
    $results_types = $types . 'ii';
    
    $stmt_results = $conn->prepare($results_sql);
    $stmt_results->bind_param($results_types, ...$results_params);
    $stmt_results->execute();
    $submissions_for_review = $stmt_results->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_results->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --info-color: #4895ef;
            --warning-color: #f8961e;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --text-muted: #6c757d;
        }
        body { background-color: var(--light-color); }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: none;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            border-radius: 12px 12px 0 0 !important;
            background-color: rgba(var(--bs-primary-rgb), 0.05);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        .kpi-card .card-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        .kpi-card .card-text { font-size: 2rem; font-weight: 700; }
        .btn-checkin { background-color: #2ecc71; border-color: #27ae60; color: white; }
        .btn-checkout { background-color: #e74c3c; border-color: #c0392b; color: white; }
        .attendance-widget {
            background: white;
            border-radius: 10px;
            padding: 10px 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .table-hover tbody tr:hover { background-color: rgba(var(--bs-primary-rgb), 0.05); }
        .pagination .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }
        .pagination .page-link { color: var(--primary-color); }
        .chart-container { position: relative; height: 320px; }
        .kpi-value { transition: color 0.3s ease; }
    </style>
</head>
<body>
    <div class="container-fluid my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-dark-800">Dashboard</h1>
                <p class="mb-0 text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            <div class="attendance-widget">
                <?php if ($open_checkin): ?>
                    <div class="d-flex align-items-center">
                        <div class="me-3"><i class="bi bi-clock-history text-primary"></i> Checked in at: <strong><?php echo date('h:i A', strtotime($open_checkin['check_in_time'])); ?></strong></div>
                        <form action="process_attendance.php" method="POST" class="mb-0">
                            <button type="submit" name="check_out" class="btn btn-checkout btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Check Out</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form action="process_attendance.php" method="POST" class="mb-0">
                        <button type="submit" name="check_in" class="btn btn-checkin btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Check In</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100 kpi-card" style="border-left: 4px solid var(--primary-color);">
                    <div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="card-title">Open Job Orders</h5><p class="card-text text-primary"><?php echo $top_card_counts['job_count']; ?></p></div><i class="bi bi-briefcase-fill text-primary opacity-50 fs-2"></i></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100 kpi-card" style="border-left: 4px solid var(--warning-color);">
                     <div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="card-title">Yesterday's Submissions</h5><p class="card-text text-warning"><?php echo $top_card_counts['submission_count']; ?></p></div><i class="bi bi-send-fill text-warning opacity-50 fs-2"></i></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100 kpi-card" style="border-left: 4px solid var(--success-color);">
                    <div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="card-title">Today's Interviews</h5><p class="card-text" style="color:var(--success-color);"><?php echo $top_card_counts['interview_count']; ?></p></div><i class="bi bi-people-fill text-success opacity-50 fs-2"></i></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100 kpi-card" style="border-left: 4px solid var(--danger-color);">
                    <div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="card-title">Today's Offers</h5><p class="card-text" style="color:var(--danger-color);"><?php echo $top_card_counts['offered_count']; ?></p></div><i class="bi bi-award-fill text-danger opacity-50 fs-2"></i></div>
                </div>
            </div>
        </div>

        <div class="card filter-card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3"><label class="form-label">From Date</label><input type="date" id="from_date_filter" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">To Date</label><input type="date" id="to_date_filter" class="form-control"></div>
                    <div class="col-md-4">
                        <label class="form-label">Recruiter</label>
                        <select id="recruiter_filter" class="form-select">
                            <option value="">All Recruiters</option>
                            <?php foreach($all_recruiters as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['full_name']); ?>"><?php echo htmlspecialchars($r['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><button id="reset-filters-btn" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-counterclockwise"></i> Reset</button></div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
             <div class="col-md-3 mb-4"><div class="card h-100"><div class="card-body text-center"><h5 class="card-title">SUBMISSIONS</h5><p class="card-text fs-2 fw-bold text-primary kpi-value" id="kpi-submissions">0</p></div></div></div>
             <div class="col-md-3 mb-4"><div class="card h-100"><div class="card-body text-center"><h5 class="card-title">INTERVIEWS</h5><p class="card-text fs-2 fw-bold kpi-value" id="kpi-interviews" style="color:var(--success-color);">0</p></div></div></div>
             <div class="col-md-3 mb-4"><div class="card h-100"><div class="card-body text-center"><h5 class="card-title">OFFERS</h5><p class="card-text fs-2 fw-bold text-warning kpi-value" id="kpi-offers">0</p></div></div></div>
             <div class="col-md-3 mb-4"><div class="card h-100"><div class="card-body text-center"><h5 class="card-title">JOINED</h5><p class="card-text fs-2 fw-bold text-danger kpi-value" id="kpi-joined">0</p></div></div></div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="card h-100"><div class="card-header"><i class="bi bi-funnel-fill me-2"></i>Recruitment Funnel</div><div class="card-body"><div class="chart-container"><canvas id="funnelChart"></canvas></div></div></div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100"><div class="card-header"><i class="bi bi-pie-chart-fill me-2"></i>Submissions by Recruiter</div><div class="card-body"><div class="chart-container"><canvas id="recruiterChart"></canvas></div></div></div>
            </div>
        </div>

        <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'team_lead')): ?>
        <div class="card">
            <div class="card-header"><div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Submissions for Review</h5>
                <span class="badge bg-primary rounded-pill"><?php echo $total_results; ?> pending</span>
            </div></div>
            <div class="card-body">
                <form action="dashboard.php" method="GET" class="row g-3 mb-4">
                    <div class="col-md-4"><input type="text" name="filter_job" class="form-control" placeholder="Filter by Job Title..." value="<?php echo htmlspecialchars($filter_job); ?>"></div>
                    <div class="col-md-4"><input type="text" name="filter_client" class="form-control" placeholder="Filter by Client..." value="<?php echo htmlspecialchars($filter_client); ?>"></div>
                    <div class="col-md-2"><input type="text" name="filter_recruiter" class="form-control" placeholder="Filter by Recruiter..." value="<?php echo htmlspecialchars($filter_recruiter); ?>"></div>
                    <div class="col-md-2 d-flex"><button type="submit" class="btn btn-primary w-100 me-2">Filter</button><a href="dashboard.php" class="btn btn-outline-secondary w-100">Clear</a></div>
                </form>
                
                <form action="process_bulk_validation.php" method="POST">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr>
                                <th width="40"><input type="checkbox" id="select-all-checkbox" class="form-check-input"></th>
                                <th>Submitted On</th><th>Candidate</th><th>Job Title</th><th>Client</th><th>Submitted By</th>
                            </tr></thead>
                            <tbody>
                            <?php if (empty($submissions_for_review)): ?>
                                <tr><td colspan="6" class="text-center py-5"><i class="bi bi-inbox fs-2 text-muted"></i><p class="mt-2 text-muted">No submissions awaiting review.</p></td></tr>
                            <?php else: ?>
                                <?php foreach ($submissions_for_review as $sub): ?>
                                    <tr>
                                        <td><input type="checkbox" class="submission-checkbox form-check-input" name="submission_ids[]" value="<?php echo $sub['submission_id']; ?>"></td>
                                        <td><?php echo date('d-M-Y', strtotime($sub['submitted_at'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($sub['candidate_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sub['job_title']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['recruiter_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!empty($submissions_for_review)): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="btn-group">
                            <button type="submit" name="bulk_action" value="approve" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Approve Selected</button>
                            <button type="submit" name="bulk_action" value="reject" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i> Reject Selected</button>
                        </div>
                        <nav><ul class="pagination mb-0">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&filter_job=<?php echo urlencode($filter_job); ?>&filter_client=<?php echo urlencode($filter_client); ?>&filter_recruiter=<?php echo urlencode($filter_recruiter); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul></nav>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script>
    $(document).ready(function() {
        const fullDataset = <?php echo $full_dataset_json; ?>;
        let funnelChart, recruiterChart;

        // Get CSS variables for chart colors
        const style = getComputedStyle(document.body);
        const chartColors = {
            primary: style.getPropertyValue('--primary-color').trim(),
            success: style.getPropertyValue('--success-color').trim(),
            warning: style.getPropertyValue('--warning-color').trim(),
            danger: style.getPropertyValue('--danger-color').trim(),
            info: style.getPropertyValue('--info-color').trim(),
            secondary: style.getPropertyValue('--secondary-color').trim()
        };

        const funnelOrder = ['Submitted to client', 'L1 interview', 'Awaiting Feedback', 'L2 interview', 'Customer Interview', 'Offered', 'Joined'];
        const statusGroups = {
            submissions: ['Submitted to client'],
            interviews: ['L1 interview', 'Awaiting Feedback', 'L2 interview', 'Customer Interview'],
            offers: ['Offered'],
            joined: ['Joined']
        };

        function updateDashboard(data) {
            // 1. Calculate all KPIs in one go for performance
            const kpi = data.reduce((acc, d) => {
                if (statusGroups.submissions.includes(d.status)) acc.submissions++;
                if (statusGroups.interviews.includes(d.status)) acc.interviews++;
                if (statusGroups.offers.includes(d.status)) acc.offers++;
                if (statusGroups.joined.includes(d.status)) acc.joined++;
                acc.recruiterCounts[d.recruiter_name] = (acc.recruiterCounts[d.recruiter_name] || 0) + 1;
                return acc;
            }, { submissions: 0, interviews: 0, offers: 0, joined: 0, recruiterCounts: {} });
            
            // 2. Update KPI Cards with animation
            $('.kpi-value').each(function() { $(this).fadeOut(150); });
            setTimeout(() => {
                $('#kpi-submissions').text(kpi.submissions).fadeIn(150);
                $('#kpi-interviews').text(kpi.interviews).fadeIn(150);
                $('#kpi-offers').text(kpi.offers).fadeIn(150);
                $('#kpi-joined').text(kpi.joined).fadeIn(150);
            }, 150);

            // 3. Prepare data for charts
            const funnelData = funnelOrder.map(status => {
                return data.filter(d => d.status === status).length;
            });

            const recruiterData = Object.entries(kpi.recruiterCounts)
                .sort(([, a], [, b]) => b - a) // Sort recruiters by submission count
                .reduce((r, [k, v]) => ({ ...r, [k]: v }), {});
                
            // 4. Destroy old charts if they exist
            if (funnelChart) funnelChart.destroy();
            if (recruiterChart) recruiterChart.destroy();

            // 5. Render new charts
            funnelChart = new Chart(document.getElementById('funnelChart'), {
                type: 'bar',
                data: {
                    labels: funnelOrder,
                    datasets: [{
                        label: 'Candidate Count',
                        data: funnelData,
                        backgroundColor: [chartColors.primary, chartColors.info, chartColors.info, chartColors.success, chartColors.success, chartColors.warning, chartColors.danger],
                        borderRadius: 4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
            });

            recruiterChart = new Chart(document.getElementById('recruiterChart'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(recruiterData),
                    datasets: [{
                        label: 'Submissions',
                        data: Object.values(recruiterData)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    onClick: (evt, elements) => {
                        if (elements.length > 0) {
                            const clickedRecruiter = recruiterChart.data.labels[elements[0].index];
                            $('#recruiter_filter').val(clickedRecruiter).trigger('change');
                        }
                    }
                }
            });
        }

        function applyFilters() {
            const fromDate = $('#from_date_filter').val();
            const toDate = $('#to_date_filter').val();
            const recruiter = $('#recruiter_filter').val();
            
            let filteredData = fullDataset;
            if (fromDate) { filteredData = filteredData.filter(d => d.submission_date >= fromDate); }
            if (toDate) { filteredData = filteredData.filter(d => d.submission_date <= toDate); }
            if (recruiter) { filteredData = filteredData.filter(d => d.recruiter_name === recruiter); }
            
            updateDashboard(filteredData);
        }

        function setDefaultDates() {
            const today = new Date();
            const thirtyDaysAgo = new Date(new Date().setDate(today.getDate() - 30));
            $('#to_date_filter').val(today.toISOString().split('T')[0]);
            $('#from_date_filter').val(thirtyDaysAgo.toISOString().split('T')[0]);
        }

        // --- Event Listeners ---
        $('#from_date_filter, #to_date_filter, #recruiter_filter').on('change', applyFilters);
        $('#reset-filters-btn').on('click', () => {
            setDefaultDates();
            $('#recruiter_filter').val('');
            applyFilters();
        });
        $('#select-all-checkbox').on('click', function(){
            $('.submission-checkbox').prop('checked', $(this).is(':checked'));
        });

        // --- Initial Load ---
        setDefaultDates();
        applyFilters();
    });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>