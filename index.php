<?php
session_start();
// If user is already logged in, redirect to dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mount Graph Technologies ATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin: auto; padding: 2rem; margin-top: 50px; background: white; border-radius: 15px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; }
        .logo { max-width: 250px; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="assets/logo.png" alt="Mount Graph Technologies Logo" class="logo">

        <h2 class="text-center mb-4">ATS Portal Login</h2>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <form action="login_process.php" method="post">
            <div class="mb-3 text-start">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3 text-start">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>