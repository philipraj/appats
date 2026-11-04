<?php
require 'config.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("location: dashboard.php");
    exit();
}

if (isset($_POST['save_email_settings'])) {

    $settings_to_update = [
        'smtp_host'   => $_POST['smtp_host'],
        'smtp_user'   => $_POST['smtp_user'],
        'smtp_port'   => $_POST['smtp_port'],
        'smtp_secure' => $_POST['smtp_secure']
    ];

    // Only update password if a new one is entered
    if (!empty($_POST['smtp_pass'])) {
        $settings_to_update['smtp_pass'] = $_POST['smtp_pass'];
    }

    $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($settings_to_update as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    
    $stmt->close();
    $_SESSION['message'] = "Email settings have been saved successfully!";
    $_SESSION['msg_type'] = "success";
}

header("location: settings.php?page=email_config");
exit();
?>