<?php
// Only start session if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$displayName = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/style.css">
    <!-- Page title will be set in individual files -->
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo-container">
                <a href="/index.php" style="text-decoration: none; color: inherit;">
                    <h1>myDonut Shop</h1>
                </a>
            </div>
            
            <nav>
                <ul>
                    <?php if ($isLoggedIn): ?>
                        <!-- Navigation for logged in users -->
                        
                        <?php if ($userType == 'staff'): ?>
                            <!-- Staff-only navigation -->
                            <li><span class="user-greeting">Hello, <?= htmlspecialchars($displayName) ?></span></li>
                            <li><a href="/Sites/Staff/panel.php">Panel</a></li>
                            <li><a href="/Sites/logout.php">Logout</a></li>
                        <?php else: ?>
                            <!-- Customer navigation -->
                            <li><span class="user-greeting">Hello, <?= htmlspecialchars($displayName) ?></span></li>
                            <li><a href="/Sites/Cart/cart.php"><i class="fa fa-shopping-cart"></i> Cart</a></li>
                            <li><a href="/Sites/Cart/order.php"><i class="fa fa-shopping-cart"></i> Order</a></li>
                            <li><a href="/Sites/logout.php">Logout</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Navigation for guests -->
                        <li><a href="/Sites/Cart/cart.php"><i class="fa fa-shopping-cart"></i> Cart</a></li>
                        <li><a href="/Sites/register.php">Register</a></li>
                        <li><a href="/Sites/login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <style>
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-container img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        }
        
        .user-greeting {
            display: inline-block;
            font-weight: bold;
            color:rgb(255, 255, 255);
            margin-right: 10px;
        }
        
        /* Additional responsive adjustments */
        @media (max-width: 600px) {
            .header-container {
                flex-direction: column;
                gap: 1rem;
            }
            .logo-container {
                margin-bottom: 0.5rem;
            }
        }
    </style>
    
    <!-- Start content container -->
    <!-- <div class="content"></div> -->