<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// This helper function must be here to generate the response
function render_status_badge($status) {
    $badge_class = 'bg-secondary';
    switch ($status) {
        case 'Active': $badge_class = 'bg-success'; break;
        case 'Hold': $badge_class = 'bg-warning text-dark'; break;
        case 'Closed': $badge_class = 'bg-danger'; break;
    }
    return "<span class='badge " . $badge_class . "'>" . htmlspecialchars($status) . "</span>";
}

// Check if session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!hasPermission('job_orders', 'edit')) {
    echo json_encode(['status' => 'error', 'message' => 'Permission Denied.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id']) && isset($_POST['status'])) {
    $job_id = (int)$_POST['job_id'];
    $status = $_POST['status'];
    $allowed_statuses = ['Active', 'Hold', 'Closed'];

    if (in_array($status, $allowed_statuses) && $job_id > 0) {
        $stmt = $conn->prepare("UPDATE job_orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $job_id);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'badge_html' => render_status_badge($status)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$conn->close();
?>