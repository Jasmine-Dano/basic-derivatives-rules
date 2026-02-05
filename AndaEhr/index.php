<?php
// DEBUG MODE - EXTENDED
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start session
session_start();

// Application settings
define('APP_NAME', 'Anda EHR');
define('APP_VERSION', '1.0.0');
define('SESSION_TIMEOUT', 1800);
define('REMEMBER_ME_DAYS', 30);

// Include database functions
require_once 'db_functions.php';

// DEBUG: Test database connection immediately
error_log("=== DEBUG: index.php loaded ===");
$pdo = getDBConnection();
if ($pdo) {
    error_log("✅ Database connection OK");
    
    // Test if users table exists
    try {
        $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'users')");
        $users_table_exists = $stmt->fetchColumn();
        error_log("📋 Users table exists: " . ($users_table_exists ? 'YES' : 'NO'));
        
        if ($users_table_exists) {
            // Show table structure
            $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position");
            $columns = $stmt->fetchAll();
            error_log("📋 Users table columns:");
            foreach ($columns as $col) {
                error_log("   - " . $col['column_name'] . " (" . $col['data_type'] . ")");
            }
            
            // Count existing users
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $user_count = $stmt->fetchColumn();
            error_log("👥 Total users in database: " . $user_count);
        }
    } catch (Exception $e) {
        error_log("❌ Error checking users table: " . $e->getMessage());
    }
} else {
    error_log("❌ Database connection FAILED");
}

// Check if user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: AndaEhr.php');
    exit();
}

// Initialize error/success messages
$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    error_log("=== DEBUG: Form submitted with action: " . $_POST['action'] . " ===");
    
    if ($_POST['action'] == 'login') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $remember = isset($_POST['remember']) ? true : false;
        
        error_log("🔐 Login attempt for: " . $username);
        
        if (empty($username) || empty($password)) {
            $error = "Username and password are required!";
        } else {
            // Authenticate user from database
            $user = authenticateUser($username, $password);
            
            if ($user) {
                error_log("✅ Login successful for user ID: " . $user['id']);
                
                // Parse full_name for first and last name
                $name_parts = explode(' ', $user['full_name'], 2);
                $first_name = $name_parts[0] ?? '';
                $last_name = $name_parts[1] ?? '';
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_fullname'] = $user['full_name'];
                $_SESSION['user_firstname'] = $first_name;
                $_SESSION['user_lastname'] = $last_name;
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Set remember me cookie if requested
                if ($remember) {
                    setcookie('remember_user', $user['id'], time() + (86400 * REMEMBER_ME_DAYS), "/");
                }
                
                // Update last login
                updateLastLogin($user['id']);
                
                // Redirect to dashboard
                header('Location: AndaEhr.php');
                exit();
            } else {
                $error = "Invalid username or password! Please check your credentials.";
                error_log("❌ Login failed for: " . $username);
            }
        }
    }
    
    // Handle registration
    elseif ($_POST['action'] == 'register') {
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        error_log("📝 Registration attempt:");
        error_log("   First Name: " . $first_name);
        error_log("   Last Name: " . $last_name);
        error_log("   Email: " . $email);
        
        // Basic validation
        if (empty($first_name) || empty($last_name) || empty($password) || empty($confirm_password) || empty($email)) {
            $error = "All fields are required!";
        } 
        elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        }
        elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long!";
        }
        else {
            // Check if email exists
            error_log("🔍 Checking if email exists: " . $email);
            if (emailExists($email)) {
                $error = "Email already registered!";
                error_log("❌ Email already exists: " . $email);
            } else {
                // Generate username from email
                $username = strtok($email, '@');
                $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);
                
                if (empty($username)) {
                    $username = strtolower($first_name . $last_name);
                }
                
                // Make username unique
                $base_username = $username;
                $counter = 1;
                
                error_log("🔍 Checking if username exists: " . $username);
                while (usernameExists($username)) {
                    $username = $base_username . $counter;
                    $counter++;
                    error_log("   Trying alternative username: " . $username);
                }
                
                // Create full name
                $full_name = trim($first_name . ' ' . $last_name);
                
                // Create user using the createUser function
                $user_data = [
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'full_name' => $full_name,
                    'role' => 'user',
                    'is_active' => true
                ];
                
                error_log("🎯 Attempting to create user with data:");
                error_log(print_r($user_data, true));
                
                $user_id = createUser($user_data);
                
                if ($user_id) {
                    error_log("✅ User created successfully with ID: " . $user_id);
                    
                    // Get the created user
                    $new_user = getUserById($user_id);
                    
                    if ($new_user) {
                        error_log("✅ User retrieved from database:");
                        error_log("   ID: " . $new_user['id']);
                        error_log("   Username: " . $new_user['username']);
                        error_log("   Email: " . $new_user['email']);
                        error_log("   Full Name: " . $new_user['full_name']);
                        
                        // Set success message and clear the form
                        $success = "Registration successful! Your username is: <strong>" . htmlspecialchars($username) . "</strong>. Please log in with your credentials.";
                        
                        // Clear the error variable
                        $error = '';
                    } else {
                        $error = "User created but could not be retrieved.";
                        error_log("❌ Could not retrieve newly created user with ID: " . $user_id);
                    }
                } else {
                    $error = "Registration failed. Please try again.";
                    error_log("❌ createUser() returned false - Registration failed");
                    
                    // Check what might be wrong
                    $pdo = getDBConnection();
                    if (!$pdo) {
                        error_log("❌ No database connection available");
                        $error .= " Database connection failed.";
                    } else {
                        // Check last error
                        $error_info = $pdo->errorInfo();
                        if ($error_info && $error_info[0] != '00000') {
                            error_log("❌ PDO Error: " . print_r($error_info, true));
                            $error .= " Database error: " . $error_info[2];
                        }
                    }
                }
            }
        }
    }
}

