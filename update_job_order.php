<?php
require 'config.php';
require 'send_email.php'; // Include our email function

if (isset($_POST['update_job'])) {
    // --- Get all form data ---
    $id = $_POST['id'];
    $client_id = $_POST['client_id'];
    $contact_id = $_POST['contact_id'];
    $primary_recruiter_id = !empty($_POST['primary_recruiter_id']) ? $_POST['primary_recruiter_id'] : NULL;
    $secondary_recruiters = isset($_POST['secondary_recruiters']) ? $_POST['secondary_recruiters'] : [];
    $job_title = $_POST['job_title'];
    $status = $_POST['status'];
    $job_location_city = $_POST['job_location_city'];
    $job_location_state = $_POST['job_location_state'];
    $job_type = $_POST['job_type'];
    $exp_required_min = !empty($_POST['exp_required_min']) ? $_POST['exp_required_min'] : NULL;
    $exp_required_max = !empty($_POST['exp_required_max']) ? $_POST['exp_required_max'] : NULL;
    $budget_min = !empty($_POST['budget_min']) ? $_POST['budget_min'] : NULL;
    $budget_max = !empty($_POST['budget_max']) ? $_POST['budget_max'] : NULL;
    $job_description = $_POST['job_description'];
    $additional_comments = $_POST['additional_comments'];
    $is_non_local = isset($_POST['is_non_local']) ? 1 : 0;

    if (empty($client_id) || empty($contact_id) || empty($job_title)) {
        $_SESSION['message'] = "Client, Contact, and Job Title are required.";
        $_SESSION['msg_type'] = "danger";
    } else {
        // --- Update the main job_orders table ---
        $stmt = $conn->prepare("UPDATE job_orders SET client_id = ?, contact_id = ?, primary_recruiter_id = ?, job_title = ?, status = ?, job_location_city = ?, job_location_state = ?, job_type = ?, exp_required_min = ?, exp_required_max = ?, budget_min = ?, budget_max = ?, job_description = ?, additional_comments = ?, is_non_local = ? WHERE id = ?");
        $stmt->bind_param("iiisssssiiddssii", $client_id, $contact_id, $primary_recruiter_id, $job_title, $status, $job_location_city, $job_location_state, $job_type, $exp_required_min, $exp_required_max, $budget_min, $budget_max, $job_description, $additional_comments, $is_non_local, $id);

        if ($stmt->execute()) {
            // --- Update secondary recruiter assignments ---
            $delete_stmt = $conn->prepare("DELETE FROM job_assignments WHERE job_order_id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            if (!empty($secondary_recruiters)) {
                $assign_stmt = $conn->prepare("INSERT INTO job_assignments (job_order_id, user_id) VALUES (?, ?)");
                foreach ($secondary_recruiters as $user_id) {
                    $assign_stmt->bind_param("ii", $id, $user_id);
                    $assign_stmt->execute();
                }
                $assign_stmt->close();
            }
            $_SESSION['message'] = "Job Order has been updated successfully!";
            $_SESSION['msg_type'] = "success";

            // --- NEW: Send Email Notifications ---
            $all_assigned_ids = array_unique(array_merge([$primary_recruiter_id], $secondary_recruiters));
            if (!empty($all_assigned_ids)) {
                $id_placeholders = implode(',', array_fill(0, count($all_assigned_ids), '?'));
                $user_stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id IN ($id_placeholders)");
                $user_stmt->bind_param(str_repeat('i', count($all_assigned_ids)), ...$all_assigned_ids);
                $user_stmt->execute();
                $recipients = $user_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $job_link = BASE_URL . "view_job.php?id=" . $id;
                $subject = "Update on your assigned Job Order: " . htmlspecialchars($job_title);
                
                foreach ($recipients as $recipient) {
                    $email_body = "
                        <h2>Job Assignment Update</h2>
                        <p>Hello " . htmlspecialchars($recipient['full_name']) . ",</p>
                        <p>The details or assignments for a job order you are assigned to have been updated.</p>
                        <ul>
                            <li><strong>Job Title:</strong> " . htmlspecialchars($job_title) . "</li>
                            <li><strong>View Details:</strong> <a href='" . $job_link . "'>" . $job_link . "</a></li>
                        </ul>
                    ";
                    sendEmail($recipient['email'], $subject, $email_body);
                }
            }

        } else {
            $_SESSION['message'] = "Error: Could not update job order. " . $stmt->error;
            $_SESSION['msg_type'] = "danger";
        }
        $stmt->close();
    }
    header("location: job_orders.php");
    exit();
}
?>