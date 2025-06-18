<?php
session_start();

// Include database connection
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$customer_id = $_SESSION['user_id'];
$message = '';
$error = '';
$order_id = null;

try {
    // Start transaction
    $conn->beginTransaction();
    
    // First, get cart items for this customer
    $cartQuery = "SELECT ci.product_id, ci.quantity, p.product_name, p.list_price, p.quantity as stock_quantity
                  FROM cart_items ci
                  INNER JOIN products p ON ci.product_id = p.product_id
                  WHERE ci.customer_id = ?";
    $cartStmt = $conn->prepare($cartQuery);
    $cartStmt->execute([$customer_id]);
    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartItems)) {
        throw new Exception('Your cart is empty. Please add items before placing an order.');
    }
    
    // Validate stock availability for all items
    foreach ($cartItems as $item) {
        if ($item['quantity'] > $item['stock_quantity']) {
            throw new Exception("Insufficient stock for {$item['product_name']}. Only {$item['stock_quantity']} available.");
        }
    }
    
    // Generate new order_id
    $orderIdQuery = "SELECT ISNULL(MAX(order_id), 0) + 1 AS new_order_id FROM orders";
    $orderIdStmt = $conn->prepare($orderIdQuery);
    $orderIdStmt->execute();
    $order_id = $orderIdStmt->fetch(PDO::FETCH_ASSOC)['new_order_id'];
    
    // Get a staff member (assuming there's at least one staff member, or use a default)
    $staffQuery = "SELECT TOP 1 staff_id FROM staffs ORDER BY staff_id";
    $staffStmt = $conn->prepare($staffQuery);
    $staffStmt->execute();
    $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
    $staff_id = $staff ? $staff['staff_id'] : 1; // Default to staff_id 1 if no staff found
    
    // Insert into orders table
    $insertOrderQuery = "INSERT INTO orders (order_id, customer_id, order_date, staff_id) VALUES (?, ?, GETDATE(), ?)";
    $insertOrderStmt = $conn->prepare($insertOrderQuery);
    $insertOrderStmt->execute([$order_id, $customer_id, $staff_id]);
    
    // Insert order items and update product quantities
    $item_id = 1;
    foreach ($cartItems as $item) {
        // Insert into order_items
        $insertItemQuery = "INSERT INTO order_items (order_id, item_id, product_id, quantity) VALUES (?, ?, ?, ?)";
        $insertItemStmt = $conn->prepare($insertItemQuery);
        $insertItemStmt->execute([$order_id, $item_id, $item['product_id'], $item['quantity']]);
        
        // Update product quantity (decrease stock)
        $updateStockQuery = "UPDATE products SET quantity = quantity - ? WHERE product_id = ?";
        $updateStockStmt = $conn->prepare($updateStockQuery);
        $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
        
        $item_id++;
    }
    
    // Clear the customer's cart
    $clearCartQuery = "DELETE FROM cart_items WHERE customer_id = ?";
    $clearCartStmt = $conn->prepare($clearCartQuery);
    $clearCartStmt->execute([$customer_id]);
    
    // Commit transaction
    $conn->commit();
    
    $message = "Order placed successfully! Your order ID is #$order_id";
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    $error = $e->getMessage();
    error_log("Place order error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
} catch (PDOException $e) {
    // Rollback transaction
    $conn->rollback();
    $error = "Unable to process your order at this time. Please try again later.";
    error_log("Database error in place_order: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
}

// Get order details for confirmation (if order was successful)
$orderDetails = [];
$orderTotal = 0;
if ($order_id && !$error) {
    try {
        $orderDetailsQuery = "SELECT oi.quantity, p.product_name, p.list_price, o.order_date
                             FROM order_items oi
                             INNER JOIN products p ON oi.product_id = p.product_id
                             INNER JOIN orders o ON oi.order_id = o.order_id
                             WHERE oi.order_id = ?
                             ORDER BY oi.item_id";
        $orderDetailsStmt = $conn->prepare($orderDetailsQuery);
        $orderDetailsStmt->execute([$order_id]);
        $orderDetails = $orderDetailsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total
        foreach ($orderDetails as $item) {
            $orderTotal += $item['quantity'] * $item['list_price'];
        }
    } catch (PDOException $e) {
        error_log("Order details query error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    }
}

$pageTitle = "myDonut - Place Order";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .order-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .order-summary {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .order-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .order-details-table th,
        .order-details-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .order-details-table th {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .order-total {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: right;
            margin-top: 1rem;
            padding: 1rem;
            background-color: #e9ecef;
            border-radius: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        .order-id {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="order-container">
            <?php if ($message): ?>
                <div class="success-message">
                    <h2>✅ Order Confirmed!</h2>
                    <p><?= htmlspecialchars($message) ?></p>
                    <div class="order-id">Order #<?= $order_id ?></div>
                </div>
                
                <?php if (!empty($orderDetails)): ?>
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <p><strong>Order Date:</strong> <?= date('F j, Y g:i A', strtotime($orderDetails[0]['order_date'])) ?></p>
                        
                        <table class="order-details-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderDetails as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>$<?= number_format($item['list_price']/100, 2) ?></td>
                                        <td>$<?= number_format(($item['quantity'] * $item['list_price'])/100, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="order-total">
                            Total: $<?= number_format($orderTotal/100, 2) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <h2>❌ Order Failed</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <?php if ($message): ?>
                    <a href="../Products/products.php" class="btn">Continue Shopping</a>
                    <a href="order.php" class="btn btn-secondary">View Order History</a>
                <?php else: ?>
                    <a href="../Cart/cart.php" class="btn btn-secondary">Back to Cart</a>
                    <a href="../Products/products.php" class="btn">Continue Shopping</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 myDonut Music Store. All rights reserved.</p>
    </footer>
</body>
</html>