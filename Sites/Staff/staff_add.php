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
    $staff_name = trim($_POST['staff_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($staff_name)) {
        $errors[] = "Staff name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    if (empty($role)) {
        $errors[] = "Role is required.";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Check if email already exists
            $emailCheckQuery = "SELECT staff_id FROM staffs WHERE email = ?";
            $emailCheckStmt = $conn->prepare($emailCheckQuery);
            $emailCheckStmt->execute([$email]);
            
            if ($emailCheckStmt->fetch()) {
                throw new Exception("A staff member with this email address already exists.");
            }
            
            // Check if username already exists
            $usernameCheckQuery = "SELECT staff_id FROM staff_auth WHERE username = ?";
            $usernameCheckStmt = $conn->prepare($usernameCheckQuery);
            $usernameCheckStmt->execute([$username]);
            
            if ($usernameCheckStmt->fetch()) {
                throw new Exception("This username is already taken.");
            }
            
            // Generate new staff_id
            $staffIdQuery = "SELECT ISNULL(MAX(staff_id), 0) + 1 AS new_staff_id FROM staffs";
            $staffIdStmt = $conn->prepare($staffIdQuery);
            $staffIdStmt->execute();
            $new_staff_id = $staffIdStmt->fetch(PDO::FETCH_ASSOC)['new_staff_id'];
            
            // Insert new staff member (without role - role goes in staff_auth)
            $insertStaffQuery = "INSERT INTO staffs (staff_id, staff_name, email, phone) VALUES (?, ?, ?, ?)";
            $insertStaffStmt = $conn->prepare($insertStaffQuery);
            $insertStaffStmt->execute([$new_staff_id, $staff_name, $email, $phone]);
            
            // Create login account with role
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insertAuthQuery = "INSERT INTO staff_auth (staff_id, username, password_hash, role) VALUES (?, ?, ?, ?)";
            $insertAuthStmt = $conn->prepare($insertAuthQuery);
            $insertAuthStmt->execute([$new_staff_id, $username, $password_hash, $role]);

            // Commit transaction
            $conn->commit();
            
            $success = "Staff member has been successfully created with ID #$new_staff_id! Login account has also been created.";
            
            // Clear form data on success
            $staff_name = '';
            $email = '';
            $phone = '';
            $role = '';
            $username = '';
            $password = '';
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Staff add error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        } catch (PDOException $e) {
            // Rollback transaction
            $conn->rollback();
            $error = "Database error occurred. Please try again.";
            error_log("Database error in staff_add: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        }
    }
}

$pageTitle = "Add New Staff Member - myDonut Staff Panel";
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
        .add-staff-container {
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
        
        .role-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #ff6347;
        }
        
        .role-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .role-section h3 i {
            margin-right: 0.5rem;
            color: #ff6347;
        }
        
        .account-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #007bff;
        }
        
        .account-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .account-section h3 i {
            margin-right: 0.5rem;
            color: #007bff;
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
            .add-staff-container {
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
        <div class="add-staff-container">
            <a href="staffs_list.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Staff List
            </a>
            
            <div class="page-header">
                <h2>Add New Staff Member</h2>
                <p>Create a new staff account with login access</p>
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
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="staff-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="staff_name">Staff Name <span class="required">*</span></label>
                            <input type="text" id="staff_name" name="staff_name" value="<?= htmlspecialchars($staff_name ?? '') ?>" required>
                            <small>Enter the staff member's full name</small>
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
                    
                    <div class="role-section">
                        <h3><i class="fas fa-user-tie"></i> Staff Role</h3>
                        <div class="form-group">
                            <label for="role">Role <span class="required">*</span></label>
                            <select id="role" name="role" required>
                                <option value="">Select a role...</option>
                                <option value="manager" <?= (isset($role) && $role == 'manager') ? 'selected' : '' ?>>Manager</option>
                                <option value="cashier" <?= (isset($role) && $role == 'cashier') ? 'selected' : '' ?>>Cashier</option>
                                <option value="inventory" <?= (isset($role) && $role == 'inventory') ? 'selected' : '' ?>>Inventory Specialist</option>
                                <option value="admin" <?= (isset($role) && $role == 'admin') ? 'selected' : '' ?>>Administrator</option>
                                <option value="staff" <?= (isset($role) && $role == 'staff') ? 'selected' : '' ?>>General Staff</option>
                            </select>
                            <small>Choose the appropriate role for this staff member</small>
                        </div>
                    </div>
                    
                    <div class="account-section">
                        <h3><i class="fas fa-key"></i> Login Account</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username <span class="required">*</span></label>
                                <input type="text" id="username" name="username" value="<?= htmlspecialchars('') ?>" required>
                                <small>Minimum 3 characters, letters and numbers only</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <input type="password" id="password" name="password" required>
                                <small>Minimum 6 characters</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="staffs_list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Create Staff Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        // Form validation
        document.getElementById('staff-form').addEventListener('submit', function(e) {
            const staffName = document.getElementById('staff_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const role = document.getElementById('role').value;
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!staffName) {
                alert('Please enter the staff name.');
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
            
            if (!role) {
                alert('Please select a role.');
                e.preventDefault();
                return;
            }
            
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
        });
    </script>
</body>
</html>