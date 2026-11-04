<?php
require 'config.php';
require_once 'auth.php';

if (!hasPermission('job_orders', 'create')) {
    $_SESSION['message'] = "You do not have permission to perform this action.";
    $_SESSION['msg_type'] = "danger";
    header("location: job_orders.php");
    exit();
}

if (isset($_POST['add_job'])) {
    // Validation
    $errors = [];
    if (empty($_POST['client_id'])) { $errors[] = "Client is a required field."; }
    if (empty($_POST['contact_id'])) { $errors[] = "Contact is a required field."; }
    if (empty($_POST['job_title'])) { $errors[] = "Job Title is a required field."; }

    if (!empty($errors)) {
        $_SESSION['message'] = implode('<br>', $errors);
        $_SESSION['msg_type'] = "danger";
        header("location: add_job_order.php");
        exit();
    }
    
    $conn->begin_transaction();
    try {
        // ✅ Replaced HTMLPurifier with simple sanitization
        $job_description = htmlspecialchars($_POST['job_description'], ENT_QUOTES, 'UTF-8');

        $client_id = (int)$_POST['client_id'];
        $contact_id = (int)$_POST['contact_id'];
        $job_title = htmlspecialchars($_POST['job_title'], ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8');
        $job_location_city = htmlspecialchars($_POST['job_location_city'], ENT_QUOTES, 'UTF-8');
        $job_location_state = htmlspecialchars($_POST['job_location_state'], ENT_QUOTES, 'UTF-8');
        $job_type = htmlspecialchars($_POST['job_type'], ENT_QUOTES, 'UTF-8');
        $priority = htmlspecialchars($_POST['priority'] ?? '', ENT_QUOTES, 'UTF-8'); 
        $additional_comments = htmlspecialchars($_POST['additional_comments'], ENT_QUOTES, 'UTF-8');
        $primary_recruiter_id = !empty($_POST['primary_recruiter_id']) ? (int)$_POST['primary_recruiter_id'] : NULL;
        $secondary_recruiters = $_POST['secondary_recruiters'] ?? [];
        $exp_required_min = !empty($_POST['exp_required_min']) ? (int)$_POST['exp_required_min'] : NULL;
        $exp_required_max = !empty($_POST['exp_required_max']) ? (int)$_POST['exp_required_max'] : NULL;
        $budget_min = !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : NULL;
        $budget_max = !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : NULL;
        $is_non_local = isset($_POST['is_non_local']) ? 1 : 0;
        
        // Database Execution
        $sql_job = "INSERT INTO job_orders (
                        client_id, contact_id, job_title, status, job_location_city,
                        job_location_state, job_type, priority, exp_required_min, exp_required_max,
                        budget_min, budget_max, job_description, additional_comments, is_non_local, primary_recruiter_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_job = $conn->prepare($sql_job);
        $stmt_job->bind_param("iissssssiiddssii",
            $client_id, $contact_id, $job_title, $status, $job_location_city,
            $job_location_state, $job_type, $priority, $exp_required_min, $exp_required_max,
            $budget_min, $budget_max, $job_description, $additional_comments, $is_non_local, $primary_recruiter_id
        );
        $stmt_job->execute();
        
        $new_job_id = $conn->insert_id;

        if (!empty($secondary_recruiters)) {
            $sql_assign = "INSERT INTO job_assignments (job_order_id, user_id) VALUES (?, ?)";
            $stmt_assign = $conn->prepare($sql_assign);
            foreach ($secondary_recruiters as $recruiter_id) {
                $user_id = (int)$recruiter_id;
                $stmt_assign->bind_param("ii", $new_job_id, $user_id);
                $stmt_assign->execute();
            }
            $stmt_assign->close();
        }

        $conn->commit();
        $_SESSION['message'] = "Job Order added successfully!";
        $_SESSION['msg_type'] = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "An error occurred. The job order was not saved.";
        $_SESSION['msg_type'] = "danger";
    }

    header("location: job_orders.php");
    exit();
}
?>
