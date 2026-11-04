<?php
require_once 'config.php';
require_once 'auth.php';

if (!hasPermission('reports', 'view')) { die("Permission Denied."); }

// --- Get all the filter parameters from the URL ---
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');
$recruiter_ids = isset($_GET['recruiter_ids']) ? explode(',', $_GET['recruiter_ids']) : [];
$client_ids = isset($_GET['client_ids']) ? explode(',', $_GET['client_ids']) : [];
$contact_ids = isset($_GET['contact_ids']) ? explode(',', $_GET['contact_ids']) : [];
$statuses = isset($_GET['statuses']) ? explode(',', $_GET['statuses']) : [];

// --- BUILD THE SAME DYNAMIC SQL QUERY AS THE REPORTS PAGE ---
$sql = "SELECT 
            cl.client_name as 'CLIENT', 
            j.job_title as 'Requirement',
            c.candidate_name as 'CANDIDATE NAME',
            c.phone as 'CONTACT NUMBER',
            c.email as 'EMAIL ID',
            c.current_location as 'CURRENT LOCATION',
            c.current_ctc as 'CURRENT CTC',
            c.expected_ctc as 'EXPECTED CTC',
            c.notice_period as 'NOTICE PERIOD',
            r.full_name as 'RECRUITER',
            s.status as 'STATUS',
            s.submitted_at as 'SUBMITTED DATE',
            c.comments as 'REMARKS'
        FROM submissions s 
        JOIN users r ON s.recruiter_id = r.id 
        JOIN candidates c ON s.candidate_id = c.id
        JOIN job_orders j ON s.job_id = j.id 
        JOIN clients cl ON j.client_id = cl.id 
        LEFT JOIN contacts co ON j.contact_id = co.id";

$where_clauses = []; $params = []; $types = '';

$where_clauses[] = "s.status != 'Sourced'";
$where_clauses[] = "s.submitted_at BETWEEN ? AND ?";
$params[] = $from_date . ' 00:00:00'; $types .= 's';
$params[] = $to_date . ' 23:59:59'; $types .= 's';

if (!empty($recruiter_ids[0])) { $where_clauses[] = "s.recruiter_id IN (" . implode(',', array_fill(0, count($recruiter_ids), '?')) . ")"; $params = array_merge($params, $recruiter_ids); $types .= str_repeat('i', count($recruiter_ids)); }
if (!empty($client_ids[0])) { $where_clauses[] = "j.client_id IN (" . implode(',', array_fill(0, count($client_ids), '?')) . ")"; $params = array_merge($params, $client_ids); $types .= str_repeat('i', count($client_ids)); }
if (!empty($contact_ids[0])) { $where_clauses[] = "j.contact_id IN (" . implode(',', array_fill(0, count($contact_ids), '?')) . ")"; $params = array_merge($params, $contact_ids); $types .= str_repeat('i', count($contact_ids)); }
if (!empty($statuses[0])) { $where_clauses[] = "s.status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ")"; $params = array_merge($params, $statuses); $types .= str_repeat('s', count($statuses)); }

$sql .= " WHERE " . implode(" AND ", $where_clauses);
$sql .= " ORDER BY s.submitted_at DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$results = $stmt->get_result();

// --- GENERATE THE CSV FILE ---
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="ats_report_'.date('Y-m-d').'.csv"');
$output = fopen('php://output', 'w');

// Get headers from the query result
$headers = [];
if ($results->num_rows > 0) {
    $first_row = $results->fetch_assoc();
    $headers = array_keys($first_row);
    // Add S.No to the beginning of the headers
    array_unshift($headers, 'S.No');
    fputcsv($output, $headers);
    // Write the first row back
    array_unshift($first_row, 1);
    fputcsv($output, $first_row);
} else {
    // If no results, just write the headers
    $headers = ['S.No', 'CLIENT', 'Requirement', 'CANDIDATE NAME', 'CONTACT NUMBER', 'EMAIL ID', 'CURRENT LOCATION', 'CURRENT CTC', 'EXPECTED CTC', 'NOTICE PERIOD', 'RECRUITER', 'STATUS', 'SUBMITTED DATE', 'REMARKS'];
    fputcsv($output, $headers);
}

// Add the rest of the data rows
$s_no = 2;
while ($row = $results->fetch_assoc()) {
    array_unshift($row, $s_no++);
    fputcsv($output, $row);
}
fclose($output);
exit();
?>
