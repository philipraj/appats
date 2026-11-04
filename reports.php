<?php
require_once 'config.php';
require_once 'auth.php';
include 'header.php';

// --- INITIALIZE FILTERS ---
$from_date = $_POST['from_date'] ?? date('Y-m-01');
$to_date = $_POST['to_date'] ?? date('Y-m-t');
$recruiter_ids = $_POST['recruiter_ids'] ?? [];
$client_ids = $_POST['client_ids'] ?? [];
$contact_ids = $_POST['contact_ids'] ?? [];
$statuses = $_POST['statuses'] ?? [];

// --- FETCH DATA FOR FILTER DROPDOWNS ---
$all_recruiters = $conn->query("SELECT id, full_name FROM users ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$all_clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetch_all(MYSQLI_ASSOC);
$all_statuses = ['Follow Up', 'Not Interested', 'Submitted', 'Submitted to client', 'L1 interview', 'Awaiting Feedback', 'L1 reject', 'L2 interview', 'L2 Reject', 'Customer Interview', 'Customer reject', 'Selected', 'Offered', 'Joined', 'No show'];

// --- BUILD DYNAMIC SQL QUERY ---
$sql = "SELECT 
            s.submitted_at as 'Submission Date',
            r.full_name as 'Recruiter',
            cl.client_name as 'Client',
            co.contact_name as 'Contact',
            c.candidate_name as 'Candidate Name',
            c.phone as 'Mobile',
            c.email as 'Email ID',
            c.current_ctc as 'Current CTC',
            c.expected_ctc as 'Expected CTC',
            c.notice_period as 'Notice',
            s.status as 'Status'
        FROM submissions s 
        JOIN users r ON s.recruiter_id = r.id 
        JOIN candidates c ON s.candidate_id = c.id
        JOIN job_orders j ON s.job_id = j.id 
        JOIN clients cl ON j.client_id = cl.id 
        LEFT JOIN contacts co ON j.contact_id = co.id";

$where_clauses = []; $params = []; $types = '';

// Always exclude 'Sourced' status
$where_clauses[] = "s.status != 'Sourced'";
$where_clauses[] = "s.submitted_at BETWEEN ? AND ?";
$params[] = $from_date . ' 00:00:00'; $types .= 's';
$params[] = $to_date . ' 23:59:59'; $types .= 's';

if (!empty($recruiter_ids)) { $where_clauses[] = "s.recruiter_id IN (" . implode(',', array_fill(0, count($recruiter_ids), '?')) . ")"; $params = array_merge($params, $recruiter_ids); $types .= str_repeat('i', count($recruiter_ids)); }
if (!empty($client_ids)) { $where_clauses[] = "j.client_id IN (" . implode(',', array_fill(0, count($client_ids), '?')) . ")"; $params = array_merge($params, $client_ids); $types .= str_repeat('i', count($client_ids)); }
if (!empty($contact_ids)) { $where_clauses[] = "j.contact_id IN (" . implode(',', array_fill(0, count($contact_ids), '?')) . ")"; $params = array_merge($params, $contact_ids); $types .= str_repeat('i', count($contact_ids)); }
if (!empty($statuses)) { $where_clauses[] = "s.status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ")"; $params = array_merge($params, $statuses); $types .= str_repeat('s', count($statuses)); }

$sql .= " WHERE " . implode(" AND ", $where_clauses);
$sql .= " ORDER BY s.submitted_at DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$detailed_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed Submission Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .report-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .filter-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .result-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-container {
            position: relative;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .fixed-first-column {
            position: sticky;
            left: 0;
            background-color: white;
            z-index: 1;
            box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.3em 0.8em;
            border-radius: 0.25rem;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #3498db !important;
            color: white !important;
            border: 1px solid #3498db !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef !important;
            border: 1px solid #dee2e6 !important;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 0.375rem;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
        }
        .btn-download {
            background-color: #28a745;
            border-color: #28a745;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="report-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1"><i class="bi bi-file-text-fill me-2"></i>Detailed Submission Report</h1>
                    <p class="mb-0">Track and analyze candidate submissions</p>
                </div>
                <a href="#" id="download-report-btn" class="btn btn-download btn-lg">
                    <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Report
                </a>
            </div>
        </div>

        <div class="filter-card card">
            <div class="card-header">Filters</div>
            <div class="card-body">
                <form id="report-filter-form" action="reports.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Recruiter</label>
                            <select name="recruiter_ids[]" class="form-select select2" multiple>
                                <?php foreach($all_recruiters as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo in_array($r['id'], $recruiter_ids) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Client</label>
                            <select name="client_ids[]" id="client_filter" class="form-select select2" multiple>
                                <?php foreach($all_clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo in_array($c['id'], $client_ids) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['client_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Contact</label>
                            <select name="contact_ids[]" id="contact_filter" class="form-select select2" multiple>
                                <option value="">-- Select Client First --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="statuses[]" class="form-select select2" multiple>
                                <?php foreach($all_statuses as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo in_array($s, $statuses) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 text-end mt-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-funnel-fill me-1"></i> Apply Filters
                            </button>
                            <button type="reset" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="result-card card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Report Results</span>
                <span class="badge bg-primary rounded-pill"><?php echo count($detailed_results); ?> records found</span>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table id="reportTable" class="table table-hover mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th class="fixed-first-column">#</th>
                                <?php if (!empty($detailed_results)) { foreach (array_keys($detailed_results[0]) as $header) { 
                                    echo "<th>" . htmlspecialchars(str_replace('_', ' ', $header)) . "</th>"; 
                                } } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($detailed_results)): ?>
                                <tr>
                                    <td colspan="13" class="text-center py-4 text-muted">
                                        No data found for the selected filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($detailed_results as $index => $row): ?>
                                <tr>
                                    <td class="fixed-first-column"><?php echo $index + 1; ?></td>
                                    <?php foreach ($row as $key => $value): ?>
                                        <td>
                                            <?php if ($key === 'Status'): ?>
                                                <span class="status-badge 
                                                    <?php 
                                                        echo strpos($value, 'reject') !== false ? 'bg-danger' : 
                                                        ($value === 'Joined' ? 'bg-success' : 
                                                        ($value === 'Offered' ? 'bg-info' : 
                                                        (strpos($value, 'interview') !== false ? 'bg-warning text-dark' : 'bg-secondary'))); 
                                                    ?>">
                                                    <?php echo htmlspecialchars($value); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($value); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function(){
        // Initialize Select2
        $('.select2').select2({ 
            placeholder: "All", 
            allowClear: true, 
            width: '100%',
            closeOnSelect: false
        });

        // Initialize DataTable with pagination and horizontal scroll
        $('#reportTable').DataTable({
            "paging": true,
            "pageLength": 25,
            "lengthChange": true,
            "lengthMenu": [10, 25, 50, 100],
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "scrollX": true,
            "fixedColumns": {
                left: 1 // Fix the first column (S.No)
            },
            "dom": '<"top"<"d-flex justify-content-between align-items-center"lf>><"clear">rt<"bottom"<"d-flex justify-content-between align-items-center"ip>><"clear">',
            "language": {
                "paginate": {
                    "previous": "<i class='bi bi-chevron-left'></i>",
                    "next": "<i class='bi bi-chevron-right'></i>"
                },
                "search": "_INPUT_",
                "searchPlaceholder": "Search records...",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries"
            }
        });

        // Load contacts when client is selected
        function populateContacts() {
            const clientIds = $('#client_filter').val();
            const contactFilter = $('#contact_filter');
            const previouslySelected = <?php echo json_encode($contact_ids); ?>;
            contactFilter.html('');

            if (clientIds && clientIds.length > 0) {
                $.ajax({
                    type: 'POST', 
                    url: 'api_get_contacts.php', 
                    data: { client_ids: JSON.stringify(clientIds) }, 
                    dataType: 'json',
                    success: function(contacts){
                        if (contacts.length > 0) {
                            contacts.forEach(function(contact) {
                                const isSelected = previouslySelected.includes(contact.id.toString());
                                const newOption = new Option(contact.contact_name, contact.id, false, isSelected);
                                contactFilter.append(newOption);
                            });
                        }
                        contactFilter.trigger('change.select2');
                    }
                });
            } else {
                contactFilter.trigger('change.select2');
            }
        }

        $('#client_filter').on('change', populateContacts);
        populateContacts();

        // Export to Excel
        $('#download-report-btn').on('click', function(e) {
            e.preventDefault();
            const form = $('#report-filter-form');
            const params = new URLSearchParams(new FormData(form[0])).toString();
            const downloadUrl = `export_report.php?${params}`;
            window.location.href = downloadUrl;
        });
    });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>