<?php
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-browser-chrome me-2"></i>Job Portals</h1>
</div>

<p class="lead">Quick access to external job portals.</p>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <img src="https://placehold.co/150x60?text=LinkedIn+Logo" alt="LinkedIn Logo" class="img-fluid mb-3">
                <h5 class="card-title">LinkedIn Recruiter</h5>
                <p class="card-text">Access your LinkedIn Recruiter account.</p>
                <a href="https://www.linkedin.com/login" target="_blank" class="btn btn-primary">Open Portal</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                 <img src="https://placehold.co/150x60?text=Naukri+Logo" alt="Naukri Logo" class="img-fluid mb-3">
                <h5 class="card-title">Naukri.com</h5>
                <p class="card-text">Access your Naukri employer account.</p>
                <a href="https://www.naukri.com/nlogin/login" target="_blank" class="btn btn-primary">Open Portal</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                 <img src="https://placehold.co/150x60?text=Indeed+Logo" alt="Indeed Logo" class="img-fluid mb-3">
                <h5 class="card-title">Indeed</h5>
                <p class="card-text">Access your Indeed employer account.</p>
                 <a href="https://secure.indeed.com/account/login" target="_blank" class="btn btn-primary">Open Portal</a>
            </div>
        </div>
    </div>
    
    </div>


### To Customize This Page:

1.  **Add Your Logos:**
    * Create a new folder inside your `assets` folder named `logos`.
    * Upload your job portal logo images into `/assets/logos/`.
    * In the code above, change the `<img src="...">` path from `https://placehold.co/...` to your logo's path, for example: `assets/logos/linkedin-logo.png`.

2.  **Update the Links:**
    * Change the `href="..."` in the `<a>` tag for each portal to the direct login page you need.

3.  **Add More Portals:**
    * To add another portal, simply copy and paste one of the `<div class="col-md-4 mb-4">...</div>` blocks and edit its content.

<?php include 'footer.php'; ?>