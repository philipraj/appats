<?php
require 'config.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_GET['id'])) {
    header("location: users.php");
    exit();
}

$id_to_delete = $_GET['id'];
$current_user_id = $_SESSION['user_id'];

// Safety check: Do not allow a user to delete themselves
if ($id_to_delete == $current_user_id) {
    $_SESSION['message'] = "You cannot delete your own account.";
    $_SESSION['msg_type'] = "danger";
    header("location: users.php");
    exit();
}

// To maintain data integrity, set owned records to NULL before deleting the user.
// This prevents database errors if foreign key constraints are in place.
$conn->query("UPDATE clients SET owner_id = NULL WHERE owner_id = $id_to_delete");
$conn->query("UPDATE contacts SET owner_id = NULL WHERE owner_id = $id_to_delete");
// Note: We don't re-assign submissions or created candidates, as that's historical data.

// Now, delete the user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id_to_delete);
if ($stmt->execute()) {
    $_SESSION['message'] = "User has been deleted successfully!";
    $_SESSION['msg_type'] = "success";
} else {
    $_SESSION['message'] = "Error: Could not delete user.";
    $_SESSION['msg_type'] = "danger";
}
$stmt->close();

header("location: users.php");
exit();
?>