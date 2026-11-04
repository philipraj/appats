<?php
include 'header.php';

if (!isset($_GET['id'])) {
    header("location: candidates.php");
    exit();
}
$id = $_GET['id'];

// Fetch the candidate's details
$stmt = $conn->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();
if (!$candidate) {
    header("location: candidates.php");
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Edit Candidate</h1>
    <a href="candidates.php" class="btn btn-secondary">Cancel</a>
</div>

<div class="card">
    <div class="card-body">
        <form action="update_candidate_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $candidate['id']; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Candidate Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="candidate_name" value="<?php echo htmlspecialchars($candidate['candidate_name']); ?>" required>
                </div>
                 <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($candidate['email']); ?>" required>
                </div>
            </div>
             <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($candidate['phone']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Current Location</label>
                    <input type="text" class="form-control" name="current_location" value="<?php echo htmlspecialchars($candidate['current_location']); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Current CTC</label>
                    <input type="text" class="form-control" name="current_ctc" value="<?php echo htmlspecialchars($candidate['current_ctc']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Expected CTC</label>
                    <input type="text" class="form-control" name="expected_ctc" value="<?php echo htmlspecialchars($candidate['expected_ctc']); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Notice Period</label>
                    <input type="text" class="form-control" name="notice_period" value="<?php echo htmlspecialchars($candidate['notice_period']); ?>">
                </div>
                 <div class="col-md-6 mb-3">
                    <label class="form-label">Upload New Resume (Optional)</label>
                    <input type="file" class="form-control" name="resume">
                    <?php if(!empty($candidate['resume_filename'])): ?>
                        <small class="form-text text-muted">Current file: <a href="uploads/<?php echo htmlspecialchars($candidate['resume_filename']); ?>" target="_blank">View Resume</a></small>
                    <?php endif; ?>
                    <input type="hidden" name="old_resume" value="<?php echo htmlspecialchars($candidate['resume_filename']); ?>">
                 </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Comments</label>
                <textarea name="comments" class="form-control" rows="4"><?php echo htmlspecialchars($candidate['comments']); ?></textarea>
            </div>
             <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="can_relocate" value="1" id="can_relocate" <?php echo ($candidate['can_relocate'] == 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="can_relocate">Willing to Relocate</label>
            </div>
            <div class="mt-4 text-end">
                <a href="candidates.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_candidate" class="btn btn-primary">Update Candidate</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>