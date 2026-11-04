<?php
require_once 'config.php';
require_once 'auth.php';
include 'header.php';

// Security: Only 'admin' and 'delivery_manager' can view this page.
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'delivery_manager') {
    $_SESSION['message'] = "You do not have permission to access this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// --- DATA FETCHING ---
// Get all users for the filter
$users_result = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
if ($users_result) {
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
} else {
    $users = [];
    error_log("Users query failed: " . $conn->error);
}

// Get all pending leave requests
$pending_leaves_result = $conn->query("SELECT l.*, u.full_name FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.status = 'Pending' ORDER BY l.start_date ASC");
if ($pending_leaves_result) {
    $pending_leaves = $pending_leaves_result->fetch_all(MYSQLI_ASSOC);
} else {
    $pending_leaves = [];
    error_log("Pending leaves query failed: " . $conn->error);
}

// --- Filter Logic for Attendance Report ---
$from_date = $_POST['from_date'] ?? date('Y-m-d');
$to_date = $_POST['to_date'] ?? date('Y-m-d');
$user_id_filter = $_POST['user_id'] ?? '';

// Build the attendance query safely
$sql = "SELECT a.*, u.full_name FROM attendance a JOIN users u ON a.user_id = u.id";
$where_clauses = ["DATE(a.check_in_time) BETWEEN ? AND ?"];
$params = [$from_date, $to_date];
$types = 'ss';

if (!empty($user_id_filter)) {
    $where_clauses[] = "a.user_id = ?";
    $params[] = $user_id_filter;
    $types .= 'i';
}

$sql .= " WHERE " . implode(' AND ', $where_clauses) . " ORDER BY a.check_in_time DESC";

$attendance_report = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $attendance_report = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Attendance query execution failed: " . $stmt->error);
    }
    $stmt->close();
} else {
    error_log("Attendance query preparation failed: " . $conn->error);
}

// Get statistics
$total_employees = count($users);
$pending_leaves_count = count($pending_leaves);
$today = date('Y-m-d');

// Approved today count
$approved_today_result = $conn->query("SELECT COUNT(*) as count FROM leaves WHERE status = 'Approved' AND DATE(updated_at) = '$today'");
if ($approved_today_result) {
    $approved_today = $approved_today_result->fetch_assoc()['count'];
} else {
    $approved_today = 0;
}

// On leave today count
$on_leave_today_result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM leaves WHERE status = 'Approved' AND '$today' BETWEEN start_date AND end_date");
if ($on_leave_today_result) {
    $on_leave_today = $on_leave_today_result->fetch_assoc()['count'];
} else {
    $on_leave_today = 0;
}

// Get leave history for the recent section
$leave_history_status = $_GET['status'] ?? '';
$leave_history_sql = "SELECT l.*, u.full_name FROM leaves l JOIN users u ON l.user_id = u.id";
if (in_array($leave_history_status, ['Approved', 'Rejected'])) {
    $leave_history_sql .= " WHERE l.status = '$leave_history_status'";
}
$leave_history_sql .= " ORDER BY l.updated_at DESC LIMIT 20";

$leave_history = [];
$leave_history_result = $conn->query($leave_history_sql);
if ($leave_history_result) {
    $leave_history = $leave_history_result->fetch_all(MYSQLI_ASSOC);
}
?>

