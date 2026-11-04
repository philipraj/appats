<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!hasPermission('attendance', 'edit')) { // Only users who can edit attendance can manage events
    echo json_encode(['status' => 'error', 'message' => 'Permission Denied']);
    exit();
}

$response = [];

try {
    switch ($_POST['action']) {
        case 'add':
            $stmt = $conn->prepare("INSERT INTO events (title, start_date, end_date, type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $_POST['title'], $_POST['start'], $_POST['end'], $_POST['type']);
            $stmt->execute();
            $response = ['status' => 'success', 'message' => 'Event added successfully.'];
            break;

        case 'update':
            $stmt = $conn->prepare("UPDATE events SET title = ?, start_date = ?, end_date = ?, type = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $_POST['title'], $_POST['start'], $_POST['end'], $_POST['type'], $_POST['id']);
            $stmt->execute();
            $response = ['status' => 'success', 'message' => 'Event updated successfully.'];
            break;

        case 'delete':
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $stmt->bind_param("i", $_POST['id']);
            $stmt->execute();
            $response = ['status' => 'success', 'message' => 'Event deleted successfully.'];
            break;
            
        case 'drag_update': // Handles drag-and-drop
            $stmt = $conn->prepare("UPDATE events SET start_date = ?, end_date = ? WHERE id = ?");
            $stmt->bind_param("ssi", $_POST['start'], $_POST['end'], $_POST['id']);
            $stmt->execute();
            $response = ['status' => 'success', 'message' => 'Event date changed successfully.'];
            break;
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
}

echo json_encode($response);
?>