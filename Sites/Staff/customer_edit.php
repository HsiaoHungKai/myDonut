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
$customer_id = 0;
$customer = [];
$error = "";
$success = "";

// Check if customer ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
} else {
    // Redirect to customers list if no ID provided
    header("Location: customers_list.php?error=" . urlencode("No customer selected for editing."));
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
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
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Check if email already exists for other customers
            $emailCheckQuery = "SELECT customer_id FROM customers WHERE email = ? AND customer_id != ?";
            $emailCheckStmt = $conn->prepare($emailCheckQuery);
            $emailCheckStmt->execute([$email, $customer_id]);
            
            if ($emailCheckStmt->fetch()) {
                throw new Exception("Email address is already in use by another customer.");
            }
            
            // Check if username already exists for other customers
            $usernameCheckQuery = "SELECT customer_id FROM customer_auth WHERE username = ? AND customer_id != ?";
            $usernameCheckStmt = $conn->prepare($usernameCheckQuery);
            $usernameCheckStmt->execute([$username, $customer_id]);
            
            if ($usernameCheckStmt->fetch()) {
                throw new Exception("Username is already taken by another customer.");
            }
            
            // Update customer information
            $updateCustomerQuery = "UPDATE customers SET customer_name = ?, email = ?, phone = ? WHERE customer_id = ?";
            $updateCustomerStmt = $conn->prepare($updateCustomerQuery);
            $updateCustomerStmt->execute([$customer_name, $email, $phone, $customer_id]);
            
            // Update customer authentication
            if (!empty($password)) {
                // Update with new password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $updateAuthQuery = "UPDATE customer_auth SET username = ?, password_hash = ? WHERE customer_id = ?";
                $updateAuthStmt = $conn->prepare($updateAuthQuery);
                $updateAuthStmt->execute([$username, $password_hash, $customer_id]);
            } else {
                // Update username only
                $updateAuthQuery = "UPDATE customer_auth SET username = ? WHERE customer_id = ?";
                $updateAuthStmt = $conn->prepare($updateAuthQuery);
                $updateAuthStmt->execute([$username, $customer_id]);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to customers list with success message
            header("Location: customers_list.php?status=updated&id=" . $customer_id);
            exit();
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Customer update error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        } catch (PDOException $e) {
            // Rollback transaction
            $conn->rollback();
            $error = "Database error occurred. Please try again.";
            error_log("Database error in customer_edit: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        }
    }
}

// Fetch customer data
try {
    $query = "SELECT c.*, ca.username FROM customers c
              LEFT JOIN customer_auth ca ON c.customer_id = ca.customer_id
              WHERE c.customer_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$customer_id]);
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        // Redirect if customer not found
        header("Location: customers_list.php?error=" . urlencode("Customer not found."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Customer fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Failed to load customer data.";
}

// Set page title
$pageTitle = "Edit Customer - myDonut Staff Panel";
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
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .form-header {
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        
        .customer-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: bold;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: flex;
            gap: 1.5rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        input:focus, select:focus, textarea:focus {
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
        
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 1.5rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .button-group {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="container">
            <div class="form-container">
                <div class="form-header">
                    <h2>Edit Customer #<?= $customer_id ?></h2>
                    <p>Update customer information and account details</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <!-- Customer Information -->
                <div class="customer-info">
                    <div class="info-item">
                        <span class="info-label">Customer ID:</span>
                        <span class="info-value">#<?= $customer['customer_id'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current Name:</span>
                        <span class="info-value"><?= htmlspecialchars($customer['customer_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current Email:</span>
                        <span class="info-value"><?= htmlspecialchars($customer['email']) ?></span>
                    </div>
                </div>
                
                <form method="post" action="<?= $_SERVER['PHP_SELF'] . '?id=' . $customer_id ?>" id="customer-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_name">Customer Name <span class="required">*</span></label>
                            <input type="text" id="customer_name" name="customer_name" 
                                   value="<?= htmlspecialchars($customer['customer_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number <span class="required">*</span></label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($customer['phone']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($customer['email']) ?>" required>
                        <small>Must be unique across all customers</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username <span class="required">*</span></label>
                            <input type="text" id="username" name="username" 
                                   value="<?= htmlspecialchars($customer['username'] ?? '') ?>" required>
                            <small>Must be unique for login</small>
                        </div>
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password">
                            <small>Leave blank to keep current password</small>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <a href="customers_list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Customers
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Update Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        // Form validation
        document.getElementById('customer-form').addEventListener('submit', function(e) {
            const customerName = document.getElementById('customer_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const username = document.getElementById('username').value.trim();
            
            if (!customerName) {
                alert('Please enter the customer name.');
                e.preventDefault();
                return;
            }
            
            if (!email) {
                alert('Please enter an email address.');
                e.preventDefault();
                return;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                e.preventDefault();
                return;
            }
            
            if (!phone) {
                alert('Please enter a phone number.');
                e.preventDefault();
                return;
            }
            
            if (!username) {
                alert('Please enter a username.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>