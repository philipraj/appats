<?php
require_once 'config.php';
require_once 'auth.php';

// Security check: Only users with permission to view attendance can download it.
if (!hasPermission('attendance', 'view')) {
    die("Access Denied. You do not have permission to access this feature.");
}

// Get filter parameters from the URL
$from_date = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_GET['to'] ?? date('Y-m-d');
$user_id = $_GET['user'] ?? '';

// --- Build the Database Query ---
$query = "
    SELECT u.full_name, a.check_in_time, a.check_out_time
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE DATE(a.check_in_time) BETWEEN ? AND ?";

if (!empty($user_id)) {
    $query .= " AND a.user_id = ?";
}
$query .= " ORDER BY u.full_name, a.check_in_time ASC";

$stmt = $conn->prepare($query);
if (!empty($user_id)) {
    $stmt->bind_param("ssi", $from_date, $to_date, $user_id);
} else {
    $stmt->bind_param("ss", $from_date, $to_date);
}
$stmt->execute();
$result = $stmt->get_result();

// --- Generate the CSV File ---
$filename = "attendance_report_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add the header row to the CSV
fputcsv($output, ['Employee', 'Date', 'Check-in Time', 'Check-out Time', 'Total Hours (Decimal)']);

// Loop through the results and add them to the CSV
while ($row = $result->fetch_assoc()) {
    $duration_decimal = '';
    if (!empty($row['check_out_time'])) {
        $check_in = new DateTime($row['check_in_time']);
        $check_out = new DateTime($row['check_out_time']);
        $diff_seconds = $check_out->getTimestamp() - $check_in->getTimestamp();
        // Convert duration to a decimal format for payroll (e.g., 8.5 hours)
        $duration_decimal = number_format($diff_seconds / 3600, 2);
    }

    $csv_row = [
        $row['full_name'],
        date('d-m-Y', strtotime($row['check_in_time'])),
        date('h:i A', strtotime($row['check_in_time'])),
        $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : 'N/A',
        $duration_decimal
    ];
    fputcsv($output, $csv_row);
}

fclose($output);
$stmt->close();
exit();
?>