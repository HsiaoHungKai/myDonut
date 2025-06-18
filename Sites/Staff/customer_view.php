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
$customer_id = null;
$customer = null;
$customer_orders = [];
$customer_auth = null;
$error = "";

// Get customer ID from URL parameter
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
} else {
    header("Location: customers_list.php?error=" . urlencode("Invalid customer ID."));
    exit();
}

// Fetch customer information
try {
    $customer_query = "SELECT c.customer_id, c.customer_name, c.email, c.phone
                       FROM customers c
                       WHERE c.customer_id = ?";
    
    $customer_stmt = $conn->prepare($customer_query);
    $customer_stmt->execute([$customer_id]);
    $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header("Location: customers_list.php?error=" . urlencode("Customer not found."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Customer fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving customer information.";
}

// Fetch customer authentication info
try {
    $auth_query = "SELECT username, last_login FROM customer_auth WHERE customer_id = ?";
    $auth_stmt = $conn->prepare($auth_query);
    $auth_stmt->execute([$customer_id]);
    $customer_auth = $auth_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Customer auth fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
}

// Fetch customer orders
try {
    $orders_query = "SELECT o.order_id, o.order_date,
                            COUNT(oi.item_id) as total_items,
                            SUM(oi.quantity * p.list_price) as total_amount
                     FROM orders o
                     LEFT JOIN order_items oi ON o.order_id = oi.order_id
                     LEFT JOIN products p ON oi.product_id = p.product_id
                     WHERE o.customer_id = ?
                     GROUP BY o.order_id, o.order_date
                     ORDER BY o.order_date DESC";
    
    $orders_stmt = $conn->prepare($orders_query);
    $orders_stmt->execute([$customer_id]);
    $customer_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Customer orders fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving customer orders.";
}

// Calculate totals
$total_orders = count($customer_orders);
$total_spent = array_sum(array_column($customer_orders, 'total_amount'));
$total_items = array_sum(array_column($customer_orders, 'total_items'));

$pageTitle = "Customer Details #" . $customer_id . " - myDonut Staff Panel";
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
        .customer-view-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #007bff;
            text-decoration: none;
            margin-bottom: 2rem;
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
        
        .customer-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .customer-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .customer-id-badge {
            background: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .customer-actions {
            display: flex;
            gap: 1rem;
        }
        
        .customer-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #ff6347;
        }
        
        .info-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .info-section h3 i {
            margin-right: 0.5rem;
            color: #ff6347;
        }
        
        .info-item {
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #333;
            font-size: 1.1rem;
        }
        
        .orders-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            color: #333;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: #ff6347;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .orders-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .orders-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .order-id {
            font-weight: bold;
            color: #007bff;
        }
        
        .order-date {
            color: #555;
        }
        
        .order-items {
            font-weight: bold;
            color: #ff6347;
            text-align: center;
        }
        
        .order-total {
            font-weight: bold;
            color: #28a745;
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .action-btn:hover {
            background: #e9ecef;
        }
        
        .view-btn {
            color: #007bff;
        }
        
        .edit-btn {
            color: #28a745;
        }
        
        .customer-summary {
            background: #ff6347;
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-top: 2rem;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }
        
        .summary-item h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-orders {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-orders i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
        
        @media (max-width: 768px) {
            .customer-view-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .customer-header,
            .orders-section {
                padding: 1.5rem;
            }
            
            .customer-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .customer-actions {
                width: 100%;
                justify-content: stretch;
            }
            
            .customer-actions .btn {
                flex: 1;
                text-align: center;
            }
            
            .info-section {
                padding: 1rem;
            }
            
            .orders-table {
                font-size: 0.9rem;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 0.75rem;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .customer-header,
        .orders-section,
        .customer-summary {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .orders-section {
            animation-delay: 0.1s;
        }
        
        .customer-summary {
            animation-delay: 0.2s;
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="customer-view-container">
            <a href="customers_list.php" class="back-link">
                <i class="fas fa-chevron-left"></i> Back to Customers
            </a>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($customer): ?>
                <!-- Customer Header -->
                <div class="customer-header">
                    <div class="customer-title">
                        <div class="customer-id-badge">
                            Customer #<?= $customer['customer_id'] ?>
                        </div>
                        
                        <div class="customer-actions">
                            <a href="customer_edit.php?id=<?= $customer['customer_id'] ?>" class="btn">
                                <i class="fas fa-edit"></i> Edit Customer
                            </a>
                            <button type="button" class="btn btn-secondary" 
                                    onclick="confirmDelete(<?= $customer['customer_id'] ?>, '<?= addslashes(htmlspecialchars($customer['customer_name'])) ?>')">
                                <i class="fas fa-trash"></i> Delete Customer
                            </button>
                        </div>
                    </div>
                    
                    <div class="customer-info-grid">
                        <div class="info-section">
                            <h3><i class="fas fa-user"></i> Customer Information</h3>
                            <div class="info-item">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?= htmlspecialchars($customer['customer_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($customer['email']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?= htmlspecialchars($customer['phone']) ?></span>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h3><i class="fas fa-lock"></i> Account Details</h3>
                            <?php if ($customer_auth): ?>
                                <div class="info-item">
                                    <span class="info-label">Username:</span>
                                    <span class="info-value"><?= htmlspecialchars($customer_auth['username']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Last Login:</span>
                                    <span class="info-value">
                                        <?= $customer_auth['last_login'] ? date('F j, Y g:i A', strtotime($customer_auth['last_login'])) : 'Never' ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="info-item">
                                    <span class="info-value" style="color: #dc3545;">No account created</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Orders -->
                <div class="orders-section">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-cart"></i> Order History
                    </h2>
                    
                    <?php if (count($customer_orders) > 0): ?>
                        <div class="table-container">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Order Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customer_orders as $order): ?>
                                        <tr>
                                            <td class="order-id">#<?= $order['order_id'] ?></td>
                                            <td class="order-date"><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                            <td class="order-items"><?= $order['total_items'] ?></td>
                                            <td class="order-total">$<?= number_format(($order['total_amount'] ?? 0)/100, 2) ?></td>
                                            <td>
                                                <div class="order-actions">
                                                    <a href="order_view.php?id=<?= $order['order_id'] ?>" class="action-btn view-btn">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="order_edit.php?id=<?= $order['order_id'] ?>" class="action-btn edit-btn">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-orders">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>No Orders Found</h3>
                            <p>This customer hasn't placed any orders yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Customer Summary -->
                <div class="customer-summary">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <h4>Total Orders</h4>
                            <p class="summary-value"><?= $total_orders ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Total Spent</h4>
                            <p class="summary-value">$<?= number_format($total_spent/100, 2) ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Items Purchased</h4>
                            <p class="summary-value"><?= $total_items ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        function confirmDelete(customerId, customerName) {
            if (confirm(`Are you sure you want to delete customer #${customerId} "${customerName}"?\n\nThis action cannot be undone and will also delete all associated orders and account data.`)) {
                window.location.href = `customer_delete.php?id=${customerId}`;
            }
        }
    </script>
</body>
</html>