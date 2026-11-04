<?php
include 'header.php';
require_once 'auth.php';

if (!hasPermission('job_orders', 'edit')) {
    // ... permission check ...
    exit();
}
if (!isset($_GET['id'])) {
    header("location: interviews.php");
    exit();
}
$interview_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM interviews WHERE id = ?");
$stmt->bind_param("i", $interview_id);
$stmt->execute();
$interview = $stmt->get_result()->fetch_assoc();
if (!$interview) {
    // ... handle not found ...
    header("location: interviews.php");
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit Interview Schedule</h1>
    <a href="interviews.php" class="btn btn-secondary">Cancel</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit_interview.php" method="POST">
            <input type="hidden" name="interview_id" value="<?php echo $interview['id']; ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Interview Round/Type <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="interview_round" value="<?php echo htmlspecialchars($interview['interview_round']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mode of Interview <span class="text-danger">*</span></label>
                    <select name="interview_mode" class="form-select" required>
                        <option value="Virtual" <?php if($interview['interview_mode'] == 'Virtual') echo 'selected'; ?>>Virtual</option>
                        <option value="Telephonic" <?php if($interview['interview_mode'] == 'Telephonic') echo 'selected'; ?>>Telephonic</option>
                        <option value="In-person" <?php if($interview['interview_mode'] == 'In-person') echo 'selected'; ?>>In-person</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Interview Date & Time <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" name="interview_datetime" value="<?php echo date('Y-m-d\TH:i', strtotime($interview['interview_datetime'])); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Interviewer(s)</label>
                <input type="text" class="form-control" name="interviewers" value="<?php echo htmlspecialchars($interview['interviewers']); ?>">
            </div>
            <div class="mt-4 text-end">
                <button type="submit" name="update_interview" class="btn btn-primary">Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>