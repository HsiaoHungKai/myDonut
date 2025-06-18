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

// Get staff role for permission checks
$staffRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$displayName = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'Staff Member';

// Initialize stats variables
$totalOrders = 0;
$totalProducts = 0;
$activeCustomers = 0;
$totalRevenue = 0;

// Fetch actual statistics from database
try {
    // Total Orders
    $ordersQuery = "SELECT COUNT(*) as total FROM orders";
    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->execute();
    $totalOrders = $ordersStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Products
    $productsQuery = "SELECT COUNT(*) as total FROM products";
    $productsStmt = $conn->prepare($productsQuery);
    $productsStmt->execute();
    $totalProducts = $productsStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active Customers (customers who have logged in within the last month)
    $customersQuery = "SELECT COUNT(DISTINCT ca.customer_id) as total 
                      FROM customer_auth ca 
                      WHERE ca.last_login >= DATEADD(month, -1, GETDATE())";
    $customersStmt = $conn->prepare($customersQuery);
    $customersStmt->execute();
    $activeCustomers = $customersStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Revenue (sum of all order items)
    $revenueQuery = "SELECT SUM(oi.quantity * p.list_price) as total 
                    FROM order_items oi 
                    INNER JOIN products p ON oi.product_id = p.product_id";
    $revenueStmt = $conn->prepare($revenueQuery);
    $revenueStmt->execute();
    $revenueResult = $revenueStmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = $revenueResult['total'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Stats fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    // Keep default values if there's an error
}

// Set page title
$pageTitle = "Staff Control Panel - myDonut";
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
        .panel-header {
            background: linear-gradient(135deg, #343a40, #212529);
            padding: 2rem;
            border-radius: 15px;
            color: white;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .admin-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .admin-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            border-color: #ff6347;
        }
        
        .admin-card h3 {
            color: #ff6347;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-card i {
            font-size: 2rem;
        }
        
        .admin-card p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.7;
            flex-grow: 1;
        }
        
        .admin-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .stats {
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 4px solid #ff6347;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card h4 {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .stat-card .value {
            color: #ff6347;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .subtitle {
            color: #999;
            font-size: 0.85rem;
        }
        
        /* Different border colors for each stat */
        .stat-card:nth-child(1) { border-left-color: #007bff; }
        .stat-card:nth-child(1) .value { color: #007bff; }
        
        .stat-card:nth-child(2) { border-left-color: #28a745; }
        .stat-card:nth-child(2) .value { color: #28a745; }
        
        .stat-card:nth-child(3) { border-left-color: #ffc107; }
        .stat-card:nth-child(3) .value { color: #ffc107; }
        
        .stat-card:nth-child(4) { border-left-color: #dc3545; }
        .stat-card:nth-child(4) .value { color: #dc3545; }
        
        @media (max-width: 992px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .stats {
                grid-template-columns: 1fr;
            }
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            background: #ff6347;
            color: white;
            font-weight: bold;
            margin-left: 1rem;
            font-size: 0.9rem;
        }
        
        .admin .role-badge {
            background: #dc3545;
        }
        
        .manager .role-badge {
            background: #6f42c1;
        }
        
        .staff .role-badge {
            background: #20c997;
        }
        
        .stats-loading {
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="panel-header">
            <h2>Staff Control Panel</h2>
            <p>Welcome back, <?= htmlspecialchars($displayName) ?>! Manage your store from this centralized dashboard.</p>
        </div>
        
        <div class="container">
            <!-- Quick Stats Section with Real Data -->
            <div class="stats">
                <div class="stat-card">
                    <h4>Total Orders</h4>
                    <div class="value"><?= number_format($totalOrders) ?></div>
                    <div class="subtitle">All time orders</div>
                </div>
                <div class="stat-card">
                    <h4>Products</h4>
                    <div class="value"><?= number_format($totalProducts) ?></div>
                    <div class="subtitle">In inventory</div>
                </div>
                <div class="stat-card">
                    <h4>Active Customers</h4>
                    <div class="value"><?= number_format($activeCustomers) ?></div>
                    <div class="subtitle">Last 30 days</div>
                </div>
                <div class="stat-card">
                    <h4>Total Revenue</h4>
                    <div class="value">$<?= number_format($totalRevenue / 100, 0) ?></div>
                    <div class="subtitle">All time sales</div>
                </div>
            </div>
            
            <div class="admin-dashboard">
                <!-- Products Management -->
                <div class="admin-card">
                    <h3><i class="fas fa-compact-disc"></i> Products</h3>
                    <p>Manage your inventory, add new albums, update prices, and control product visibility.</p>
                    <div class="admin-actions">
                        <a href="products_list.php" class="btn">View All Products</a>
                        <a href="product_add.php" class="btn btn-secondary">Add New Product</a>
                    </div>
                </div>
                
                <!-- Orders Management -->
                <div class="admin-card">
                    <h3><i class="fas fa-shopping-cart"></i> Orders</h3>
                    <p>Process customer orders, track shipments, and manage order statuses.</p>
                    <div class="admin-actions">
                        <a href="orders_list.php" class="btn">View All Orders</a>
                        <a href="order_add.php" class="btn btn-secondary">Add New Order</a>
                    </div>
                </div>
                
                <!-- Customer Management -->
                <div class="admin-card">
                    <h3><i class="fas fa-users"></i> Customers</h3>
                    <p>View customer information, purchase history, and manage customer accounts.</p>
                    <div class="admin-actions">
                        <a href="customers_list.php" class="btn">View All Customers</a>
                        <a href="customer_add.php" class="btn btn-secondary">Add new Customers</a>
                    </div>
                </div>
                
                <!-- Analytics Dashboard -->
                <div class="admin-card">
                    <h3><i class="fas fa-chart-line"></i> Analytics</h3>
                    <p>View sales reports, track performance metrics, and analyze customer behavior.</p>
                    <div class="admin-actions">
                        <a href="dashboard.php" class="btn">View Dashboard</a>
                    </div>
                </div>
                
                <?php if ($staffRole == 'admin' || $staffRole == 'manager'): ?>
                <!-- Staff Management (Admin/Manager Only) -->
                <div class="admin-card">
                    <h3><i class="fas fa-user-tie"></i> Staff Management</h3>
                    <p>Manage staff accounts, assign roles, and track staff activity.</p>
                    <div class="admin-actions">
                        <a href="staffs_list.php" class="btn">View All Staff</a>
                        <a href="staff_add.php" class="btn btn-secondary">Add New Staff</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($staffRole == 'admin'): ?>
                <!-- System Settings (Admin Only) -->
                <!-- <div class="admin-card">
                    <h3><i class="fas fa-cog"></i> Settings</h3>
                    <p>Configure system settings, backup data, and manage application parameters.</p>
                    <div class="admin-actions">
                        <a href="settings.php" class="btn">System Settings</a>
                        <a href="backup.php" class="btn btn-secondary">Backup Database</a>
                    </div>
                </div> -->
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        // Add any needed JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight the current section in navigation
            const currentLocation = window.location.pathname;
            const navLinks = document.querySelectorAll('nav ul li a');
            navLinks.forEach(link => {
                if(link.href.includes(currentLocation)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>