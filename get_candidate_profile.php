<?php
// get_candidate_profile.php
include 'header.php';
require_once 'auth.php';

if (!hasPermission('candidates', 'view')) {
    die('Access denied');
}

$candidate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($candidate_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, u.full_name as created_by_name 
        FROM candidates c 
        LEFT JOIN users u ON c.created_by_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    
    if ($candidate) {
        ?>
        <div class="row">
            <div class="col-md-3 text-center">
                <div class="candidate-avatar-large mb-3" style="background-color: #<?php echo substr(md5($candidate['candidate_name']), 0, 6); ?>;">
                    <?php echo strtoupper(substr($candidate['candidate_name'], 0, 2)); ?>
                </div>
                <h5><?php echo htmlspecialchars($candidate['candidate_name']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($candidate['current_title']); ?></p>
            </div>
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Contact Information</h6>
                        <p><i class="bi bi-envelope me-2"></i> <?php echo htmlspecialchars($candidate['email']); ?></p>
                        <p><i class="bi bi-telephone me-2"></i> <?php echo htmlspecialchars($candidate['phone']); ?></p>
                        <p><i class="bi bi-geo-alt me-2"></i> <?php echo htmlspecialchars($candidate['current_location']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Professional Details</h6>
                        <p><i class="bi bi-briefcase me-2"></i> <?php echo htmlspecialchars($candidate['current_company']); ?></p>
                        <p><i class="bi bi-cash-stack me-2"></i> <?php echo htmlspecialchars($candidate['current_ctc']); ?></p>
                        <p><i class="bi bi-currency-dollar me-2"></i> <?php echo htmlspecialchars($candidate['expected_ctc']); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($candidate['skills'])): ?>
                <div class="mt-3">
                    <h6 class="text-muted">Skills</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php 
                        $skills = explode(',', $candidate['skills']);
                        foreach ($skills as $skill):
                            $skill = trim($skill);
                            if (!empty($skill)):
                        ?>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($skill); ?></span>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <h6 class="text-muted">Additional Information</h6>
                    <p><i class="bi bi-person me-2"></i> Uploaded by: <?php echo htmlspecialchars($candidate['created_by_name']); ?></p>
                    <p><i class="bi bi-calendar me-2"></i> Uploaded on: <?php echo date('M d, Y', strtotime($candidate['created_at'])); ?></p>
                    <?php if (!empty($candidate['resume_filename'])): ?>
                        <p><i class="bi bi-file-earmark-text me-2"></i> Resume: <?php echo htmlspecialchars($candidate['resume_filename']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($candidate['notes'])): ?>
                <div class="mt-3">
                    <h6 class="text-muted">Notes</h6>
                    <div class="border rounded p-3 bg-light">
                        <?php echo nl2br(htmlspecialchars($candidate['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    } else {
        echo '<div class="text-center py-5"><i class="bi bi-exclamation-triangle fs-1 text-danger"></i><h5 class="mt-3">Candidate Not Found</h5></div>';
    }
} else {
    echo '<div class="text-center py-5"><i class="bi bi-exclamation-triangle fs-1 text-danger"></i><h5 class="mt-3">Invalid Candidate ID</h5></div>';
}
?>