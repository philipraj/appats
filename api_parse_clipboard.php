<?php
// --- Robust CORS Handling ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
// --- End of CORS block ---

header("Content-Type: application/json; charset=UTF-8");
require 'config.php';

$response = ['status' => 'error', 'data' => null, 'message' => 'No text provided.'];

if (isset($_POST['text_block'])) {
    $text = $_POST['text_block'];
    $data = [];
    
    $lines = array_map('trim', explode("\n", $text));
    $lines = array_filter($lines);
    $lines = array_values($lines);

    // --- Final Parsing Logic ---
    $data['name'] = $lines[0] ?? null;

    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
        $data['email'] = trim($matches[0]);
    }
    if (preg_match('/\b\d{10}\b/', $text, $matches)) {
        $data['phone'] = trim($matches[0]);
    }
    
    if (preg_match('/(\d+y\s*\d*m?|\d+y|\d+m)/i', $text, $matches)) {
        $data['totalExperience'] = trim($matches[0]);
    }

    if (preg_match('/₹\s*([\d.]+\s*Lacs)/', $text, $matches)) {
        $data['currentSalary'] = trim($matches[0]);
    }
    
    $location_keywords = ['Bengaluru', 'Hyderabad', 'Chennai', 'Pune', 'Mumbai', 'Remote'];
    foreach($location_keywords as $loc) {
        if(stripos($text, $loc) !== false) {
            $data['currentLocation'] = $loc;
            break;
        }
    }
    
    $notice_period_options = [
        'Currently serving notice period',
        'More than 3 months',
        '0 - 15 days',
        'Immediate',
        '1 month',
        '2 months',
        '3 months',
        'Any'
    ];
    foreach ($lines as $line) {
        foreach ($notice_period_options as $option) {
            if (stripos(trim($line), $option) !== false) {
                $data['noticePeriod'] = trim($line);
                break 2; // Break out of both loops once found
            }
        }
    }
    
    $response = ['status' => 'success', 'data' => $data];
}

echo json_encode($response);
?>