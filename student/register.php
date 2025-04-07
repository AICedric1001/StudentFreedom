<?php
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$showOtpModal = false;
$registrationData = [];

// Function to generate OTP
function generateOTP($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Function to send OTP via email
function sendOTPEmail($email, $otp) {
    // Include SMTP configuration
    require_once '../config/smtp_config.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = EMAIL_SUBJECT_PREFIX . 'Email Verification';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #E74C3C;'>Email Verification</h2>
                <p>Thank you for registering with Freedom Wall. Please use the following OTP to verify your email address:</p>
                <div style='background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;'>
                    {$otp}
                </div>
                <p>This OTP will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
                <p>If you did not request this verification, please ignore this email.</p>
                <p>Best regards,<br>Freedom Wall Team</p>
            </div>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Handle OTP verification
if (isset($_POST['verify_otp'])) {
    $submittedOtp = trim($_POST['otp']);
    $storedOtp = $_SESSION['otp'] ?? '';
    $registrationData = $_SESSION['registration_data'] ?? [];
    
    if (empty($submittedOtp)) {
        $error = 'Please enter the OTP code';
    } elseif ($submittedOtp !== $storedOtp) {
        $error = 'Invalid OTP code. Please try again.';
    } elseif (empty($registrationData)) {
        $error = 'Registration data not found. Please try registering again.';
    } else {
        // OTP verified, proceed with registration
        try {
            $result = registerUser(
                $registrationData['student_id'],
                $registrationData['username'],
                $registrationData['first_name'],
                $registrationData['last_name'],
                $registrationData['email'],
                $registrationData['password']
            );
            
            if ($result['success']) {
                $success = $result['message'];
                // Clear session data
                unset($_SESSION['otp']);
                unset($_SESSION['registration_data']);
                // Redirect to login page after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

// Handle initial registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_otp'])) {
    $student_id = trim($_POST['student_id']);
    $username = trim($_POST['username']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']) ? true : false;

    // Validation
    if (empty($student_id) || empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $student_id) && !preg_match('/^\d{2}-\d{4}-\d{4}$/', $student_id)) {
        $error = 'Student ID must be in the format: 00-00-0000 or 00-0000-0000';
    } elseif (!preg_match('/^([a-z]\.)?[a-z]+@csab\.edu\.ph$/', $email)) {
        $error = 'Email must be in the format: studentname@csab.edu.ph or (single letter).studentname@csab.edu.ph';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!$terms) {
        $error = 'You must agree to the Terms and Conditions';
    } else {
        // Generate OTP
        $otp = generateOTP();
        
        // Send OTP via email
        if (sendOTPEmail($email, $otp)) {
            // Store OTP and registration data in session
            $_SESSION['otp'] = $otp;
            $_SESSION['registration_data'] = [
                'student_id' => $student_id,
                'username' => $username,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'password' => $password
            ];
            
            // Show OTP modal
            $showOtpModal = true;
            $success = 'An OTP has been sent to your email. Please verify to complete registration.';
        } else {
            $error = 'Failed to send verification email. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Freedom Wall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #E74C3C;
            --secondary-gold: #D4AF37;
            --light-gold: #FFD700;
            --dark-grey: #2C3E50;
            --light-grey: #ECF0F1;
            --white: #FFFFFF;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-gold));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
        }

        .register-header {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-gold));
            color: var(--white);
            padding: 30px;
            text-align: center;
        }

        .register-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }

        .register-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }

        .register-body {
            padding: 30px;
        }

        .form-floating {
            margin-bottom: 20px;
            position: relative;
        }

        .form-floating input {
            border: 2px solid #eee;
            border-radius: 10px;
            padding: 1rem 0.75rem;
        }

        .form-floating input:focus {
            border-color: var(--primary-red);
            box-shadow: none;
        }

        .form-floating label {
            padding: 1rem 0.75rem;
        }

        .btn-register {
            background: var(--primary-red);
            color: var(--white);
            padding: 12px;
            border-radius: 10px;
            border: none;
            width: 100%;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            background: var(--secondary-gold);
            transform: translateY(-2px);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .login-link a {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            color: var(--secondary-gold);
        }

        .form-check {
            margin: 15px 0;
        }

        .form-check-input:checked {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            z-index: 10;
        }
        
        .form-column {
            padding: 0 15px;
        }
        
        @media (max-width: 768px) {
            .register-container {
                max-width: 100%;
            }
            
            .form-column {
                padding: 0;
            }
        }
        
        /* OTP Modal Styles */
        .otp-input {
            letter-spacing: 8px;
            font-size: 24px;
            text-align: center;
            font-weight: bold;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-gold));
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .btn-verify {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-verify:hover {
            background: var(--secondary-gold);
            transform: translateY(-2px);
        }
        
        .resend-link {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .resend-link:hover {
            color: var(--secondary-gold);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Freedom Wall</h1>
            <p>Create your account to get started.</p>
        </div>
        <div class="register-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registrationForm">
                <div class="row">
                    <div class="col-md-6 form-column">
                        <h4 class="mb-3">Personal Information</h4>
                <div class="form-floating">
                    <input type="text" class="form-control" id="student_id" name="student_id" 
                                   placeholder="Student ID" pattern="\d{2}-\d{2}-\d{4}|\d{2}-\d{4}-\d{4}" 
                                   title="Please enter a valid Student ID in the format: 00-00-0000 or 00-0000-0000" required>
                    <label for="student_id">Student ID</label>
                            <div class="invalid-feedback">Please enter a valid Student ID in the format: 00-00-0000 or 00-0000-0000</div>
                            <div class="form-text text-muted">Please enter a valid Student ID in the format (e.g. 00-00-0000 or 00-0000-0000)</div>
                </div>
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <label for="username">Username</label>
                            <div class="form-text text-muted">Please do not use your real name as your username. If you do, it's your responsibility.</div>
                </div>
                <div class="form-floating">
                    <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required>
                    <label for="first_name">First Name</label>
                </div>
                <div class="form-floating">
                    <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" required>
                    <label for="last_name">Last Name</label>
                </div>
                    </div>
                    
                    <div class="col-md-6 form-column">
                        <h4 class="mb-3">Account Information</h4>
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="studentname@csab.edu.ph or j.studentname@csab.edu.ph" pattern="([a-z]\.)?[a-z]+@csab\.edu\.ph"
                                   title="Please enter a valid email in the format: studentname@csab.edu.ph or (single letter).studentname@csab.edu.ph" required>
                    <label for="email">Email address</label>
                            <div class="invalid-feedback">Please enter a valid email in the format: studentname@csab.edu.ph or (single letter).studentname@csab.edu.ph</div>
                            <div class="form-text text-muted">Please use your school email address in the format: studentname@csab.edu.ph or j.studentname@csab.edu.ph</div>
                </div>
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                            <div class="form-text text-muted">Password must be at least 8 characters long</div>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                </div>
                <div class="form-floating">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                    <label for="confirm_password">Confirm Password</label>
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
                    </label>
                </div>
                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>Register
                </button>
                        <div class="login-link">
                            Already have an account? <a href="login.php">Login here</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- OTP Verification Modal -->
    <div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="otpModalLabel">Email Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please enter the 6-digit verification code sent to your email address.</p>
                    <form method="POST" action="" id="otpForm">
                        <div class="mb-3">
                            <input type="text" class="form-control otp-input" id="otp" name="otp" maxlength="6" placeholder="000000" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="verify_otp" class="btn btn-verify">
                                <i class="fas fa-check-circle me-2"></i>Verify
                            </button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <p>Didn't receive the code? <span class="resend-link" id="resendOtp">Resend</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                // Custom validation for email format
                const email = document.getElementById('email').value;
                const emailPattern = /^([a-z]\.)?[a-z]+@csab\.edu\.ph$/;
                
                if (!emailPattern.test(email)) {
                    event.preventDefault();
                    document.getElementById('email').setCustomValidity('Email must be in the format: studentname@csab.edu.ph or (single letter).studentname@csab.edu.ph');
                } else {
                    document.getElementById('email').setCustomValidity('');
                }
                
                // Custom validation for student ID format
                const studentId = document.getElementById('student_id').value;
                const idPattern1 = /^\d{2}-\d{2}-\d{4}$/;
                const idPattern2 = /^\d{2}-\d{4}-\d{4}$/;
                
                if (!idPattern1.test(studentId) && !idPattern2.test(studentId)) {
                    event.preventDefault();
                    document.getElementById('student_id').setCustomValidity('Student ID must be in the format: 00-00-0000 or 00-0000-0000');
                } else {
                    document.getElementById('student_id').setCustomValidity('');
                }
                
                // Password match validation
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    event.preventDefault();
                    document.getElementById('confirm_password').setCustomValidity('Passwords do not match');
                } else {
                    document.getElementById('confirm_password').setCustomValidity('');
                }
                
                form.classList.add('was-validated');
            });
            
            // Real-time validation for email format
            document.getElementById('email').addEventListener('input', function() {
                const email = this.value;
                const emailPattern = /^([a-z]\.)?[a-z]+@csab\.edu\.ph$/;
                
                if (!emailPattern.test(email)) {
                    this.setCustomValidity('Email must be in the format: studentname@csab.edu.ph or (single letter).studentname@csab.edu.ph');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Real-time validation for student ID format
            document.getElementById('student_id').addEventListener('input', function() {
                const studentId = this.value;
                const idPattern1 = /^\d{2}-\d{2}-\d{4}$/;
                const idPattern2 = /^\d{2}-\d{4}-\d{4}$/;
                
                if (!idPattern1.test(studentId) && !idPattern2.test(studentId)) {
                    this.setCustomValidity('Student ID must be in the format: 00-00-0000 or 00-0000-0000');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Real-time password match validation
            document.getElementById('confirm_password').addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirmPassword = this.value;
                
                if (password !== confirmPassword) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            togglePassword.addEventListener('click', function() {
                // Toggle the type attribute
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                // Toggle the eye icon
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            toggleConfirmPassword.addEventListener('click', function() {
                // Toggle the type attribute
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                
                // Toggle the eye icon
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            // OTP Modal
            <?php if ($showOtpModal): ?>
            const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
            otpModal.show();
            <?php endif; ?>
            
            // OTP input formatting
            const otpInput = document.getElementById('otp');
            if (otpInput) {
                otpInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            // Resend OTP functionality
            const resendLink = document.getElementById('resendOtp');
            if (resendLink) {
                resendLink.addEventListener('click', function() {
                    // Here you would typically make an AJAX call to resend the OTP
                    alert('A new OTP has been sent to your email.');
                });
            }
        });
    </script>
</body>
</html> 