// Check remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user'])) {
    $remembered_user_id = $_COOKIE['remember_user'];
    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND is_active = true");
            $stmt->execute([':id' => $remembered_user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Parse full_name
                $name_parts = explode(' ', $user['full_name'], 2);
                $first_name = $name_parts[0] ?? '';
                $last_name = $name_parts[1] ?? '';
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_fullname'] = $user['full_name'];
                $_SESSION['user_firstname'] = $first_name;
                $_SESSION['user_lastname'] = $last_name;
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                header('Location: AndaEhr.php');
                exit();
            }
        } catch (PDOException $e) {
            error_log("Remember me error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anda EHR - Electronic Health Records System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Login/Registration Page -->
    <div class="login-container" id="login-container">
        <div class="brand-section">
            <div class="brand-logo">
                <i class="fas fa-heartbeat"></i>
            </div>
            <h1 class="brand-name">Anda <span>EHR</span></h1>
            <p class="brand-tagline">Secure Electronic Health Records System for Modern Healthcare</p>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure & Compliant</h3>
                    <p>HIPAA-compliant data protection</p>
                </div>
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <h3>Smart Analytics</h3>
                    <p>Real-time insights and reporting</p>
                </div>
                <div class="feature">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Fully Responsive</h3>
                    <p>Access from any device</p>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-container">
                <h2 class="form-title">Welcome to Anda EHR</h2>
                
                <!-- Error/Success Messages -->
                <?php if (isset($error) && $error): ?>
                <div class="alert alert-error" style="background: #f44336; color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($success) && $success): ?>
                <div class="alert alert-success" style="background: #4CAF50; color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <!-- DEBUG INFO (visible only during debugging) -->
                <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div style="background: #f0f0f0; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem; font-family: monospace; font-size: 12px;">
                    <h4 style="margin-top: 0;">Debug Info:</h4>
                    <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
                    <p><strong>Users Table:</strong> <?php echo isset($users_table_exists) ? ($users_table_exists ? 'Exists' : 'Missing') : 'Not checked'; ?></p>
                    <p><strong>Function exists:</strong> 
                        createUser: <?php echo function_exists('createUser') ? 'Yes' : 'No'; ?>, 
                        emailExists: <?php echo function_exists('emailExists') ? 'Yes' : 'No'; ?>
                    </p>
                    <p><a href="?test_register=1" style="color: blue;">Test Registration</a> | 
                       <a href="?test_db=1" style="color: blue;">Test Database</a></p>
                </div>
                <?php endif; ?>
                
                <div class="form-toggle">
                    <button class="toggle-btn active" id="login-toggle">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                    <button class="toggle-btn" id="register-toggle">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </div>
                
                <!-- Login Form -->
                <form class="form active" id="login-form" method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="input-group">
                        <label class="input-label" for="login-username">
                            <i class="fas fa-user"></i> Username or Email
                        </label>
                        <input type="text" class="input-field" id="login-username" name="username" placeholder="Enter username or email" required>
                    </div>
                    
                    <div class="input-group">
                        <label class="input-label" for="login-password">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" class="input-field" id="login-password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" id="toggle-login-password">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="input-group" style="display: flex; align-items: center; margin-bottom: 1.5rem;">
                        <input type="checkbox" id="remember" name="remember" style="margin-right: 0.5rem;">
                        <label for="remember" style="color: #666; font-size: 0.9rem;">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Log In
                    </button>
                    
                    <div class="form-link">
                        <p>Don't have an account? <a href="#" id="show-register">Register here</a></p>
                    </div>
                </form>
                
                <!-- Registration Form -->
                <form class="form" id="register-form" method="POST">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="input-group">
                        <label class="input-label" for="first-name">
                            <i class="fas fa-user"></i> First Name
                        </label>
                        <input type="text" class="input-field" id="first-name" name="first_name" placeholder="Enter your first name" required>
                    </div>
                    
                    <div class="input-group">
                        <label class="input-label" for="last-name">
                            <i class="fas fa-user"></i> Last Name
                        </label>
                        <input type="text" class="input-field" id="last-name" name="last_name" placeholder="Enter your last name" required>
                    </div>
                    
                    <div class="input-group">
                        <label class="input-label" for="register-email">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <input type="email" class="input-field" id="register-email" name="email" placeholder="your@email.com" required>
                        <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem;">Enter a valid email address</small>
                    </div>
                    
                    <div class="input-group">
                        <label class="input-label" for="register-password">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" class="input-field" id="register-password" name="password" placeholder="Create a secure password (min. 6 characters)" required>
                            <button type="button" class="toggle-password" id="toggle-register-password">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem;">Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="input-group">
                        <label class="input-label" for="confirm-password">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" class="input-field" id="confirm-password" name="confirm_password" placeholder="Confirm your password" required>
                            <button type="button" class="toggle-password" id="toggle-confirm-password">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem;">Re-enter your password</small>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    
                    <div class="form-link">
                        <p>Already have an account? <a href="#" id="show-login">Sign in here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle between login and register forms
        document.addEventListener('DOMContentLoaded', function() {
            const loginToggle = document.getElementById('login-toggle');
            const registerToggle = document.getElementById('register-toggle');
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            const showRegister = document.getElementById('show-register');
            const showLogin = document.getElementById('show-login');
            
            if (loginToggle && registerToggle) {
                loginToggle.addEventListener('click', function() {
                    loginToggle.classList.add('active');
                    registerToggle.classList.remove('active');
                    loginForm.classList.add('active');
                    registerForm.classList.remove('active');
                });
                
                registerToggle.addEventListener('click', function() {
                    registerToggle.classList.add('active');
                    loginToggle.classList.remove('active');
                    registerForm.classList.add('active');
                    loginForm.classList.remove('active');
                });
                
                if (showRegister) {
                    showRegister.addEventListener('click', function(e) {
                        e.preventDefault();
                        registerToggle.click();
                    });
                }
                
                if (showLogin) {
                    showLogin.addEventListener('click', function(e) {
                        e.preventDefault();
                        loginToggle.click();
                    });
                }
            }
            
            // Toggle password visibility
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');
            togglePasswordButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            });
            
            // Add specific toggle for confirm password
            const toggleConfirmPassword = document.getElementById('toggle-confirm-password');
            if (toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', function() {
                    const input = document.getElementById('confirm-password');
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
            
            // Auto-switch to login form after successful registration
            <?php if (isset($success) && $success): ?>
            // If there's a success message (from registration), show login form
            setTimeout(function() {
                if (loginToggle) {
                    loginToggle.click();
                }
                
                // Clear registration form fields
                const registerForm = document.getElementById('register-form');
                if (registerForm) {
                    registerForm.reset();
                }
            }, 100);
            <?php endif; ?>
        });
    </script>
</body>
</html>