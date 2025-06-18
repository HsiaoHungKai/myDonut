<?php
session_start();

// Include database connection
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Include the header for styling
    include_once '../header.php';
    
    echo '<main>';
    echo '<div class="form-section text-center">';
    echo '<h2>Please log in to view your orders</h2>';
    echo '<p>You need to be logged in to access your order history.</p>';
    echo '<a href="../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']) . '" class="btn">Login</a>';
    echo '</div>';
    echo '</main>';
    
    echo '<footer>';
    echo '<p>&copy; 2025 myDonut Music Store. All rights reserved.</p>';
    echo '</footer>';
    echo '</body>';
    echo '</html>';
    exit();
}

$customer_id = $_SESSION['user_id'];
$orders = [];
$error = '';

try {
    // Get all orders for this customer with order details
    $ordersQuery = "SELECT o.order_id, o.order_date,
                           COUNT(oi.item_id) as total_items,
                           SUM(oi.quantity * p.list_price) as order_total
                    FROM orders o
                    LEFT JOIN order_items oi ON o.order_id = oi.order_id
                    LEFT JOIN products p ON oi.product_id = p.product_id
                    WHERE o.customer_id = ?
                    GROUP BY o.order_id, o.order_date
                    ORDER BY o.order_date DESC";
    
    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->execute([$customer_id]);
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Orders query error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Unable to retrieve your order history at this time.";
}

// Handle order details request
$orderDetails = [];
$selectedOrderId = null;
if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $selectedOrderId = (int)$_GET['order_id'];
    
    try {
        // Verify this order belongs to the logged-in customer
        $verifyQuery = "SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ?";
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->execute([$selectedOrderId, $customer_id]);
        
        if ($verifyStmt->fetch()) {
            // Get order item details
            $detailsQuery = "SELECT oi.quantity, p.product_name, p.list_price, p.album_cover_url,
                                   (oi.quantity * p.list_price) as item_total
                            FROM order_items oi
                            INNER JOIN products p ON oi.product_id = p.product_id
                            WHERE oi.order_id = ?
                            ORDER BY oi.item_id";
            $detailsStmt = $conn->prepare($detailsQuery);
            $detailsStmt->execute([$selectedOrderId]);
            $orderDetails = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Order details query error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    }
}

$pageTitle = "myDonut - Order History";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .orders-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .orders-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .empty-orders {
            text-align: center;
            padding: 3rem;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin: 2rem 0;
            border: 1px solid #dee2e6;
        }
        
        .empty-orders h3 {
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .empty-orders p {
            color: #6c757d;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .orders-table th {
            background-color: #343a40;
            color: white;
            font-weight: bold;
        }
        
        .orders-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .order-id {
            font-weight: bold;
            color: #007bff;
        }
        
        .order-total {
            font-weight: bold;
            color: #28a745;
        }
        
        .view-details-btn {
            background-color: #007bff;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .view-details-btn:hover {
            background-color: #0056b3;
        }
        
        .order-details {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .order-details h3 {
            margin-top: 0;
            color: #343a40;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .details-table th,
        .details-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .details-table th {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .product-info {
            display: flex;
            align-items: center;
        }
        
        .product-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            margin-right: 1rem;
            border-radius: 5px;
        }
        
        .details-total {
            text-align: right;
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 1rem;
            padding: 1rem;
            background-color: #e9ecef;
            border-radius: 5px;
        }
        
        .back-to-orders {
            display: inline-block;
            margin-bottom: 1rem;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-to-orders:hover {
            text-decoration: underline;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .orders-container {
                padding: 1rem;
            }
            
            .orders-table {
                font-size: 0.9rem;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 0.5rem;
            }
            
            .product-info {
                flex-direction: column;
                text-align: center;
            }
            
            .product-thumbnail {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-buttons .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="orders-container">

            <?php if ($error): ?>
                <div class="error-message">
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php elseif (empty($orders)): ?>
                <div class="empty-orders">
                    <h3>No Orders Found</h3>
                    <p>You haven't placed any orders yet. Browse our catalog to find amazing music!</p>
                    <a href="../Products/products.php" class="btn">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="orders-header">
                    <h2>Your Order History</h2>
                    <p>View all your past orders and their details</p>
                </div>
                
                <?php if ($selectedOrderId && !empty($orderDetails)): ?>
                    <div class="order-details">
                        <a href="order.php" class="back-to-orders">← Back to Order History</a>
                        <h3>Order #<?= $selectedOrderId ?> Details</h3>
                        
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $orderTotal = 0;
                                foreach ($orderDetails as $item): 
                                    $orderTotal += $item['item_total'];
                                ?>
                                    <tr>
                                        <td class="product-info">
                                            <?php if (!empty($item['album_cover_url'])): ?>
                                                <img src="<?= htmlspecialchars($item['album_cover_url']) ?>" 
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                     class="product-thumbnail">
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($item['product_name']) ?></span>
                                        </td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>$<?= number_format($item['list_price']/100, 2) ?></td>
                                        <td>$<?= number_format($item['item_total']/100, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="details-total">
                            Order Total: $<?= number_format($orderTotal/100, 2) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Order Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="order-id">#<?= $order['order_id'] ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($order['order_date'])) ?></td>
                                    <td><?= $order['total_items'] ?> item(s)</td>
                                    <td class="order-total">$<?= number_format(($order['order_total'] ?? 0)/100, 2) ?></td>
                                    <td>
                                        <a href="order.php?order_id=<?= $order['order_id'] ?>" class="btn">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="../Products/products.php" class="btn">Continue Shopping</a>
                <a href="../Cart/cart.php" class="btn btn-secondary">View Cart</a>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 myDonut Music Store. All rights reserved.</p>
    </footer>
</body>
</html>