<style>
    .stats-card { transition: transform 0.2s; }
    .stats-card:hover { transform: translateY(-2px); }
    .leave-table tbody tr:nth-child(odd) { background-color: #f8f9fa; }
    .leave-table tbody tr:nth-child(even) { background-color: #ffffff; }
    .attendance-table tbody tr:hover { background-color: #e3f2fd !important; }
    .text-truncate-custom { 
        max-width: 200px; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
    }
    .table th { border-top: none; }
</style>

<div class="container-fluid">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_employees; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Leaves</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_leaves_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock-history fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $approved_today; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">On Leave Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $on_leave_today; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-x fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Leaves Card -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-left-warning shadow">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Pending Leave Requests</h5>
                    <span class="badge bg-danger fs-6"><?php echo $pending_leaves_count; ?> Pending</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 leave-table">
                            <thead class="table-warning">
                                <tr>
                                    <th class="ps-4">Employee</th>
                                    <th>Leave Period</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Applied On</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($pending_leaves)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle display-4 text-success"></i>
                                            <p class="mt-2 mb-0 fs-5">No pending leave requests</p>
                                            <small class="text-muted">All leave requests have been processed</small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($pending_leaves as $leave): 
                                        $start = new DateTime($leave['start_date']);
                                        $end = new DateTime($leave['end_date']);
                                        $duration = $start->diff($end)->days + 1;
                                    ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($leave['full_name']); ?></div>
                                                <small class="text-muted">ID: <?php echo $leave['user_id']; ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo date('d M Y', strtotime($leave['start_date'])); ?></div>
                                                <div class="text-muted">to <?php echo date('d M Y', strtotime($leave['end_date'])); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-white fs-6"><?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?></span>
                                            </td>
                                            <td>
                                                <div class="text-truncate-custom" title="<?php echo htmlspecialchars($leave['reason']); ?>">
                                                    <?php echo htmlspecialchars($leave['reason']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($leave['created_at'])); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="process_leave_approval.php?id=<?php echo $leave['id']; ?>&action=approve" 
                                                       class="btn btn-success btn-sm" 
                                                       title="Approve Leave">
                                                        <i class="bi bi-check-lg me-1"></i>Approve
                                                    </a>
                                                    <a href="process_leave_approval.php?id=<?php echo $leave['id']; ?>&action=reject" 
                                                       class="btn btn-danger btn-sm" 
                                                       title="Reject Leave">
                                                        <i class="bi bi-x-lg me-1"></i>Reject
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Report Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Attendance Report</h5>
            <small class="text-muted">Showing records between <?php echo date('d M Y', strtotime($from_date)); ?> and <?php echo date('d M Y', strtotime($to_date)); ?></small>
        </div>
        <div class="card-body">
            <form action="manage_attendance.php" method="POST" class="row g-3 mb-4 align-items-end bg-light p-3 rounded">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control border-0 shadow-sm" value="<?php echo htmlspecialchars($from_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control border-0 shadow-sm" value="<?php echo htmlspecialchars($to_date); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Employee</label>
                    <select name="user_id" class="form-select border-0 shadow-sm">
                        <option value="">All Employees</option>
                        <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($user_id_filter == $user['id']) ? 'selected':''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm">
                        <i class="bi bi-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover attendance-table">
                    <thead class="table-dark">
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($attendance_report)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox display-4"></i>
                                    <p class="mt-2 mb-0">No attendance records found</p>
                                    <small class="text-muted">Try adjusting your filter criteria</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($attendance_report as $rec): 
                                // Check if employee was on leave this day
                                $attendance_date = date('Y-m-d', strtotime($rec['check_in_time']));
                                $on_leave_query = $conn->query("SELECT id FROM leaves WHERE user_id = {$rec['user_id']} AND status = 'Approved' AND '$attendance_date' BETWEEN start_date AND end_date");
                                $on_leave = $on_leave_query ? $on_leave_query->fetch_assoc() : null;
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($rec['full_name']); ?></td>
                                    <td><?php echo date('d-M-Y', strtotime($rec['check_in_time'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($rec['check_in_time'])); ?></td>
                                    <td>
                                        <?php if($rec['check_out_time']): ?>
                                            <?php echo date('h:i A', strtotime($rec['check_out_time'])); ?>
                                        <?php else: ?>
                                            <span class="text-warning fw-semibold">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($rec['check_out_time']): 
                                            $d = (new DateTime($rec['check_in_time']))->diff(new DateTime($rec['check_out_time'])); 
                                            echo $d->format('%h hrs %i mins'); 
                                        else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($on_leave): ?>
                                            <span class="badge bg-info">On Leave</span>
                                        <?php elseif(!$rec['check_out_time']): ?>
                                            <span class="badge bg-warning text-dark">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($rec['check_in_ip'] ?? 'N/A'); ?>
                                            <?php if($rec['check_out_ip']): ?>
                                                <br><?php echo htmlspecialchars($rec['check_out_ip']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Leave History Section -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-history me-2"></i>Recent Leave History</h5>
            <div class="btn-group">
                <a href="manage_attendance.php?status=Approved" class="btn btn-sm btn-outline-success <?php echo ($leave_history_status == 'Approved') ? 'active' : ''; ?>">Approved</a>
                <a href="manage_attendance.php?status=Rejected" class="btn btn-sm btn-outline-danger <?php echo ($leave_history_status == 'Rejected') ? 'active' : ''; ?>">Rejected</a>
                <a href="manage_attendance.php" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Leave Period</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Processed On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($leave_history)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">
                                    <i class="bi bi-inbox me-2"></i>No leave history found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($leave_history as $leave): 
                                $start = new DateTime($leave['start_date']);
                                $end = new DateTime($leave['end_date']);
                                $duration = $start->diff($end)->days + 1;
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($leave['start_date'])); ?> - 
                                        <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?></span></td>
                                    <td>
                                        <div class="text-truncate-custom" title="<?php echo htmlspecialchars($leave['reason']); ?>">
                                            <?php echo htmlspecialchars($leave['reason']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $leave['status'] == 'Approved' ? 'success' : 'danger'; ?>">
                                            <?php echo $leave['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $leave['updated_at'] ? date('d M Y h:i A', strtotime($leave['updated_at'])) : date('d M Y', strtotime($leave['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>