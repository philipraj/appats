<?php
require_once 'config.php';
require_once 'auth.php';

if (!isset($_SESSION["user_id"])) {
    if(basename($_SERVER['PHP_SELF']) != 'index.php') {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATS - Mount Graph Technologies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .top-navbar { position: fixed; top: 0; left: 0; right: 0; min-height: 70px; background-color: #212529; z-index: 1030; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; }
        .top-navbar .navbar-brand img { max-height: 40px; }
        .top-navbar .nav-links { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .top-navbar .nav-links a { padding: 8px 12px; text-decoration: none; color: #adb5bd; border-radius: 5px; font-size: 0.9rem; }
        .top-navbar .nav-links a:hover, .top-navbar .nav-links a.active { background-color: #495057; color: white; }
        .main-content { margin-top: 90px; padding: 20px; }
        .profile-dropdown > a.dropdown-toggle { color: #dee2e6; }
        .profile-dropdown > a.dropdown-toggle:hover { color: white; }
        .dropdown-menu .dropdown-item { color: #212529 !important; }
        .profile-image { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    </style>
</head>
<body>

<?php
if (isset($_SESSION["user_id"])):
$current_page = basename($_SERVER['PHP_SELF']);
?>
    <header class="top-navbar">
        <div class="d-flex align-items-center">
            <a class="navbar-brand me-3" href="<?php echo BASE_URL; ?>dashboard.php"><img src="assets/logo.png" alt="Mount Graph Technologies Logo"></a>
            <nav class="nav-links">
                <a class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a>
                <a class="<?php echo ($current_page == 'job_orders.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>job_orders.php">Job Orders</a>
                <a class="<?php echo ($current_page == 'pipeline.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>pipeline.php">Pipeline</a>
                <a class="<?php echo ($current_page == 'candidates.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>candidates.php">Candidates</a>
                <a class="<?php echo ($current_page == 'clients.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>clients.php">Clients</a>
                <a class="<?php echo ($current_page == 'interviews.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>interviews.php">Interviews</a>
                <a class="<?php echo ($current_page == 'offered.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>offered.php">Offered</a>
                <a class="<?php echo ($current_page == 'tracker.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>tracker.php">Tracker</a>
                <a class="<?php echo ($current_page == 'search.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>search.php">Search</a>
                <a class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>reports.php">Reports</a>
            </nav>
        </div>
        <div class="profile-dropdown dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (isset($_SESSION['profile_photo']) && !empty($_SESSION['profile_photo'])): ?><img src="uploads/profiles/<?php echo htmlspecialchars($_SESSION['profile_photo']); ?>" alt="Profile" class="profile-image me-2"><?php else: ?><i class="bi bi-person-circle fs-4 me-2"></i><?php endif; ?>
                <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-end text-small shadow">
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php">My Profile</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>attendance.php">My Attendance & Leave</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>settings.php">Settings</a></li>

                <?php if (hasPermission('attendance', 'view') || hasPermission('permissions', 'view')): ?>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (hasPermission('attendance', 'view')): ?>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>manage_attendance.php">Attendance Management</a></li>
                    <?php endif; ?>
                    <?php if (hasPermission('permissions', 'view')): ?>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>users.php">User Management</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>logout.php">Sign out</a></li>
            </ul>
        </div>
    </header>
<?php endif; ?>
<div class="main-content">