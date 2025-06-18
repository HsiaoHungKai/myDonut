<?php
// Start session for user authentication
session_start();

// Include database connection
require_once 'connect.php';

// Initialize variables
$customer_name = $email = $phone = $username = $password = $confirm_password = "";
$error = "";
$success = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $customer_name = trim($_POST['customer_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    if (empty($customer_name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Check if email already exists
            $emailCheckQuery = "SELECT customer_id FROM customers WHERE email = ?";
            $emailCheckStmt = $conn->prepare($emailCheckQuery);
            $emailCheckStmt->execute([$email]);
            
            if ($emailCheckStmt->fetch()) {
                throw new Exception("An account with this email address already exists.");
            }
            
            // Check if username already exists
            $usernameCheckQuery = "SELECT customer_id FROM customer_auth WHERE username = ?";
            $usernameCheckStmt = $conn->prepare($usernameCheckQuery);
            $usernameCheckStmt->execute([$username]);
            
            if ($usernameCheckStmt->fetch()) {
                throw new Exception("This username is already taken. Please choose another one.");
            }
            
            // Generate new customer_id
            $customerIdQuery = "SELECT ISNULL(MAX(customer_id), 0) + 1 AS new_customer_id FROM customers";
            $customerIdStmt = $conn->prepare($customerIdQuery);
            $customerIdStmt->execute();
            $new_customer_id = $customerIdStmt->fetch(PDO::FETCH_ASSOC)['new_customer_id'];
            
            // Insert new customer
            $insertCustomerQuery = "INSERT INTO customers (customer_id, customer_name, email, phone) VALUES (?, ?, ?, ?)";
            $insertCustomerStmt = $conn->prepare($insertCustomerQuery);
            $insertCustomerStmt->execute([$new_customer_id, $customer_name, $email, $phone]);
            
            // Create login account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insertAuthQuery = "INSERT INTO customer_auth (customer_id, username, password_hash) VALUES (?, ?, ?)";
            $insertAuthStmt = $conn->prepare($insertAuthQuery);
            $insertAuthStmt->execute([$new_customer_id, $username, $password_hash]);
            
            // Commit transaction
            $conn->commit();
            
            $success = "Account created successfully! You can now log in with your credentials.";
            
            // Clear form data on success
            $customer_name = $email = $phone = $username = $password = $confirm_password = "";
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Registration error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        } catch (PDOException $e) {
            // Rollback transaction
            $conn->rollback();
            $error = "A database error occurred. Please try again.";
            error_log("Database error in register: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        }
    }
}

$pageTitle = "myDonut - Register";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .register-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 0 0 3px rgba(255, 99, 71, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .required {
            color: #dc3545;
        }
        
        .submit-button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #ff6347, #e5573b);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .submit-button:hover {
            background: linear-gradient(135deg, #e5573b, #d44a2e);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 99, 71, 0.3);
        }
        
        .submit-button:active {
            transform: translateY(0);
        }
        
        .error-message {
            color: #dc3545;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }
        
        .success-message {
            color: #155724;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .login-link a {
            color: #ff6347;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .password-requirements h4 {
            margin: 0 0 0.5rem 0;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
        }
        
        .input-icon input {
            padding-right: 3rem;
        }
        
        @media (max-width: 768px) {
            .register-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'header.php'; ?>

    <main class="content">
        <div class="register-container">
            <div class="register-header">
                <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>
                <p>Join myDonut to start shopping for amazing music!</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>
            
            <form id="register-form" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="POST">
                <div class="form-group">
                    <label for="customer_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" 
                           value="<?= htmlspecialchars($customer_name) ?>" required>
                    <small>Enter your first and last name</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($email) ?>" required>
                        <small>We'll use this for order confirmations</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= htmlspecialchars($phone) ?>" required>
                        <small>For order updates and delivery</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" 
                           value="<?= htmlspecialchars($username) ?>" required>
                    <small>3+ characters, letters, numbers, and underscores only</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <div class="input-icon">
                            <input type="password" id="password" name="password" required>
                            <i class="fas fa-eye" id="toggle-password" onclick="togglePassword('password', this)"></i>
                        </div>
                        <small>At least 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <div class="input-icon">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <i class="fas fa-eye" id="toggle-confirm-password" onclick="togglePassword('confirm_password', this)"></i>
                        </div>
                        <small>Re-enter your password</small>
                    </div>
                </div>
                
                <div class="password-requirements">
                    <h4><i class="fas fa-shield-alt"></i> Password Requirements:</h4>
                    <ul>
                        <li>At least 6 characters long</li>
                        <li>Mix of letters and numbers recommended</li>
                        <li>Avoid using personal information</li>
                    </ul>
                </div>
                
                <button type="submit" class="submit-button">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
                
                <div class="login-link">
                    <p>Already have an account? 
                        <a href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Login here
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </main>
    
    <?php include_once 'footer.php'; ?>
    
    <script>
        // Toggle password visibility
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Form validation
        document.getElementById('register-form').addEventListener('submit', function(e) {
            const customerName = document.getElementById('customer_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Basic validation
            if (!customerName) {
                alert('Please enter your full name.');
                e.preventDefault();
                return;
            }
            
            if (!email) {
                alert('Please enter your email address.');
                e.preventDefault();
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                e.preventDefault();
                return;
            }
            
            if (!phone) {
                alert('Please enter your phone number.');
                e.preventDefault();
                return;
            }
            
            if (!username || username.length < 3) {
                alert('Username must be at least 3 characters long.');
                e.preventDefault();
                return;
            }
            
            // Username validation
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            if (!usernameRegex.test(username)) {
                alert('Username can only contain letters, numbers, and underscores.');
                e.preventDefault();
                return;
            }
            
            if (!password || password.length < 6) {
                alert('Password must be at least 6 characters long.');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                e.preventDefault();
                return;
            }
        });
        
        // Real-time password confirmation check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            
            if (username && !usernameRegex.test(username)) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
    </script>
</body>
</html>