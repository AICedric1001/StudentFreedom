<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    // Redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            // Check user role and redirect accordingly
            if ($result['user']['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: student/dashboard.php');
            }
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Freedom Wall & Complaint System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #B22222;
            --secondary-red: #8B0000;
            --primary-gold: #FFD700;
            --secondary-gold: #DAA520;
            --light-gold: #FFF8DC;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red)) !important;
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover {
            transform: scale(1.05);
        }
        
        .nav-link {
            position: relative;
            transition: color 0.3s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-gold);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .card {
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.8s ease-out;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            color: var(--primary-gold);
            font-size: 2.5rem;
            margin-bottom: 1rem;
            transition: transform 0.3s ease, color 0.3s ease;
        }
        
        .card:hover .feature-icon {
            transform: scale(1.2);
            color: var(--secondary-gold);
        }
        
        .btn-primary {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-red);
            border-color: var(--secondary-red);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 0, 0, 0.3);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red)) !important;
            transition: all 0.3s ease;
        }
        
        .input-group-text {
            background-color: var(--light-gold);
            border-color: var(--primary-gold);
            transition: all 0.3s ease;
        }
        
        .form-control {
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 0.2rem rgba(218, 165, 32, 0.25);
            transform: translateY(-2px);
        }
        
        footer {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red)) !important;
            transition: all 0.3s ease;
        }
        
        /* Animation keyframes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        /* Apply animations to specific elements */
        .display-4 {
            animation: slideInLeft 0.8s ease-out;
        }
        
        .lead {
            animation: slideInLeft 0.8s ease-out 0.2s backwards;
        }
        
        .d-grid {
            animation: slideInLeft 0.8s ease-out 0.4s backwards;
        }
        
        .card.shadow-lg {
            animation: slideInRight 0.8s ease-out;
        }
        
        .feature-icon {
            animation: fadeIn 0.8s ease-out;
        }
        
        /* Staggered animation for feature cards */
        .col-md-6.col-lg-3:nth-child(1) .card {
            animation-delay: 0.1s;
        }
        
        .col-md-6.col-lg-3:nth-child(2) .card {
            animation-delay: 0.2s;
        }
        
        .col-md-6.col-lg-3:nth-child(3) .card {
            animation-delay: 0.3s;
        }
        
        .col-md-6.col-lg-3:nth-child(4) .card {
            animation-delay: 0.4s;
        }
        
        /* Button hover effects */
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        
        .btn:hover::after {
            width: 300px;
            height: 300px;
        }
        
        /* Alert animations */
        .alert {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-comments text-warning me-2"></i>Freedom Wall
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="student/register.php">Register</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php">Admin Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4 fw-bold text-danger mb-4">Your Voice Matters</h1>
                    <p class="lead mb-4">Join our community of students who are making a difference. Share your thoughts, raise concerns, and help shape a better school environment.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="student/register.php" class="btn btn-danger btn-lg px-4 me-md-2">Get Started</a>
                        <a href="#features" class="btn btn-outline-danger btn-lg px-4">Learn More</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-lg">
                        <div class="card-header text-white text-center py-3">
                            <h4 class="mb-0">Student Login</h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-danger"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="name@example.com" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-danger"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                                    <label class="form-check-label" for="rememberMe">Remember me</label>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </button>
                                    <a href="student/forgot-password.php" class="btn btn-link text-danger text-center">Forgot Password?</a>
                                </div>
                            </form>
                            
                           
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="py-5">
        <div class="container">
            <h2 class="text-center text-danger mb-5">Why Choose Freedom Wall?</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-comment-alt feature-icon"></i>
                            <h5 class="card-title">Freedom Wall</h5>
                            <p class="card-text">Share your thoughts and experiences anonymously with your fellow students.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clipboard-list feature-icon"></i>
                            <h5 class="card-title">Complaint System</h5>
                            <p class="card-text">Submit formal complaints and track their resolution status.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line feature-icon"></i>
                            <h5 class="card-title">Track Progress</h5>
                            <p class="card-text">Monitor the status of your complaints and see how they're being addressed.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-shield-alt feature-icon"></i>
                            <h5 class="card-title">Secure & Private</h5>
                            <p class="card-text">Your privacy is our priority. Post anonymously and track complaints securely.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-white py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; 2024 Freedom Wall & Complaint System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html> 