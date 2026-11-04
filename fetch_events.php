<?php
require_once 'config.php';
session_start();

$query = $conn->query("SELECT id, title, start_date, end_date, type FROM events");
$events = [];

while ($row = $query->fetch_assoc()) {
    $color = ($row['type'] == 'Holiday') ? '#dc3545' : '#0d6efd'; // Red for Holidays, Blue for Events
    $events[] = [
        'id'    => $row['id'],
        'title' => $row['title'],
        'start' => $row['start_date'],
        'end'   => $row['end_date'],
        'color' => $color,
        'allDay'=> true // Assumes all events are full-day events
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
?>