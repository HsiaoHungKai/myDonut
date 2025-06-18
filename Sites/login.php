<?php
// Start session for user authentication
session_start();

// Include database connection
require_once 'connect.php';

// Initialize variables
$username = $password = "";
$error = "";
$user_type = "customer"; // Default to customer login

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check which form was submitted (customer or staff)
    if (isset($_POST['user_type'])) {
        $user_type = $_POST['user_type'];
    }
    
    // Get username and password
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        try {
            // Select appropriate table based on user type
            $table = ($user_type == "staff") ? "staff_auth" : "customer_auth";
            $join_table = ($user_type == "staff") ? "staffs" : "customers";
            $name_field = ($user_type == "staff") ? "staff_name" : "customer_name";
            $id_field = ($user_type == "staff") ? "staff_id" : "customer_id";
            
            // Prepare query to fetch user data with a join to get the name
            $query = "SELECT a.*, u.$name_field as display_name 
                      FROM $table a 
                      JOIN $join_table u ON a.$id_field = u.$id_field 
                      WHERE a.username = :username";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

             // Debugging line to check if user exists
            
            // Check if user exists
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                
                // Use password_verify() to check hashed password
                if (password_verify($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user[$id_field];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['display_name'] = $user['display_name'];
                    $_SESSION['user_type'] = $user_type;
                    
                    // If staff, also store role
                    if ($user_type == "staff") {
                        $_SESSION['role'] = $user['role'];
                    }
                    
                    // Redirect to appropriate dashboard
                    $redirect = ($user_type == "staff") ? "Staff/panel.php" : "../index.php";
                    header("Location: $redirect");
                    exit();
                } else {
                    $error = "Invalid password";
                }
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
            $error = "An error occurred during login. Please try again.";
        }
    }
}

// Set page title
$pageTitle = "myDonut - Login";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .login-container {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .tab-container {
            display: flex;
            margin-bottom: 2rem;
        }
        
        .tab-button {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background-color: #f8f9fa;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .tab-button:first-child {
            border-radius: 5px 0 0 5px;
        }
        
        .tab-button:last-child {
            border-radius: 0 5px 5px 0;
        }
        
        .tab-button.active {
            background-color: #ff6347;
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .submit-button {
            width: 100%;
            padding: 0.75rem;
            background-color: #ff6347;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .submit-button:hover {
            background-color: #e5573b;
        }
        
        .error-message {
            color: #dc3545;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 5px;
            text-align: center;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .register-link a {
            color: #ff6347;
            text-decoration: none;
            font-weight: bold;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include_once 'header.php'; ?>

    <main class="content">
        <div class="login-container">
            <h2 class="text-center mb-3">Login to myDonut</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="tab-container">
                <button id="customer-tab" class="tab-button <?= $user_type == 'customer' ? 'active' : '' ?>" onclick="switchTab('customer')">Customer Login</button>
                <button id="staff-tab" class="tab-button <?= $user_type == 'staff' ? 'active' : '' ?>" onclick="switchTab('staff')">Staff Login</button>
            </div>
            
            <form id="login-form" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="POST">
                <input type="hidden" id="user-type-input" name="user_type" value="<?= $user_type ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="submit-button">Login</button>
                
                <div id="register-link-container" class="register-link">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        function switchTab(tabType) {
            // Update active tab styling
            document.getElementById('customer-tab').classList.toggle('active', tabType === 'customer');
            document.getElementById('staff-tab').classList.toggle('active', tabType === 'staff');
            
            // Update hidden form field value
            document.getElementById('user-type-input').value = tabType;
            
            // Show/hide register link for customers only
            document.getElementById('register-link-container').style.display = 
                (tabType === 'customer') ? 'block' : 'none';
        }
        
        // Initialize tab display
        switchTab('<?= $user_type ?>');
    </script>
</body>
</html>