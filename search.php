<?php
include 'header.php';
require_once 'auth.php';

// Check permission
if (!hasPermission('candidates', 'view')) {
    $_SESSION['message'] = "You do not have permission to view this page.";
    $_SESSION['msg_type'] = "danger";
    header("location: dashboard.php");
    exit();
}

// Initialize variables
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_results = [];
$total_records = 0;
$total_pages = 1;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Pagination settings
$results_per_page = 15; // Match candidates.php
$offset = ($current_page - 1) * $results_per_page;

// Validate and sanitize search query
if (!empty($search_query)) {
    // Prevent excessively long queries
    if (strlen($search_query) > 500) {
        $search_query = substr($search_query, 0, 500);
    }
    
    // Escape special characters for safety
    $search_query = htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8');
    
    // Store search in session for history
    if (!isset($_SESSION['search_history'])) {
        $_SESSION['search_history'] = [];
    }
    
    // Perform search with pagination
    // First, get total count
    $count_stmt = $conn->prepare("
        SELECT COUNT(c.id) as total 
        FROM candidates c
        WHERE MATCH(c.resume_text) AGAINST(? IN BOOLEAN MODE)
    ");
    $count_stmt->bind_param("s", $search_query);
    $count_stmt->execute();
    $total_result = $count_stmt->get_result()->fetch_assoc();
    $total_records = $total_result['total'];
    $count_stmt->close();
    
    // Calculate total pages
    $total_pages = ceil($total_records / $results_per_page);
    
    // Ensure current page is valid
    if ($current_page < 1) $current_page = 1;
    if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
    
    // Then get paginated results - matches your candidates.php structure
    $stmt = $conn->prepare("
        SELECT 
            c.id, c.candidate_name, c.email, c.phone, c.current_location,
            c.resume_filename, c.created_at, u.full_name as created_by_name
        FROM candidates c
        LEFT JOIN users u ON c.created_by_id = u.id
        WHERE MATCH(c.resume_text) AGAINST(? IN BOOLEAN MODE)
        ORDER BY c.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $search_query, $results_per_page, $offset);
    $stmt->execute();
    $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Store search in history (after successful search)
    array_unshift($_SESSION['search_history'], [
        'query' => $search_query,
        'time' => date('Y-m-d H:i:s'),
        'results' => $total_records
    ]);
    
    // Keep only last 10 searches
    $_SESSION['search_history'] = array_slice($_SESSION['search_history'], 0, 10);
}

// Fetch active jobs for bulk assign modal (like candidates.php)
$active_jobs_for_modal = $conn->query("
    SELECT j.id, j.job_title, c.client_name 
    FROM job_orders j 
    JOIN clients c ON j.client_id = c.id 
    WHERE j.status = 'Active' 
    ORDER BY c.client_name, j.job_title
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Search - Candidate Management</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        /* Styles for the resume viewer modal - matching candidates.php */
        .resume-modal { 
            display: none; 
            position: fixed; 
            z-index: 2000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
        }
        
        .resume-modal-content { 
            background-color: #fefefe; 
            margin: 2% auto; 
            padding: 20px; 
            border: 1px solid #888; 
            width: 90%; 
            max-width: 1200px; 
            border-radius: 8px; 
            display: flex; 
            flex-direction: column; 
            height: 95%; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .resume-modal-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #dee2e6; 
            padding-bottom: 15px; 
            margin-bottom: 15px;
            flex-shrink: 0;
        }
        
        .resume-body { 
            flex-grow: 1; 
            overflow: hidden; 
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        
        .resume-body iframe, 
        .resume-body embed { 
            width: 100%; 
            height: 100%; 
            border: none; 
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .searching .loading-spinner {
            display: block;
        }
        
        /* Results counter */
        .results-counter {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            border-left: 4px solid #0d6efd;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        /* Search tips */
        .search-tips {
            background: #e7f1ff;
            border: 1px solid #b6d4fe;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .search-tips h6 {
            color: #0a58ca;
            margin-bottom: 10px;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .resume-modal-content {
                margin: 0;
                width: 100%;
                height: 100%;
                border-radius: 0;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
            }
            
            .resume-modal-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .resume-modal-header div:last-child {
                align-self: flex-end;
            }
        }
        
        /* Candidate avatar style - matching candidates.php */
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
        
        /* Meta info style */
        .meta-info { 
            font-size: 0.8rem; 
            color: #6c757d; 
        }
        
        /* Bulk actions bar - matching candidates.php */
        .bulk-actions-bar { 
            display: none; 
            position: fixed; 
            bottom: 20px; 
            left: 50%; 
            transform: translateX(-50%); 
            z-index: 1050; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.2); 
            border-radius: 8px; 
        }
        
        /* Pagination styles */
        .pagination {
            margin-top: 20px;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-search me-2"></i>Resume Search</h1>
            <?php if(count($search_results) > 0 && hasPermission('candidates', 'create')): ?>
                <a href="export_results.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $current_page; ?>" 
                   class="btn btn-success">
                    <i class="bi bi-download me-1"></i> Export Results
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Search Form Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="search.php" method="GET" id="searchForm">
                    <div class="input-group">
                        <input type="text" 
                               name="q" 
                               id="searchInput"
                               class="form-control form-control-lg" 
                               placeholder="Search resume content (e.g., +Java -Manager &quot;Project Lead&quot;)" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               required
                               autocomplete="off">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                    
                    <!-- Search Tips -->
                    <div class="search-tips mt-3">
                        <h6><i class="bi bi-lightbulb me-2"></i>Search Tips:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small>
                                    <i class="bi bi-plus-circle me-1"></i>Use <code>+keyword</code> for required terms<br>
                                    <i class="bi bi-dash-circle me-1"></i>Use <code>-keyword</code> to exclude terms
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small>
                                    <i class="bi bi-quote me-1"></i>Use quotes for exact phrases<br>
                                    <i class="bi bi-asterisk me-1"></i>Use <code>*</code> for wildcard searches
                                </small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Loading Spinner -->
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Searching resumes...</p>
        </div>
        
        <!-- Results Section -->
        <div id="resultsSection" class="card" <?php echo !empty($search_query) ? '' : 'style="display: none;"'; ?>>
            <div class="card-body p-0">
                <form id="bulk-assign-form" action="bulk_assign_to_job.php" method="POST">
                    <!-- Results Header -->
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="results-counter">
                                <h5 class="mb-1">
                                    <?php if(!empty($search_query)): ?>
                                        <?php echo number_format($total_records); ?> result<?php echo $total_records != 1 ? 's' : ''; ?> found for "<?php echo htmlspecialchars($search_query); ?>"
                                    <?php else: ?>
                                        Enter a search term to find candidates
                                    <?php endif; ?>
                                </h5>
                                <?php if($total_records > 0): ?>
                                    <small class="text-muted">
                                        Showing <?php echo min($results_per_page, count($search_results)); ?> of <?php echo number_format($total_records); ?> results
                                        <?php if($total_pages > 1): ?>
                                            (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Bulk select checkbox -->
                            <?php if(count($search_results) > 0 && hasPermission('candidates', 'edit')): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="select-all-candidates">
                                    <label class="form-check-label" for="select-all-candidates">
                                        Select All
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Results List - Matching candidates.php layout -->
                    <div class="list-group list-group-flush">
                        <?php if (count($search_results) > 0): ?>
                            <?php foreach ($search_results as $row): ?>
                                <div class="list-group-item p-3">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <?php if(hasPermission('candidates', 'edit')): ?>
                                                <input class="form-check-input candidate-checkbox" type="checkbox" name="candidate_ids[]" value="<?php echo $row['id']; ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 mb-2 mb-md-0">
                                            <div class="d-flex align-items-center">
                                                <div class="candidate-avatar me-3" style="background-color: #<?php echo substr(md5($row['candidate_name']), 0, 6); ?>;">
                                                    <?php echo strtoupper(substr($row['candidate_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0">
                                                        <a href="candidate_details.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($row['candidate_name']); ?>
                                                        </a>
                                                    </h6>
                                                    <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="text-muted text-decoration-none">
                                                        <?php echo htmlspecialchars($row['email']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2 mb-md-0">
                                            <div class="text-muted">
                                                <i class="bi bi-telephone-fill me-1"></i> <?php echo htmlspecialchars($row['phone']); ?>
                                            </div>
                                            <div class="meta-info mt-1">
                                                <i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($row['current_location']); ?>
                                            </div>
                                            <div class="meta-info mt-1">
                                                <i class="bi bi-person-up me-1"></i> Uploaded by <?php echo htmlspecialchars($row['created_by_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-md-end">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary view-resume-btn" 
                                                        data-candidate-id="<?php echo $row['id']; ?>"
                                                        data-candidate-name="<?php echo htmlspecialchars($row['candidate_name']); ?>">
                                                    <i class="bi bi-file-earmark-text"></i> Resume
                                                </button>
                                                <?php if (hasPermission('candidates', 'edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success assign-job-btn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#assignJobModal" 
                                                            data-candidate-id="<?php echo $row['id']; ?>" 
                                                            data-candidate-name="<?php echo htmlspecialchars($row['candidate_name']); ?>">
                                                        <i class="bi bi-plus-lg"></i> Assign
                                                    </button>
                                                    <a href="edit_candidate.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($search_query)): ?>
                            <div class="list-group-item">
                                <div class="empty-state">
                                    <i class="bi bi-search display-4"></i>
                                    <h4 class="mt-3">No matching candidates found</h4>
                                    <p class="text-muted mb-4">We couldn't find any resumes matching your search criteria.</p>
                                    <div class="row justify-content-center">
                                        <div class="col-md-8">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6>Suggestions:</h6>
                                                    <ul class="list-unstyled mb-0">
                                                        <li><i class="bi bi-check-circle text-success me-2"></i>Check for spelling mistakes</li>
                                                        <li><i class="bi bi-check-circle text-success me-2"></i>Use more general keywords</li>
                                                        <li><i class="bi bi-check-circle text-success me-2"></i>Try different combinations of terms</li>
                                                        <li><i class="bi bi-check-circle text-success me-2"></i>Remove filters or specific requirements</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="list-group-item">
                                <div class="empty-state">
                                    <i class="bi bi-search display-4"></i>
                                    <h4 class="mt-3">Start Your Search</h4>
                                    <p class="text-muted">Enter keywords in the search box above to search through resume content.</p>
                                    <div class="row justify-content-center mt-4">
                                        <div class="col-md-8">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6>Search Examples:</h6>
                                                    <ul class="list-unstyled mb-0">
                                                        <li><i class="bi bi-search me-2"></i><code>"project manager" +Java</code></li>
                                                        <li><i class="bi bi-search me-2"></i><code>+Python -Django</code></li>
                                                        <li><i class="bi bi-search me-2"></i><code>machine learning data scientist</code></li>
                                                        <li><i class="bi bi-search me-2"></i><code>react* +JavaScript</code></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Search results navigation">
                        <ul class="pagination">
                            <!-- First Page -->
                            <?php if($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="search.php?q=<?php echo urlencode($search_query); ?>&page=1">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="bi bi-chevron-double-left"></i></span>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Previous Page -->
                            <?php if($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="search.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $current_page - 1; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php 
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if($start_page > 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif;
                            
                            for($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="search.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor;
                            
                            if($end_page < $total_pages): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            
                            <!-- Next Page -->
                            <?php if($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="search.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $current_page + 1; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Last Page -->
                            <?php if($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="search.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $total_pages; ?>">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="bi bi-chevron-double-right"></i></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resume Viewer Modal -->
    <div id="resumeViewerModal" class="resume-modal">
        <div class="resume-modal-content">
            <div class="resume-modal-header">
                <div>
                    <h3 id="modalCandidateName" class="mb-1"></h3>
                    <small class="text-muted">
                        <i class="bi bi-lightbulb"></i> Tip: Use Ctrl+F (or Cmd+F on Mac) to search within the document
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="modalDownloadBtn" class="btn btn-success" download>
                        <i class="bi bi-download"></i> Download
                    </a>
                    <button type="button" class="btn-close" id="modalCloseBtn"></button>
                </div>
            </div>
            <div id="modalResumeBody" class="resume-body"></div>
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

    <!-- Bulk Actions Bar -->
    <div class="bulk-actions-bar card p-2">
        <div class="d-flex align-items-center">
            <span class="me-3"><strong id="selected-count">0</strong> candidates selected</span>
            <button class="btn btn-success" id="bulk-assign-btn" data-bs-toggle="modal" data-bs-target="#bulkAssignJobModal">
                <i class="bi bi-plus-lg"></i> Bulk Assign to Job
            </button>
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
    $(document).ready(function() {
        const modal = $('#resumeViewerModal');
        const modalName = $('#modalCandidateName');
        const modalBody = $('#modalResumeBody');
        const modalDownloadBtn = $('#modalDownloadBtn');
        const searchInput = $('#searchInput');
        const searchForm = $('#searchForm');
        const resultsSection = $('#resultsSection');
        const loadingSpinner = $('.loading-spinner');
        const baseUrl = '<?php echo BASE_URL; ?>';
        
        // Initialize variables
        let currentSearchTerm = '<?php echo addslashes($search_query); ?>';
        
        // Show/hide results section based on search
        <?php if(!empty($search_query)): ?>
            resultsSection.show();
            loadingSpinner.hide();
        <?php endif; ?>
        
        // Form submission handler
        searchForm.on('submit', function(e) {
            const query = searchInput.val().trim();
            if (query === '') {
                e.preventDefault();
                searchInput.focus();
                return false;
            }
            
            // Show loading state
            resultsSection.hide();
            loadingSpinner.show();
            
            // Store current search term
            currentSearchTerm = query;
            return true;
        });
        
        // View resume button handler - MATCHING candidates.php
        $(document).on('click', '.view-resume-btn', function(e) {
            e.preventDefault();
            const candidateId = $(this).data('candidate-id');
            const candidateName = $(this).data('candidate-name');
            
            modalName.text('Loading Resume...');
            modalBody.html('<div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading resume...</p></div>');
            modal.show();
            
            $.ajax({
                url: 'api_get_resume_content.php', 
                type: 'GET', 
                data: { candidate_id: candidateId }, 
                dataType: 'json',
                success: function(response) {
                    console.log('Resume API response:', response);
                    
                    if (response.status === 'success') {
                        modalName.text(response.name + "'s Resume");
                        
                        // Use local file path instead of full URL
                        const localFilePath = 'uploads/' + response.filename;
                        const fileExtension = response.filename.split('.').pop().toLowerCase();
                        let viewerHtml;

                        if (fileExtension === 'pdf') {
                            viewerHtml = `<iframe src="${localFilePath}" style="width: 100%; height: 100%; border: none;"></iframe>`;
                        } else if (fileExtension === 'doc' || fileExtension === 'docx') {
                            // For Word documents, show download option - matching candidates.php
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
        
        // Modal close handlers
        $('#modalCloseBtn').on('click', function() {
            modal.hide();
            modalBody.html('');
        });
        
        // Close modal when clicking outside
        modal.on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
                modalBody.html('');
            }
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus().select();
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                if (modal.is(':visible')) {
                    modal.hide();
                    modalBody.html('');
                }
            }
            
            // '/' to focus search (when not in input)
            if (e.key === '/' && !$(e.target).is('input, textarea, [contenteditable]')) {
                e.preventDefault();
                searchInput.focus();
            }
        });
        
        // Handler for the "Assign to Job" button - MATCHING candidates.php
        $(document).on('click', '.assign-job-btn', function() {
            $('#assign_candidate_id').val($(this).data('candidate-id'));
            $('#assign_candidate_name').text($(this).data('candidate-name'));
        });

        // Handle assign job form submission with AJAX - MATCHING candidates.php
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
                        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('assignJobModal'));
                        modalInstance.hide();
                        
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
        
        // --- BULK SELECTION FUNCTIONALITY (matching candidates.php) ---
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

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                candidateCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkActionsUI();
            });
        }

        candidateCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsUI);
        });

        if (confirmBulkAssignBtn) {
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
        }
        
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
        
        // Initialize Bootstrap modals
        const assignJobModal = new bootstrap.Modal(document.getElementById('assignJobModal'));
        const bulkAssignJobModal = new bootstrap.Modal(document.getElementById('bulkAssignJobModal'));
        
        // Focus search input on page load if there's a query
        <?php if(!empty($search_query)): ?>
            setTimeout(() => {
                searchInput.focus();
                searchInput.select();
            }, 100);
        <?php endif; ?>
    });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>