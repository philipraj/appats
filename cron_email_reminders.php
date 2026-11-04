<?php
// This script is designed to be run by a server cron job, not by a user in a browser.
// It will not produce any HTML output.

// Set the correct timezone to ensure date calculations are accurate.
date_default_timezone_set('Asia/Kolkata');

// Include necessary files
require_once 'config.php';
require_once 'send_email.php';

echo "Cron script started at: " . date('Y-m-d H:i:s') . "\n";

// --- 1. Fetch all interviews scheduled for tomorrow ---
$tomorrow_start = date('Y-m-d 00:00:00', strtotime('tomorrow'));
$tomorrow_end = date('Y-m-d 23:59:59', strtotime('tomorrow'));

$sql = "
    SELECT 
        r.full_name as recruiter_name,
        r.email as recruiter_email,
        c.candidate_name,
        c.phone as candidate_phone,
        c.email as candidate_email,
        i.interview_datetime,
        i.interview_round,
        j.job_title
    FROM interviews i
    JOIN submissions s ON i.submission_id = s.id
    JOIN users r ON s.recruiter_id = r.id
    JOIN candidates c ON s.candidate_id = c.id
    JOIN job_orders j ON s.job_id = j.id
    WHERE i.interview_datetime BETWEEN ? AND ?
    ORDER BY r.id, i.interview_datetime
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $tomorrow_start, $tomorrow_end);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($results)) {
    echo "No interviews found for tomorrow. Script finished.\n";
    exit;
}

// --- 2. Group interviews by recruiter ---
$interviews_by_recruiter = [];
foreach ($results as $row) {
    $interviews_by_recruiter[$row['recruiter_email']]['name'] = $row['recruiter_name'];
    $interviews_by_recruiter[$row['recruiter_email']]['interviews'][] = $row;
}

echo count($interviews_by_recruiter) . " recruiter(s) have interviews tomorrow.\n";

// --- 3. Loop through each recruiter and send them a single summary email ---
foreach ($interviews_by_recruiter as $email => $data) {
    $recruiter_name = $data['name'];
    $interviews = $data['interviews'];

    $subject = "Your Interview Schedule for " . date('d-M-Y', strtotime('tomorrow'));
    
    // Build the HTML for the email body
    $email_body = "<h2>Hi " . htmlspecialchars($recruiter_name) . ",</h2>";
    $email_body .= "<p>Here is your interview schedule for tomorrow:</p>";
    $email_body .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>
                        <thead>
                            <tr style='background-color: #f2f2f2;'>
                                <th>Time</th>
                                <th>Candidate Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Job Title</th>
                                <th>Round</th>
                            </tr>
                        </thead>
                        <tbody>";

    foreach ($interviews as $interview) {
        $email_body .= "<tr>
                            <td>" . date('h:i A', strtotime($interview['interview_datetime'])) . "</td>
                            <td>" . htmlspecialchars($interview['candidate_name']) . "</td>
                            <td>" . htmlspecialchars($interview['candidate_phone']) . "</td>
                            <td>" . htmlspecialchars($interview['candidate_email']) . "</td>
                            <td>" . htmlspecialchars($interview['job_title']) . "</td>
                            <td>" . htmlspecialchars($interview['interview_round']) . "</td>
                        </tr>";
    }

    $email_body .= "</tbody></table><p>Best regards,<br>Mount Graph ATS</p>";

    // Send the email using your existing function
    if (sendEmail($email, $subject, $email_body)) {
        echo "Successfully sent email to " . $email . "\n";
    } else {
        echo "Failed to send email to " . $email . "\n";
    }
}

echo "Cron script finished at: " . date('Y-m-d H:i:s') . "\n";
?>