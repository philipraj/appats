<?php
require_once 'config.php';
require_once 'auth.php';

// THE FIX: Ensure the session is started BEFORE checking permissions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Now, perform the permission check
if (!hasPermission('job_orders', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// The rest of the page can now load
include 'header.php';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';


// --- Helper function for status badges ---
function render_status_badge($status) {
    $badge_class = 'bg-secondary';
    switch ($status) {
        case 'Active': $badge_class = 'bg-success'; break;
        case 'Hold': $badge_class = 'bg-warning text-dark'; break;
        case 'Closed': $badge_class = 'bg-danger'; break;
    }
    return "<span class='badge " . $badge_class . "'>" . htmlspecialchars($status) . "</span>";
}

// --- Filter & Pagination Logic ---
$job_title_filter = $_GET['job_title'] ?? '';
$client_id_filter = $_GET['client_id'] ?? '';
$status_filter = $_GET['status'] ?? 'Active';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$sql_conditions = [];
$params = [];
$types = '';

if (!empty($job_title_filter)) { $sql_conditions[] = "j.job_title LIKE ?"; $params[] = "%{$job_title_filter}%"; $types .= 's'; }
if (!empty($client_id_filter)) { $sql_conditions[] = "j.client_id = ?"; $params[] = $client_id_filter; $types .= 'i'; }
if ($status_filter && $status_filter !== 'All') { $sql_conditions[] = "j.status = ?"; $params[] = $status_filter; $types .= 's'; }

$where_clause = !empty($sql_conditions) ? " WHERE " . implode(" AND ", $sql_conditions) : '';

// Get total count for pagination
$total_sql = "SELECT COUNT(j.id) as total FROM job_orders j" . $where_clause;
$total_stmt = $conn->prepare($total_sql);
if (!empty($params)) { $total_stmt->bind_param($types, ...$params); }
$total_stmt->execute();
$total_results = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);
$total_stmt->close();

// Get job orders for the current page
$jobs_sql = "SELECT j.id, j.job_title, j.job_location_city, c.client_name, j.status, j.created_at 
             FROM job_orders j 
             LEFT JOIN clients c ON j.client_id = c.id" . $where_clause . " 
             ORDER BY j.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit; $types .= 'i';
$params[] = $offset; $types .= 'i';

$jobs_stmt = $conn->prepare($jobs_sql);
$jobs_stmt->bind_param($types, ...$params);
$jobs_stmt->execute();
$jobs_result = $jobs_stmt->get_result();

$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetch_all(MYSQLI_ASSOC);
?>
<style>
    .status-select-wrapper { position: relative; display: inline-block; }
    .status-select-wrapper .badge { position: absolute; top: 50%; left: 0.5rem; transform: translateY(-50%); pointer-events: none; z-index: 1; }
    .status-select-wrapper select.form-select-sm { padding-left: 5rem; width: 150px; position: relative; z-index: 2; background: transparent; }
    .status-select-wrapper select.form-select-sm:hover { background-color: rgba(255,255,255,0.7); }
    .table-row-flash { animation: flash 1.5s ease-out; }
    @keyframes flash {
        0% { background-color: transparent; }
        20% { background-color: rgba(var(--bs-warning-rgb), 0.4); }
        100% { background-color: transparent; }
    }
    .job-table tbody tr:nth-child(odd) { background-color: #f8f9fa; }
    .job-table tbody tr:nth-child(even) { background-color: #e9ecef; }
    .job-table tbody tr:hover { background-color: #d1e7ff !important; }
    .card { border: none; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
    .card-header { background: linear-gradient(135deg, #f8f9fc 0%, #e2e6f0 100%); border-bottom: 1px solid #e3e6f0; font-weight: 600; }
    .table th { border-top: none; font-weight: 600; color: #4e73df; background-color: #f8f9fc; }
    .job-title-link { color: #2e59d9; font-weight: 600; transition: color 0.2s; }
    .job-title-link:hover { color: #1c3ca0; text-decoration: underline; }
    .pagination .page-item.active .page-link { background-color: #4e73df; border-color: #4e73df; }
    .btn-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); border: none; }
    .btn-primary:hover { background: linear-gradient(135deg, #3a5ccc 0%, #1a3cb0 100%); }
    .toast-container { z-index: 9999; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-briefcase-fill me-2 text-primary"></i>Job Orders</h1>
        <?php if (hasPermission('job_orders', 'create')): ?>
            <a href="add_job_order.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle me-1"></i> Add New Job</a>
        <?php endif; ?>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-filter me-2"></i>Filter Job Orders</h6>
        </div>
        <div class="card-body">
            <form action="job_orders.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="job_title" class="form-label fw-semibold">Job Title</label>
                    <input type="text" id="job_title" name="job_title" class="form-control border-0 shadow-sm" placeholder="e.g., Software Engineer" value="<?php echo htmlspecialchars($job_title_filter); ?>">
                </div>
                <div class="col-md-3">
                    <label for="client_id" class="form-label fw-semibold">Client</label>
                    <select id="client_id" name="client_id" class="form-select border-0 shadow-sm">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo ($client_id_filter == $client['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($client['client_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select id="status" name="status" class="form-select border-0 shadow-sm">
                        <option value="Active" <?php echo ($status_filter == 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Hold" <?php echo ($status_filter == 'Hold') ? 'selected' : ''; ?>>Hold</option>
                        <option value="Closed" <?php echo ($status_filter == 'Closed') ? 'selected' : ''; ?>>Closed</option>
                        <option value="All" <?php echo ($status_filter == 'All') ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="bi bi-search me-1"></i> Search</button>
                    <a href="job_orders.php" class="btn btn-outline-secondary w-100 shadow-sm"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle job-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Job Title</th>
                            <th>Client</th>
                            <th>Location</th>
                            <th>Created On</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jobs_result->num_rows > 0): ?>
                            <?php while ($row = $jobs_result->fetch_assoc()): ?>
                                <tr id="job-row-<?php echo $row['id']; ?>">
                                    <td class="ps-4">
                                        <strong>
                                            <a href="view_job.php?id=<?php echo $row['id']; ?>" class="job-title-link text-decoration-none">
                                                <?php echo htmlspecialchars($row['job_title']); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['client_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['job_location_city']); ?></td>
                                    <td><?php echo date('d-M-Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <?php if (hasPermission('job_orders', 'edit')): ?>
                                            <div class="status-select-wrapper">
                                                <?php echo render_status_badge($row['status']); ?>
                                                <select class="form-select form-select-sm job-status-dropdown border-0 shadow-sm" 
                                                        data-job-id="<?php echo $row['id']; ?>" 
                                                        data-current-status="<?php echo $row['status']; ?>"
                                                        data-job-title="<?php echo htmlspecialchars($row['job_title']); ?>"
                                                        aria-label="Update job status">
                                                    <option value="Active" <?php echo ($row['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="Hold" <?php echo ($row['status'] == 'Hold') ? 'selected' : ''; ?>>Hold</option>
                                                    <option value="Closed" <?php echo ($row['status'] == 'Closed') ? 'selected' : ''; ?>>Closed</option>
                                                </select>
                                            </div>
                                        <?php else: ?>
                                            <?php echo render_status_badge($row['status']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group shadow-sm">
                                            <a href="view_job.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary border-0" title="View"><i class="bi bi-eye"></i></a>
                                            <a href="pipeline.php?job_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary border-0" title="Pipeline"><i class="bi bi-kanban"></i></a>
                                            <?php if (hasPermission('job_orders', 'edit')): ?>
                                                <a href="edit_job_order.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info border-0" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted opacity-50"></i>
                                    <p class="mt-3 mb-0 text-muted">No job orders found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center shadow-sm">
                <?php for ($i = 1; $i <= $total_pages; $i++):
                    $query_params = $_GET; $query_params['page'] = $i;
                    $href = '?' . http_build_query($query_params);
                ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link border-0 shadow-sm" href="<?php echo $href; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <!-- Toast will be inserted here dynamically -->
</div>

<script>
$(document).ready(function() {
    // Function to show toast notification
    function showToast(message, type = 'success') {
        const icon = type === 'success' ? 'bi-check-circle-fill' : 
                    type === 'error' ? 'bi-exclamation-circle-fill' : 
                    'bi-info-circle-fill';
        const bgColor = type === 'success' ? 'bg-success' : 
                       type === 'error' ? 'bg-danger' : 
                       'bg-info';
        
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${icon} me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        $('.toast-container').append(toastHtml);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();
        
        // Remove toast from DOM after it hides
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function () {
            $(this).remove();
        });
    }

    // Function to get confirmation message based on status change
    function getConfirmationMessage(currentStatus, newStatus, jobTitle) {
        if (newStatus === 'Closed' && currentStatus !== 'Closed') {
            return `Are you sure you want to CLOSE "${jobTitle}"?\n\nThis will move the job to closed status.`;
        }
        if (newStatus === 'Hold' && currentStatus !== 'Hold') {
            return `Are you sure you want to put "${jobTitle}" on HOLD?\n\nThis will pause all activities for this job.`;
        }
        if (newStatus === 'Active' && currentStatus !== 'Active') {
            return `Are you sure you want to ACTIVATE "${jobTitle}"?\n\nThis will resume the job for normal operations.`;
        }
        return `Change status of "${jobTitle}" from ${currentStatus} to ${newStatus}?`;
    }

    $('.job-status-dropdown').on('change', function() {
        const selectElement = $(this);
        const jobId = selectElement.data('job-id');
        const currentStatus = selectElement.data('current-status');
        const newStatus = selectElement.val();
        const jobTitle = selectElement.data('job-title');
        const row = $('#job-row-' + jobId);
        const badgeWrapper = selectElement.closest('.status-select-wrapper');
        
        // Get current filter status from the page
        const currentFilterStatus = $('#status').val(); // Get the filter dropdown value
        
        // Don't show confirmation if status didn't actually change
        if (currentStatus === newStatus) {
            return;
        }

        // Show confirmation dialog
        const confirmationMessage = getConfirmationMessage(currentStatus, newStatus, jobTitle);
        
        if (!confirm(confirmationMessage)) {
            // Revert to previous status
            selectElement.val(currentStatus);
            return;
        }

        // Store original state for rollback
        const originalHTML = badgeWrapper.html();
        const originalStatus = currentStatus;
        
        // Show loading state on badge
        const badge = badgeWrapper.find('.badge');
        badge.removeClass('bg-success bg-warning bg-danger bg-secondary').addClass('bg-secondary').text('Updating...');

        // Make AJAX call
        $.ajax({
            type: 'POST',
            url: 'update_job_status.php',
            data: { 
                job_id: jobId, 
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Response:', response); // Debug log
                
                if (response.status === 'success') {
                    // Update the data attribute for current status
                    selectElement.data('current-status', newStatus);
                    
                    // Update badge immediately with new HTML
                    badgeWrapper.html(response.badge_html + selectElement.prop('outerHTML'));
                    
                    // Re-attach the event handler to the new select element
                    const newSelect = badgeWrapper.find('.job-status-dropdown');
                    newSelect
                        .data('job-id', jobId)
                        .data('current-status', newStatus)
                        .data('job-title', jobTitle)
                        .val(newStatus)
                        .off('change')
                        .on('change', function() {
                            // Rebind the change event
                            $(this).trigger('change');
                        });
                    
                    // Flash animation for visual feedback
                    row.addClass('table-row-flash');
                    setTimeout(() => row.removeClass('table-row-flash'), 1500);
                    
                    // Show success toast
                    showToast(`Status updated to ${newStatus}`, 'success');
                    
                    // Check if the row should be hidden based on current filter
                    // If we're filtering by a specific status and the new status doesn't match, hide the row
                    if (currentFilterStatus && currentFilterStatus !== 'All' && newStatus !== currentFilterStatus) {
                        // Fade out and remove the row after animation
                        setTimeout(() => {
                            row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // If no rows left, check if we should show empty state
                                if ($('tbody tr').length === 0) {
                                    const emptyRow = `
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <i class="bi bi-inbox fs-1 text-muted opacity-50"></i>
                                                <p class="mt-3 mb-0 text-muted">No job orders found.</p>
                                            </td>
                                        </tr>
                                    `;
                                    $('tbody').html(emptyRow);
                                }
                            });
                        }, 1000); // Wait 1 second before hiding to show the flash animation
                    }
                    
                } else {
                    // Revert to original state on error
                    badgeWrapper.html(originalHTML);
                    selectElement.val(originalStatus);
                    showToast('Error: ' + (response.message || 'Could not update status'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                // Revert to original state
                badgeWrapper.html(originalHTML);
                selectElement.val(originalStatus);
                
                // Check if we got a response from the server
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        showToast('Error: ' + (errorResponse.message || 'Could not update status'), 'error');
                    } catch (e) {
                        showToast('Server Error: ' + xhr.responseText.substring(0, 100), 'error');
                    }
                } else {
                    showToast('Network error occurred. Please try again.', 'error');
                }
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>