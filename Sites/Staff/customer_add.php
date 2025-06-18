<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    // Redirect to login page if not logged in or not staff
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../connect.php';

// Initialize variables
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $create_account = isset($_POST['create_account']) && $_POST['create_account'] == '1';
    
    // Validation
    $errors = [];
    
    if (empty($customer_name)) {
        $errors[] = "Customer name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    if ($create_account) {
        if (empty($username)) {
            $errors[] = "Username is required when creating an account.";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required when creating an account.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
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
                throw new Exception("A customer with this email address already exists.");
            }
            
            // Check if username already exists (if creating account)
            if ($create_account) {
                $usernameCheckQuery = "SELECT customer_id FROM customer_auth WHERE username = ?";
                $usernameCheckStmt = $conn->prepare($usernameCheckQuery);
                $usernameCheckStmt->execute([$username]);
                
                if ($usernameCheckStmt->fetch()) {
                    throw new Exception("This username is already taken.");
                }
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
            
            // Create account if requested
            if ($create_account) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insertAuthQuery = "INSERT INTO customer_auth (customer_id, username, password_hash) VALUES (?, ?, ?)";
                $insertAuthStmt = $conn->prepare($insertAuthQuery);
                $insertAuthStmt->execute([$new_customer_id, $username, $password_hash]);
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = "Customer has been successfully created with ID #$new_customer_id!" . ($create_account ? " Login account has also been created." : "");
            
            // Clear form data on success
            $customer_name = '';
            $email = '';
            $phone = '';
            $username = '';
            $password = '';
            $create_account = false;
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Customer add error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        } catch (PDOException $e) {
            // Rollback transaction
            $conn->rollback();
            $error = "Database error occurred. Please try again.";
            error_log("Database error in customer_add: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        }
    }
}

$pageTitle = "Add New Customer - myDonut Staff Panel";
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
        .add-customer-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #007bff;
            text-decoration: none;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            border: 2px solid #007bff;
            border-radius: 25px;
            background-color: transparent;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .back-link:hover {
            background-color: #007bff;
            color: white;
            text-decoration: none;
            transform: translateX(-5px);
        }
        
        .back-link i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }
        
        .back-link:hover i {
            transform: translateX(-3px);
        }
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 0 5px rgba(255, 99, 71, 0.3);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .account-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #ff6347;
        }
        
        .account-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .account-section h3 i {
            margin-right: 0.5rem;
            color: #ff6347;
        }
        
        .account-fields {
            display: none;
            margin-top: 1rem;
        }
        
        .account-fields.show {
            display: block;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        @media (max-width: 768px) {
            .add-customer-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="add-customer-container">
            <a href="customers_list.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Customers List
            </a>
            
            <div class="page-header">
                <h2>Add New Customer</h2>
                <p>Create a new customer record</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="customer-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Customer Name <span class="required">*</span></label>
                            <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($customer_name ?? '') ?>" required>
                            <small>Enter the customer's full name</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number <span class="required">*</span></label>
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" required>
                            <small>Enter phone number (e.g., 555-123-4567)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
                        <small>Enter a valid email address</small>
                    </div>
                    
                    <div class="account-section">
                        <h3><i class="fas fa-user-plus"></i> Create Login Account</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="create_account" name="create_account" value="1" 
                                   <?= (isset($create_account) && $create_account) ? 'checked' : '' ?>>
                            <label for="create_account">Create a login account for this customer</label>
                        </div>
                        <small>Check this option to allow the customer to log in and place orders online</small>
                        
                        <div class="account-fields" id="account-fields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">Username <span class="required">*</span></label>
                                    <input type="text" id="username" name="username" value="<?= htmlspecialchars('') ?>">
                                    <small>Minimum 3 characters, letters and numbers only</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Password <span class="required">*</span></label>
                                    <input type="password" id="password" name="password">
                                    <small>Minimum 6 characters</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="customers_list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Create Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        // Toggle account fields based on checkbox
        document.getElementById('create_account').addEventListener('change', function() {
            const accountFields = document.getElementById('account-fields');
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            if (this.checked) {
                accountFields.classList.add('show');
                usernameField.required = true;
                passwordField.required = true;
            } else {
                accountFields.classList.remove('show');
                usernameField.required = false;
                passwordField.required = false;
                usernameField.value = '';
                passwordField.value = '';
            }
        });
        
        // Initialize form state
        document.addEventListener('DOMContentLoaded', function() {
            const createAccountCheckbox = document.getElementById('create_account');
            if (createAccountCheckbox.checked) {
                document.getElementById('account-fields').classList.add('show');
                document.getElementById('username').required = true;
                document.getElementById('password').required = true;
            }
        });
        
        // Form validation
        document.getElementById('customer-form').addEventListener('submit', function(e) {
            const createAccount = document.getElementById('create_account').checked;
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (createAccount) {
                if (!username || username.length < 3) {
                    alert('Username must be at least 3 characters long.');
                    e.preventDefault();
                    return;
                }
                
                if (!password || password.length < 6) {
                    alert('Password must be at least 6 characters long.');
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
</body>
</html>