<?php
include 'header.php';
?>

<h1>My Profile</h1>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 text-center">
                <?php if (isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_photo']); ?>" alt="Profile" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-person-circle" style="font-size: 150px;"></i>
                <?php endif; ?>
            </div>
            <div class="col-md-9">
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p class="text-muted"><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></p>
                <hr>
                <h5>Update Profile Photo</h5>
                <form action="upload_profile_photo.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profile_photo_upload" class="form-label">Choose a photo (JPG, PNG, GIF)</label>
                        <input class="form-control" type="file" name="profile_photo" id="profile_photo_upload" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Photo</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>