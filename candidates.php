<?php
include 'header.php';
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
// Add Tabulator for Excel-like editing
echo '<script src="https://cdn.jsdelivr.net/npm/@tabulator/tabulator@5.5.2/dist/js/tabulator.min.js"></script>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabulator/tabulator@5.5.2/dist/css/tabulator.min.css">';
require_once 'auth.php';

if (!hasPermission('candidates', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// --- Filter & Pagination Logic ---
$name_filter = $_GET['name'] ?? '';
$email_filter = $_GET['email'] ?? '';
$location_filter = $_GET['location'] ?? '';
$phone_filter = $_GET['phone'] ?? '';
$uploaded_by_filter = $_GET['uploaded_by'] ?? '';
$recruiter_id_filter = isset($_GET['recruiter_id']) ? (int)$_GET['recruiter_id'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // Default 50 per page
$offset = ($page - 1) * $limit;

$sql_conditions = [];
$params = [];
$types = '';

if (!empty($name_filter)) { $sql_conditions[] = "c.candidate_name LIKE ?"; $params[] = "%{$name_filter}%"; $types .= 's'; }
if (!empty($email_filter)) { $sql_conditions[] = "c.email LIKE ?"; $params[] = "%{$email_filter}%"; $types .= 's'; }
if (!empty($location_filter)) { $sql_conditions[] = "c.current_location LIKE ?"; $params[] = "%{$location_filter}%"; $types .= 's'; }
if (!empty($phone_filter)) { $sql_conditions[] = "c.phone LIKE ?"; $params[] = "%{$phone_filter}%"; $types .= 's'; }
if (!empty($uploaded_by_filter)) { $sql_conditions[] = "u.full_name LIKE ?"; $params[] = "%{$uploaded_by_filter}%"; $types .= 's'; }
if (!empty($recruiter_id_filter)) { $sql_conditions[] = "c.created_by_id = ?"; $params[] = $recruiter_id_filter; $types .= 'i'; }

$where_clause = !empty($sql_conditions) ? " WHERE " . implode(" AND ", $sql_conditions) : '';

// Get total count for pagination
$total_sql = "SELECT COUNT(c.id) as total FROM candidates c LEFT JOIN users u ON c.created_by_id = u.id" . $where_clause;
$total_stmt = $conn->prepare($total_sql);
if (!empty($params)) { $total_stmt->bind_param($types, ...$params); }
$total_stmt->execute();
$total_results = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);
$total_stmt->close();

// Get candidates for the current page
$candidates_sql = "
    SELECT 
        c.id, c.candidate_name, c.email, c.phone, c.current_location, 
        c.resume_filename, c.created_at, u.full_name as created_by_name
    FROM candidates c
    LEFT JOIN users u ON c.created_by_id = u.id
    $where_clause 
    ORDER BY c.id DESC 
    LIMIT ? OFFSET ?
";
$params[] = $limit; $types .= 'i';
$params[] = $offset; $types .= 'i';

$stmt = $conn->prepare($candidates_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$candidates_result = $stmt->get_result();

// Fetch data for modals/filters
$active_jobs_for_modal = $conn->query("SELECT j.id, j.job_title, c.client_name FROM job_orders j JOIN clients c ON j.client_id = c.id WHERE j.status = 'Active' ORDER BY c.client_name, j.job_title")->fetch_all(MYSQLI_ASSOC);
$recruiters_for_filter = $conn->query("SELECT id, full_name FROM users WHERE role IN ('recruiter', 'team_lead', 'admin') ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
?>
<style>
    .candidate-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 600; color: white; }
    .list-group-item .text-muted { font-size: 0.85rem; }
    .list-group-item .meta-info { font-size: 0.8rem; color: #6c757d; }
    .list-group-item.selected { background-color: #e9ecef; }
    .bulk-actions-bar { display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 1050; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border-radius: 8px; }
    
    /* New Table Styles */
    .candidates-table-container {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    .candidates-table {
        width: 100%;
        margin-bottom: 0;
    }
    .candidates-table th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        padding: 12px 8px;
    }
    .candidates-table td {
        padding: 12px 8px;
        vertical-align: middle;
        border-top: 1px solid #dee2e6;
    }
    .candidates-table tr:hover {
        background-color: #f8f9fa;
    }
    .candidates-table .filter-input {
        width: 100%;
        padding: 4px 8px;
        font-size: 0.875rem;
        border: 1px solid #ced4da;
        border-radius: 4px;
    }
    
    /* Bulk Upload Styles */
    .drop-zone {
        border: 3px dashed #dee2e6;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        background: #f8f9fa;
        cursor: pointer;
        transition: all 0.3s;
    }
    .drop-zone:hover, .drop-zone.active {
        border-color: #198754;
        background-color: #e7f5ee;
    }
    .file-item {
        padding: 10px 15px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        margin-bottom: 8px;
        background: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .file-item.success { border-left: 4px solid #198754; }
    .file-item.warning { border-left: 4px solid #ffc107; }
    .file-item.danger { border-left: 4px solid #dc3545; }
    .tabulator-cell.editable { background-color: #fff3cd !important; }
    .tabulator-cell.duplicate { background-color: #f8d7da !important; }
    .tabulator-row.duplicate { background-color: #f8d7da !important; }
    .tabulator-row.needs_review { background-color: #fff3cd !important; }
    .tabulator-row.error { background-color: #f5c6cb !important; }
    #startUploadBtn { transition: all 0.3s; }
    #startUploadBtn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3); }
    
    /* Candidate Profile Modal */
    .candidate-profile-modal .modal-dialog {
        max-width: 800px;
        width: 95%;
    }
    .candidate-profile-modal .modal-content {
        height: 90vh;
    }
    .candidate-profile-modal .modal-body {
        overflow-y: auto;
        padding: 20px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-people-fill me-2"></i>Candidates</h1>
    <div>
        <?php if (hasPermission('candidates', 'create')): ?>
            <a href="add_candidate.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Add New Candidate</a>
            <button type="button" class="btn btn-success" id="bulkUploadBtn">
                <i class="bi bi-upload me-1"></i> Bulk Upload
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><a class="text-decoration-none text-dark" data-bs-toggle="collapse" href="#filterCollapse" role="button"><i class="bi bi-filter me-2"></i>Filter Candidates</a></div>
    <div class="collapse show" id="filterCollapse">
        <div class="card-body">
            <form action="candidates.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" placeholder="By name..." value="<?php echo htmlspecialchars($name_filter); ?>"></div>
                <div class="col-md-2"><label class="form-label">Email</label><input type="text" name="email" class="form-control" placeholder="By email..." value="<?php echo htmlspecialchars($email_filter); ?>"></div>
                <div class="col-md-2"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="By phone..." value="<?php echo htmlspecialchars($phone_filter); ?>"></div>
                <div class="col-md-3"><label class="form-label">Location</label><input type="text" name="location" class="form-control" placeholder="By location..." value="<?php echo htmlspecialchars($location_filter); ?>"></div>
                <div class="col-md-3">
                    <label class="form-label">Uploaded By</label>
                    <select name="recruiter_id" class="form-select">
                        <option value="">All Recruiters</option>
                        <?php foreach ($recruiters_for_filter as $recruiter): ?>
                            <option value="<?php echo $recruiter['id']; ?>" <?php if ($recruiter_id_filter == $recruiter['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($recruiter['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex"><button type="submit" class="btn btn-primary w-100 me-2">Filter</button><a href="candidates.php" class="btn btn-outline-secondary w-100">Clear</a></div>
            </form>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Showing <?php echo min($offset + 1, $total_results); ?> to <?php echo min($offset + $limit, $total_results); ?> of <?php echo $total_results; ?> candidates
            </div>
            <div class="d-flex align-items-center">
                <label class="me-2 mb-0">Show:</label>
                <select class="form-select form-select-sm w-auto" id="perPageSelect">
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    <option value="150" <?php echo $limit == 150 ? 'selected' : ''; ?>>150</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                    <option value="250" <?php echo $limit == 250 ? 'selected' : ''; ?>>250</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <form id="bulk-assign-form" action="bulk_assign_to_job.php" method="POST">
            <div class="candidates-table-container">
                <table class="candidates-table">
                    <thead>
                        <tr>
                            <th width="40"><input class="form-check-input" type="checkbox" id="select-all-candidates"></th>
                            <th width="25%">Name</th>
                            <th width="20%">Email</th>
                            <th width="15%">Phone</th>
                            <th width="20%">Location</th>
                            <th width="15%">Uploaded By</th>
                            <th width="15%">Uploaded Date</th>
                            <th width="15%" class="text-center">Actions</th>
                        </tr>
                        <tr class="filter-row">
                            <td></td>
                            <td><input type="text" class="filter-input" placeholder="Filter name..." id="filter-name"></td>
                            <td><input type="text" class="filter-input" placeholder="Filter email..." id="filter-email"></td>
                            <td><input type="text" class="filter-input" placeholder="Filter phone..." id="filter-phone"></td>
                            <td><input type="text" class="filter-input" placeholder="Filter location..." id="filter-location"></td>
                            <td><input type="text" class="filter-input" placeholder="Filter uploaded by..." id="filter-uploaded-by"></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($candidates_result->num_rows > 0): ?>
                            <?php while ($row = $candidates_result->fetch_assoc()): ?>
                                <tr>
                                    <td><input class="form-check-input candidate-checkbox" type="checkbox" name="candidate_ids[]" value="<?php echo $row['id']; ?>"></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="candidate-avatar me-3" style="background-color: #<?php echo substr(md5($row['candidate_name']), 0, 6); ?>;">
                                                <?php echo strtoupper(substr($row['candidate_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0)" class="text-decoration-none candidate-name-link" data-candidate-id="<?php echo $row['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($row['candidate_name']); ?></strong>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($row['current_location']); ?></td>
                                    <td><?php echo htmlspecialchars($row['created_by_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary view-resume-btn" data-candidate-id="<?php echo $row['id']; ?>" title="View Resume">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success assign-job-btn" data-bs-toggle="modal" data-bs-target="#assignJobModal" data-candidate-id="<?php echo $row['id']; ?>" data-candidate-name="<?php echo htmlspecialchars($row['candidate_name']); ?>" title="Assign to Job">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                            <?php if (hasPermission('candidates', 'edit')): ?>
                                                <a href="edit_candidate.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="text-center py-5">
                                        <i class="bi bi-person-x fs-1 text-muted"></i>
                                        <h4 class="mt-3">No Candidates Found</h4>
                                        <p class="text-muted">No candidates match your filter criteria.</p>
                                        <a href="add_candidate.php" class="btn btn-primary mt-2">Add the First Candidate</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php 
            // Calculate start and end pages for pagination
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++):
                $href = '?' . http_build_query(array_merge($_GET, ['page' => $i]));
            ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo $href; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<div class="bulk-actions-bar card p-2">
    <div class="d-flex align-items-center">
        <span class="me-3"><strong id="selected-count">0</strong> candidates selected</span>
        <button class="btn btn-success" id="bulk-assign-btn" data-bs-toggle="modal" data-bs-target="#bulkAssignJobModal"><i class="bi bi-plus-lg"></i> Bulk Assign to Job</button>
    </div>
</div>

<!-- Candidate Profile Modal (loaded from candidate_details.php) -->
<div class="modal fade candidate-profile-modal" id="candidateProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Candidate Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="candidateProfileContent">
                <!-- Content will be loaded via iframe -->
                <iframe id="candidateProfileFrame" style="width: 100%; height: 500px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Bulk Resume Upload</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Upload -->
                <div id="uploadStep">
                    <div class="drop-zone mb-4" id="dropZone">
                        <i class="bi bi-cloud-upload fs-1 text-muted mb-3"></i>
                        <h5>Drop your resume files here</h5>
                        <p class="text-muted mb-3">or click to browse (PDF, DOC, DOCX, TXT formats)</p>
                        <input type="file" id="resumeFiles" name="resumes[]" multiple 
                               accept=".pdf,.doc,.docx,.txt" class="d-none">
                        <button class="btn btn-primary" id="browseBtn">
                            <i class="bi bi-folder2-open me-1"></i> Select Files (Max 50)
                        </button>
                        <p class="small text-muted mt-2">Max file size: 10MB each • Supported: PDF, DOC, DOCX, TXT</p>
                    </div>
                    
                    <div id="selectedFiles" class="mb-3" style="display: none;">
                        <h6>Selected Files (<span id="fileCount">0</span>):</h6>
                        <div id="fileList"></div>
                    </div>
                    
                    <div id="uploadProgress" class="d-none">
                        <div class="progress mb-3" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div id="fileStatus" class="small text-center">
                            <i class="bi bi-hourglass-split me-1"></i>Parsing resumes... This may take up to 30 seconds
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Review & Edit -->
                <div id="reviewStep" style="display: none;">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Review and edit the parsed data below. Click any cell to edit. Duplicate emails are highlighted in red.
                    </div>
                    <div id="parsedDataTable" style="height: 400px;"></div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-primary" id="parsedCount">0</span> resumes parsed • 
                                <span class="badge bg-danger" id="duplicateCount">0</span> duplicates • 
                                <span class="badge bg-warning" id="reviewCount">0</span> need review
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-secondary" id="backToUpload">
                                    <i class="bi bi-arrow-left me-1"></i> Back
                                </button>
                                <button type="button" class="btn btn-success" id="saveCandidatesBtn">
                                    <i class="bi bi-save me-1"></i> Save All Candidates
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resume Viewer Modal -->
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

<!-- Assign Job Modal -->
<div class="modal fade" id="assignJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Candidate to Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignJobForm">
                <div class="modal-body">
                    <input type="hidden" name="candidate_id" id="assign_candidate_id">
                    <p>Assigning candidate: <strong id="assign_candidate_name"></strong></p>
                    <div class="mb-3">
                        <label for="assign_job_id" class="form-label">Select an Active Job <span class="text-danger">*</span></label>
                        <select name="job_id" id="assign_job_id" class="form-select" required>
                            <option value="">-- Select Job --</option>
                            <?php foreach ($active_jobs_for_modal as $job): ?>
                                <option value="<?php echo $job['id']; ?>">
                                    <?php echo htmlspecialchars($job['job_title'] . " (" . $job['client_name'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Candidate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Assign Job Modal -->
<div class="modal fade" id="bulkAssignJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Assign Candidates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to assign <strong id="bulk-assign-count">0</strong> selected candidates to the following job:</p>
                <div class="mb-3">
                    <label for="bulk_assign_job_id" class="form-label">Select an Active Job <span class="text-danger">*</span></label>
                    <select id="bulk_assign_job_id" class="form-select" required>
                        <option value="">-- Select Job --</option>
                        <?php foreach ($active_jobs_for_modal as $job): ?>
                            <option value="<?php echo $job['id']; ?>">
                                <?php echo htmlspecialchars($job['job_title'] . " (" . $job['client_name'] . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirm-bulk-assign" class="btn btn-primary">Confirm & Assign</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const resumeModal = new bootstrap.Modal(document.getElementById('resumeViewerModal'));
    const candidateProfileModal = new bootstrap.Modal(document.getElementById('candidateProfileModal'));
    
    // Per page select change
    document.getElementById('perPageSelect').addEventListener('change', function() {
        const limit = this.value;
        const url = new URL(window.location.href);
        url.searchParams.set('limit', limit);
        url.searchParams.set('page', 1); // Reset to first page when changing limit
        window.location.href = url.toString();
    });
    
    // Table filtering
    const filterInputs = document.querySelectorAll('.filter-input');
    filterInputs.forEach(input => {
        input.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                applyTableFilters();
            }
        });
        
        // Add debounce for better performance
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(applyTableFilters, 500);
        });
    });
    
    function applyTableFilters() {
        const filters = {};
        filterInputs.forEach(input => {
            const id = input.id.replace('filter-', '');
            const value = input.value.trim();
            if (value) {
                filters[id] = value;
            }
        });
        
        // Build URL with filters
        const url = new URL(window.location.href);
        
        // Remove existing filter params
        ['name', 'email', 'phone', 'location', 'uploaded_by'].forEach(param => {
            url.searchParams.delete(param);
        });
        
        // Add new filter params
        if (filters.name) url.searchParams.set('name', filters.name);
        if (filters.email) url.searchParams.set('email', filters.email);
        if (filters.phone) url.searchParams.set('phone', filters.phone);
        if (filters.location) url.searchParams.set('location', filters.location);
        if (filters['uploaded-by']) url.searchParams.set('uploaded_by', filters['uploaded-by']);
        
        // Reset to first page when filtering
        url.searchParams.set('page', 1);
        
        window.location.href = url.toString();
    }
    
    // Candidate profile click handler - using iframe to load candidate_details.php
    $(document).on('click', '.candidate-name-link', function(e) {
        e.preventDefault();
        const candidateId = $(this).data('candidate-id');
        const iframe = $('#candidateProfileFrame');
        
        // Set the iframe source to the candidate_details.php page
        iframe.attr('src', 'candidate_details.php?id=' + candidateId + '&modal=1');
        candidateProfileModal.show();
    });
    
    // Handler for the "View Resume" button
    $(document).on('click', '.view-resume-btn', function(e) {
        e.preventDefault();
        const candidateId = $(this).data('candidate-id');
        const modalBody = $('#modalResumeBody');
        const modalTitle = $('#modalCandidateName');
        
        modalTitle.text('Loading Resume...');
        modalBody.html('<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading resume...</p></div>');
        resumeModal.show();
        
        $.ajax({
            url: 'api_get_resume_content.php', 
            type: 'GET', 
            data: { candidate_id: candidateId }, 
            dataType: 'json',
            success: function(response) {
                console.log('Resume API response:', response);
                
                if (response.status === 'success') {
                    modalTitle.text(response.name + "'s Resume");
                    
                    // Use local file path instead of full URL
                    const localFilePath = 'uploads/' + response.filename;
                    const fileExtension = response.filename.split('.').pop().toLowerCase();
                    let viewerHtml;

                    if (fileExtension === 'pdf') {
                        viewerHtml = `<iframe src="${localFilePath}" style="width: 100%; height: 100%; border: none;"></iframe>`;
                    } else if (fileExtension === 'doc' || fileExtension === 'docx') {
                        // For Word documents, show download option
                        viewerHtml = `
                            <div class="text-center p-5">
                                <i class="bi bi-file-earmark-word fs-1 text-primary"></i>
                                <h5 class="mt-3">Word Document</h5>
                                <p class="text-muted">Word documents cannot be previewed in browser.</p>
                                <a href="${localFilePath}" class="btn btn-primary mt-2" download>
                                    <i class="bi bi-download"></i> Download Resume
                                </a>
                            </div>
                        `;
                    } else {
                        // For other file types
                        viewerHtml = `
                            <div class="text-center p-5">
                                <i class="bi bi-file-earmark-text fs-1 text-warning"></i>
                                <h5 class="mt-3">File Preview Not Available</h5>
                                <p class="text-muted">This file type cannot be previewed in the browser.</p>
                                <a href="${localFilePath}" class="btn btn-primary mt-2" download>
                                    <i class="bi bi-download"></i> Download Resume
                                </a>
                            </div>
                        `;
                    }
                    modalBody.html(viewerHtml);
                } else {
                    modalBody.html('<div class="text-center p-5"><i class="bi bi-exclamation-triangle fs-1 text-danger"></i><h5 class="mt-3">Resume Not Available</h5><p class="text-muted">' + response.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('Resume API error:', error);
                modalBody.html('<div class="text-center p-5"><i class="bi bi-exclamation-triangle fs-1 text-danger"></i><h5 class="mt-3">Failed to Load Resume</h5><p class="text-muted">Unable to load resume content. Please try again.</p></div>');
            }
        });
    });

    // Handler for the "Assign to Job" button
    $(document).on('click', '.assign-job-btn', function() {
        $('#assign_candidate_id').val($(this).data('candidate-id'));
        $('#assign_candidate_name').text($(this).data('candidate-name'));
    });

    // Handle assign job form submission with AJAX
    $('#assignJobForm').on('submit', function(e) {
        e.preventDefault();
        
        const candidateId = $('#assign_candidate_id').val();
        const jobId = $('#assign_job_id').val();
        const candidateName = $('#assign_candidate_name').text();
        
        if (!jobId) {
            alert('Please select a job first.');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Assigning...');
        
        $.ajax({
            url: 'assign_candidate_to_job.php',
            type: 'POST',
            data: {
                candidate_id: candidateId,
                job_id: jobId,
                assign_job: true
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Show success message
                    const modal = bootstrap.Modal.getInstance(document.getElementById('assignJobModal'));
                    modal.hide();
                    
                    // Show success alert
                    showAlert(response.message, 'success');
                    
                    // Reset form
                    $('#assignJobForm')[0].reset();
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error assigning candidate: ' + error, 'danger');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('Assign Candidate');
            }
        });
    });

    // Helper function to show alerts
    function showAlert(message, type) {
        const alertDiv = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>');
        
        $('.card:first').before(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            alertDiv.alert('close');
        }, 5000);
    }

    // --- SCRIPT FOR BULK ACTIONS ---
    const selectAllCheckbox = document.getElementById('select-all-candidates');
    const candidateCheckboxes = document.querySelectorAll('.candidate-checkbox');
    const bulkActionsBar = document.querySelector('.bulk-actions-bar');
    const selectedCountEl = document.getElementById('selected-count');
    const bulkAssignCountEl = document.getElementById('bulk-assign-count');
    const confirmBulkAssignBtn = document.getElementById('confirm-bulk-assign');
    const bulkAssignForm = document.getElementById('bulk-assign-form');
    
    function updateBulkActionsUI() {
        const selectedCheckboxes = document.querySelectorAll('.candidate-checkbox:checked');
        const count = selectedCheckboxes.length;
        if (count > 0) {
            selectedCountEl.textContent = count;
            bulkAssignCountEl.textContent = count;
            bulkActionsBar.style.display = 'block';
        } else {
            bulkActionsBar.style.display = 'none';
        }
    }

    selectAllCheckbox.addEventListener('change', function() {
        candidateCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActionsUI();
    });

    candidateCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionsUI);
    });

    confirmBulkAssignBtn.addEventListener('click', function() {
        const jobId = document.getElementById('bulk_assign_job_id').value;
        if (!jobId) {
            alert('Please select a job first.');
            return;
        }

        // Create hidden input for job ID and submit form
        const jobInput = document.createElement('input');
        jobInput.type = 'hidden';
        jobInput.name = 'job_id';
        jobInput.value = jobId;
        bulkAssignForm.appendChild(jobInput);
        
        bulkAssignForm.submit();
    });

    // --- BULK UPLOAD FUNCTIONALITY ---
    const bulkUploadBtn = document.getElementById('bulkUploadBtn');
    const bulkUploadModal = new bootstrap.Modal(document.getElementById('bulkUploadModal'));
    
    if (bulkUploadBtn) {
        bulkUploadBtn.addEventListener('click', function() {
            resetBulkUpload();
            bulkUploadModal.show();
        });
    }
    
    // Initialize Tabulator table
    let parsedTable = null;
    
    function initParsedTable(data) {
        if (parsedTable) {
            parsedTable.destroy();
        }
        
        parsedTable = new Tabulator("#parsedDataTable", {
            data: data,
            layout: "fitColumns",
            responsiveLayout: "hide",
            addRowPos: "bottom",
            history: true,
            pagination: "local",
            paginationSize: 10,
            movableColumns: true,
            resizableRows: true,
            cellEdited: function(cell) {
                const row = cell.getRow();
                updateRowStatus(row);
                if (cell.getField() === 'email') {
                    checkDuplicates();
                }
            },
            columns: [
                {
                    title: "#", 
                    field: "rowNumber", 
                    width: 70, 
                    formatter: "rownum", 
                    hozAlign: "center",
                    headerSort: false
                },
                {
                    title: "Name *", 
                    field: "name", 
                    editor: "input", 
                    validator: ["required", "minLength:2"],
                    headerTooltip: "Required: At least 2 characters"
                },
                {
                    title: "Email *", 
                    field: "email", 
                    editor: "input", 
                    validator: ["required", "email"],
                    headerTooltip: "Required: Valid email format"
                },
                {
                    title: "Phone", 
                    field: "phone", 
                    editor: "input"
                },
                {
                    title: "Resume File", 
                    field: "filename", 
                    width: 200,
                    formatter: function(cell) {
                        const filename = cell.getValue();
                        return `<span class="text-truncate d-inline-block" style="max-width: 180px;" title="${filename}">
                            <i class="bi bi-file-earmark-text me-1"></i>${filename}
                        </span>`;
                    }
                },
                {
                    title: "Status", 
                    field: "status", 
                    width: 120, 
                    formatter: function(cell) {
                        const status = cell.getValue();
                        const statusConfig = {
                            'ready': {text: 'Ready', class: 'success'},
                            'needs_review': {text: 'Needs Review', class: 'warning'},
                            'duplicate': {text: 'Duplicate', class: 'danger'},
                            'error': {text: 'Error', class: 'danger'}
                        };
                        const config = statusConfig[status] || {text: status, class: 'secondary'};
                        return `<span class="badge bg-${config.class}">${config.text}</span>`;
                    }
                },
                {
                    title: "Actions", 
                    field: "actions", 
                    width: 80, 
                    formatter: function() {
                        return `<button class="btn btn-sm btn-outline-danger remove-row">
                            <i class="bi bi-trash"></i>
                        </button>`;
                    }, 
                    cellClick: function(e, cell) {
                        if (e.target.closest('.remove-row')) {
                            cell.getRow().delete();
                            updateCounts();
                            checkDuplicates();
                        }
                    },
                    headerSort: false
                }
            ],
            rowFormatter: function(row) {
                const data = row.getData();
                const rowElement = row.getElement();
                
                // Reset classes
                rowElement.classList.remove('duplicate', 'needs_review', 'error');
                
                // Add status-based classes
                if (data.status === 'duplicate') {
                    rowElement.classList.add('duplicate');
                } else if (data.status === 'needs_review') {
                    rowElement.classList.add('needs_review');
                } else if (data.status === 'error') {
                    rowElement.classList.add('error');
                }
            }
        });
        
        // Add row deletion handlers
        parsedTable.on("rowDeleted", function() {
            updateCounts();
            checkDuplicates();
        });
    }
    
    function updateRowStatus(row) {
        const data = row.getData();
        const hasEmail = data.email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email);
        const hasName = data.name && data.name.trim().length >= 2;
        
        if (!hasEmail) {
            data.status = 'error';
        } else if (!hasName) {
            data.status = 'needs_review';
        } else {
            data.status = 'ready';
        }
        
        row.update(data);
        updateCounts();
    }
    
    function checkDuplicates() {
        if (!parsedTable) return;
        
        const data = parsedTable.getData();
        const emailCounts = {};
        
        // Count email occurrences
        data.forEach(row => {
            if (row.email) {
                const email = row.email.toLowerCase().trim();
                emailCounts[email] = (emailCounts[email] || 0) + 1;
            }
        });
        
        // Update duplicate status
        data.forEach((row, index) => {
            const rowData = parsedTable.getRow(index).getData();
            const email = rowData.email ? rowData.email.toLowerCase().trim() : '';
            
            if (email && emailCounts[email] > 1) {
                rowData.status = 'duplicate';
                parsedTable.getRow(index).update(rowData);
            } else if (rowData.status === 'duplicate' && emailCounts[email] <= 1) {
                // Re-evaluate status if not duplicate anymore
                const hasName = rowData.name && rowData.name.trim().length >= 2;
                rowData.status = hasName ? 'ready' : 'needs_review';
                parsedTable.getRow(index).update(rowData);
            }
        });
        
        updateCounts();
    }
    
    function updateCounts() {
        if (!parsedTable) return;
        
        const data = parsedTable.getData();
        const duplicates = data.filter(row => row.status === 'duplicate').length;
        const needsReview = data.filter(row => row.status === 'needs_review').length;
        const ready = data.filter(row => row.status === 'ready').length;
        
        document.getElementById('parsedCount').textContent = data.length;
        document.getElementById('duplicateCount').textContent = duplicates;
        document.getElementById('reviewCount').textContent = needsReview;
        
        // Update save button text
        const saveBtn = document.getElementById('saveCandidatesBtn');
        if (ready > 0) {
            saveBtn.textContent = `Save ${ready} Candidates`;
            saveBtn.disabled = false;
        } else {
            saveBtn.textContent = 'Save All Candidates';
            saveBtn.disabled = true;
        }
    }
    
    // Upload functionality
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('resumeFiles');
    const browseBtn = document.getElementById('browseBtn');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('progressBar');
    const fileStatus = document.getElementById('fileStatus');
    const fileList = document.getElementById('fileList');
    const selectedFiles = document.getElementById('selectedFiles');
    const fileCount = document.getElementById('fileCount');
    const uploadStep = document.getElementById('uploadStep');
    const reviewStep = document.getElementById('reviewStep');
    const backToUpload = document.getElementById('backToUpload');
    const saveCandidatesBtn = document.getElementById('saveCandidatesBtn');
    
    let files = [];
    
    // Event Listeners
    browseBtn.addEventListener('click', () => fileInput.click());
    
    fileInput.addEventListener('change', (e) => {
        files = Array.from(e.target.files).slice(0, 50); // Limit to 50 files
        updateFileList();
    });
    
    // Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropZone.classList.add('active');
    }
    
    function unhighlight() {
        dropZone.classList.remove('active');
    }
    
    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        files = Array.from(dt.files).slice(0, 50);
        updateFileList();
    });
    
    function updateFileList() {
        fileList.innerHTML = '';
        if (files.length === 0) {
            selectedFiles.style.display = 'none';
            return;
        }
        
        fileCount.textContent = files.length;
        selectedFiles.style.display = 'block';
        
        // Create upload button
        const uploadButtonDiv = document.createElement('div');
        uploadButtonDiv.className = 'text-center mb-4';
        uploadButtonDiv.innerHTML = `
            <button type="button" class="btn btn-success btn-lg px-5 py-3" id="startUploadBtn">
                <i class="bi bi-upload me-2"></i> Upload & Parse ${files.length} Resume${files.length > 1 ? 's' : ''}
            </button>
            <p class="text-muted mt-2 small">Click to start parsing the selected files</p>
        `;
        
        // Create files list
        const filesListDiv = document.createElement('div');
        files.forEach((file, index) => {
            const div = document.createElement('div');
            div.className = 'file-item mb-2';
            div.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-file-earmark-text me-3 fs-5 text-primary"></i>
                    <div class="flex-grow-1">
                        <strong>${file.name}</strong>
                        <div class="small text-muted">${formatBytes(file.size)} • ${file.type || 'Unknown type'}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-file" data-index="${index}" title="Remove file">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `;
            filesListDiv.appendChild(div);
        });
        
        fileList.appendChild(uploadButtonDiv);
        fileList.appendChild(filesListDiv);
        
        // Add remove file functionality
        document.querySelectorAll('.remove-file').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.closest('button').dataset.index);
                files.splice(index, 1);
                updateFileList();
            });
        });
        
        // Add upload button functionality
        const startUploadBtn = document.getElementById('startUploadBtn');
        if (startUploadBtn) {
            startUploadBtn.addEventListener('click', startUpload);
        }
    }
    
    // Start upload function
    async function startUpload() {
        console.log('Starting upload with', files.length, 'files');
        
        if (files.length === 0) {
            showBulkAlert('Please select files first.', 'warning');
            return;
        }
        
        const formData = new FormData();
        files.forEach(file => {
            formData.append('resumes[]', file);
        });
        
        // Show progress
        uploadProgress.classList.remove('d-none');
        browseBtn.disabled = true;
        const startUploadBtn = document.getElementById('startUploadBtn');
        if (startUploadBtn) {
            startUploadBtn.setAttribute('disabled', 'disabled');
            startUploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
        }
        dropZone.style.pointerEvents = 'none';
        progressBar.style.width = '10%';
        progressBar.textContent = '10%';
        fileStatus.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Starting upload...';
        
        try {
            // Simulate progress
            let progress = 10;
            const progressInterval = setInterval(() => {
                if (progress < 80) {
                    progress += 2;
                    progressBar.style.width = progress + '%';
                    progressBar.textContent = progress + '%';
                }
            }, 200);
            
            const response = await fetch('bulk_upload_parse.php', {
                method: 'POST',
                body: formData
            });
            
            clearInterval(progressInterval);
            progressBar.style.width = '90%';
            progressBar.textContent = '90%';
            fileStatus.innerHTML = '<i class="bi bi-check-circle me-1"></i>Processing files...';
            
            const result = await response.json();
            
            if (result.status === 'success') {
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                fileStatus.innerHTML = '<i class="bi bi-check-circle me-1 text-success"></i>Parsing completed!';
                
                // Format data for table
                const tableData = result.data.map((item, index) => ({
                    id: index + 1,
                    rowNumber: index + 1,
                    name: item.parsed_data?.name || '',
                    email: item.parsed_data?.email || '',
                    phone: item.parsed_data?.phone || '',
                    filename: item.filename,
                    saved_path: item.saved_path,
                    raw_content: item.parsed_data?.raw_content || '',
                    status: item.status === 'duplicate' ? 'duplicate' : 
                           (!item.parsed_data?.name || item.parsed_data.name.length < 2) ? 'needs_review' : 
                           (!item.parsed_data?.email || !item.parsed_data.email.includes('@')) ? 'error' : 'ready'
                }));
                
                // Small delay for smooth transition
                setTimeout(() => {
                    // Switch to review step
                    uploadStep.style.display = 'none';
                    reviewStep.style.display = 'block';
                    
                    // Initialize table
                    initParsedTable(tableData);
                    checkDuplicates();
                    
                    // Reset progress
                    setTimeout(() => {
                        uploadProgress.classList.add('d-none');
                        progressBar.style.width = '0%';
                        progressBar.textContent = '0%';
                        fileStatus.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Parsing resumes... This may take up to 30 seconds';
                    }, 1000);
                }, 1000);
                
            } else {
                showBulkAlert('Error: ' + result.message, 'danger');
                resetBulkUpload();
            }
        } catch (error) {
            console.error('Upload error:', error);
            showBulkAlert('Upload failed: ' + error.message, 'danger');
            resetBulkUpload();
        }
    }
    
    // Back to upload
    backToUpload.addEventListener('click', function() {
        reviewStep.style.display = 'none';
        uploadStep.style.display = 'block';
        resetBulkUpload();
    });
    
    // Save candidates
    saveCandidatesBtn.addEventListener('click', async function() {
        const data = parsedTable.getData();
        const validCandidates = data.filter(row => row.status === 'ready');
        
        if (validCandidates.length === 0) {
            showBulkAlert('Please fix all errors before saving. Candidates marked as "Ready" will be saved.', 'warning');
            return;
        }
        
        if (!confirm(`Save ${validCandidates.length} candidates to database?`)) {
            return;
        }
        
        saveCandidatesBtn.disabled = true;
        const originalText = saveCandidatesBtn.innerHTML;
        saveCandidatesBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        
        try {
            const response = await fetch('bulk_upload_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    candidates: validCandidates
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                showBulkAlert(`✅ Successfully saved ${result.saved_count} candidates! Background parsing has started.`, 'success');
                
                // Start background parsing
                if (result.candidate_ids && result.candidate_ids.length > 0) {
                    // Call background parser asynchronously
                    fetch('background_parse.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            candidate_ids: result.candidate_ids
                        })
                    }).catch(err => console.log('Background parse started'));
                }
                
                // Close modal and refresh page after delay
                setTimeout(() => {
                    bulkUploadModal.hide();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }, 2000);
                
            } else {
                showBulkAlert('Error saving candidates: ' + result.message, 'danger');
                saveCandidatesBtn.disabled = false;
                saveCandidatesBtn.innerHTML = originalText;
            }
        } catch (error) {
            showBulkAlert('Save failed: ' + error.message, 'danger');
            saveCandidatesBtn.disabled = false;
            saveCandidatesBtn.innerHTML = originalText;
        }
    });
    
    function resetBulkUpload() {
        files = [];
        fileList.innerHTML = '';
        selectedFiles.style.display = 'none';
        uploadProgress.classList.add('d-none');
        browseBtn.disabled = false;
        dropZone.style.pointerEvents = 'auto';
        uploadStep.style.display = 'block';
        reviewStep.style.display = 'none';
        if (parsedTable) {
            parsedTable.destroy();
            parsedTable = null;
        }
        // Reset file input
        fileInput.value = '';
    }
    
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    // Helper function to show alerts in bulk modal
    function showBulkAlert(message, type) {
        const alertDiv = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>');
        
        $('#bulkUploadModal .modal-body').prepend(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            alertDiv.alert('close');
        }, 5000);
    }
});
</script>

<?php include 'footer.php'; ?>