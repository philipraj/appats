<?php
// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require the autoloader
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Include the main config file to get the database connection
require 'config.php';

function sendEmail($to, $subject, $htmlBody) {
    global $conn; // Use the global database connection variable from config.php

    // Fetch email settings from the database
    $settings_result = $conn->query("SELECT * FROM app_settings WHERE setting_key LIKE 'smtp_%'");
    $settings = [];
    while($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Check if essential settings are present
    if (empty($settings['smtp_host']) || empty($settings['smtp_user']) || empty($settings['smtp_pass']) || empty($settings['smtp_port'])) {
        // Optional: log an error for the admin
        error_log("SMTP settings are not configured. Email not sent.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'];
        $mail->Password   = $settings['smtp_pass'];
        $mail->SMTPSecure = $settings['smtp_secure'];
        $mail->Port       = $settings['smtp_port'];

        // Recipients
        $mail->setFrom($settings['smtp_user'], 'Mount Graph ATS');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody); // Plain text version for non-HTML clients

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Optional: log the detailed error
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>