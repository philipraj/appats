<?php
require_once 'config.php';
require_once 'auth.php'; 

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]); 
    exit();
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if ($client_id > 0) {
    $stmt = $conn->prepare("SELECT id, contact_name FROM contacts WHERE client_id = ? ORDER BY contact_name");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $contacts_raw = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $contacts_formatted = [];
    foreach ($contacts_raw as $contact) {
        $contacts_formatted[] = [
            'value' => $contact['id'],
            'text'  => htmlspecialchars($contact['contact_name'])
        ];
    }
    
    echo json_encode($contacts_formatted);
} else {
    echo json_encode([]);
}
$conn->close();
?>