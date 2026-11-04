<?php
require 'config.php';

if (!empty($_POST["client_id"])) {
    $clientId = $_POST['client_id'];
    
    // Fetch contacts based on client ID
    $query = $conn->prepare("SELECT id, contact_name FROM contacts WHERE client_id = ? ORDER BY contact_name");
    $query->bind_param("i", $clientId);
    $query->execute();
    $result = $query->get_result();
    
    // Generate HTML of contacts dropdown options
    if ($result->num_rows > 0) {
        echo '<option value="">-- Select Contact --</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['contact_name']) . '</option>';
        }
    } else {
        echo '<option value="">-- No contacts found --</option>';
    }
}
?>