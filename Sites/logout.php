<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if confirmation was received
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // User confirmed logout, proceed with logout process
    if (isset($_SESSION['user_id'])) {
        // Store username for success message
        $username = $_SESSION['display_name'];
        
        // Clear all session variables
        $_SESSION = array();
        
        // If session cookie is used, destroy it
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Set logout success message
        $message = "You have been successfully logged out.";
    } else {
        // User wasn't logged in
        $message = "You were not logged in.";
    }
    
    // Redirect to home page with appropriate message
    header("Location: /index.php?message=" . urlencode($message));
    exit();
} elseif (!isset($_GET['confirm'])) {
    // User hasn't seen the confirmation page yet, show it
    $pageTitle = "Confirm Logout - myDonut";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .logout-container {
            max-width: 500px;
            margin: 3rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-cancel {
            background-color: #6c757d;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .btn-logout {
            background-color: #dc3545;
        }
        
        .btn-logout:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <?php include_once 'header.php'; ?>
    
    <main class="content">
        <div class="logout-container">
            <h2>Are you sure you want to log out?</h2>
            <p>You will need to log in again to access your account features.</p>
            
            <div class="btn-group">
                <a href="/index.php" class="btn btn-cancel">Cancel</a>
                <a href="/Sites/logout.php?confirm=yes" class="btn btn-logout">Yes, Log Out</a>
            </div>
        </div>
    </main>
    
</body>
</html>
<?php
    exit(); // Stop execution here
} else {
    // Invalid confirm parameter
    header("Location: /index.php");
    exit();
}
?>