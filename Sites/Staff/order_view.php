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
$order_id = null;
$order = null;
$order_items = [];
$error = "";

// Get order ID from URL parameter
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = (int)$_GET['id'];
} else {
    header("Location: orders_list.php?error=" . urlencode("Invalid order ID."));
    exit();
}

// Fetch order information
try {
    $order_query = "SELECT o.order_id, o.order_date, o.customer_id,
                           c.customer_name, c.email, c.phone
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.customer_id
                    WHERE o.order_id = ?";
    
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: orders_list.php?error=" . urlencode("Order not found."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Order fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving order information.";
}

// Fetch order items
try {
    $items_query = "SELECT oi.item_id, oi.quantity, 
                           p.product_id, p.product_name, p.list_price, p.album_cover_url,
                           g.genre_name,
                           (oi.quantity * p.list_price) as item_total
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.product_id
                    LEFT JOIN genres g ON p.genre_id = g.genre_id
                    WHERE oi.order_id = ?
                    ORDER BY oi.item_id";
    
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Order items fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving order items.";
}

// Calculate totals
$total_items = array_sum(array_column($order_items, 'quantity'));
$total_amount = array_sum(array_column($order_items, 'item_total'));

$pageTitle = "Order Details #" . $order_id . " - myDonut Staff Panel";
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
        .order-view-container {
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
        
        .order-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .order-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .order-id-badge {
            background: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .order-actions {
            display: flex;
            gap: 1rem;
        }
        
        .order-info-grid {
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
        
        .items-section {
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
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .items-table th,
        .items-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .items-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .product-genre {
            color: #666;
            font-size: 0.9rem;
        }
        
        .price-cell {
            font-weight: bold;
            color: #28a745;
        }
        
        .quantity-cell {
            font-weight: bold;
            color: #007bff;
            text-align: center;
        }
        
        .total-cell {
            font-weight: bold;
            color: #dc3545;
        }
        
        .order-summary {
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
        
        .empty-items {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-items i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
        
        @media (max-width: 768px) {
            .order-view-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .order-header,
            .items-section {
                padding: 1.5rem;
            }
            
            .order-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .order-actions {
                width: 100%;
                justify-content: stretch;
            }
            
            .order-actions .btn {
                flex: 1;
                text-align: center;
            }
            
            .info-section {
                padding: 1rem;
            }
            
            .product-info {
                flex-direction: column;
                text-align: center;
            }
            
            .items-table {
                font-size: 0.9rem;
            }
            
            .items-table th,
            .items-table td {
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
        
        .order-header,
        .items-section,
        .order-summary {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .items-section {
            animation-delay: 0.1s;
        }
        
        .order-summary {
            animation-delay: 0.2s;
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="order-view-container">
            <a href="orders_list.php" class="back-link">
                <i class="fas fa-chevron-left"></i> Back to Orders
            </a>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($order): ?>
                <!-- Order Header -->
                <div class="order-header">
                    <div class="order-title">
                        <div class="order-id-badge">
                            Order #<?= $order['order_id'] ?>
                        </div>
                        
                        <div class="order-actions">
                            <a href="order_edit.php?id=<?= $order['order_id'] ?>" class="btn">
                                <i class="fas fa-edit"></i> Edit Order
                            </a>
                            <button type="button" class="btn btn-secondary" 
                                    onclick="confirmDelete(<?= $order['order_id'] ?>, '<?= addslashes(htmlspecialchars($order['customer_name'])) ?>')">
                                <i class="fas fa-trash"></i> Delete Order
                            </button>
                        </div>
                    </div>
                    
                    <div class="order-info-grid">
                        <div class="info-section">
                            <h3><i class="fas fa-user"></i> Customer Information</h3>
                            <div class="info-item">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($order['email']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?= htmlspecialchars($order['phone']) ?></span>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h3><i class="fas fa-calendar-alt"></i> Order Details</h3>
                            <div class="info-item">
                                <span class="info-label">Order Date:</span>
                                <span class="info-value"><?= date('F j, Y', strtotime($order['order_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Order Time:</span>
                                <span class="info-value"><?= date('g:i A', strtotime($order['order_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Customer ID:</span>
                                <span class="info-value">#<?= $order['customer_id'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="items-section">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-cart"></i> Order Items
                    </h2>
                    
                    <?php if (count($order_items) > 0): ?>
                        <div class="table-container">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Unit Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <div class="product-image" 
                                                         style="background-image: url('<?= htmlspecialchars($item['album_cover_url']) ?>');">
                                                    </div>
                                                    <div class="product-details">
                                                        <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                                        <div class="product-genre"><?= htmlspecialchars($item['genre_name'] ?? 'Unknown Genre') ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="price-cell">$<?= number_format($item['list_price']/100, 2) ?></td>
                                            <td class="quantity-cell"><?= $item['quantity'] ?></td>
                                            <td class="total-cell">$<?= number_format($item['item_total']/100, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-items">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>No Items Found</h3>
                            <p>This order doesn't contain any items.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <h4>Total Items</h4>
                            <p class="summary-value"><?= $total_items ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Order Total</h4>
                            <p class="summary-value">$<?= number_format($total_amount/100, 2) ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Products Count</h4>
                            <p class="summary-value"><?= count($order_items) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        function confirmDelete(orderId, customerName) {
            if (confirm(`Are you sure you want to delete order #${orderId} for "${customerName}"?\n\nThis action cannot be undone and will remove all order items.`)) {
                window.location.href = `order_delete.php?id=${orderId}`;
            }
        }
        
        // Add loading effect for images
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.product-image');
            images.forEach(img => {
                const bgImage = img.style.backgroundImage;
                if (bgImage && bgImage !== 'url("")') {
                    const imageUrl = bgImage.slice(5, -2); // Remove url(" and ")
                    const testImage = new Image();
                    testImage.onload = function() {
                        img.style.opacity = '1';
                    };
                    testImage.onerror = function() {
                        img.style.backgroundImage = 'linear-gradient(45deg, #f0f0f0, #e0e0e0)';
                        img.innerHTML = '<i class="fas fa-music" style="color: #ccc; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; height: 100%;"></i>';
                    };
                    img.style.opacity = '0.5';
                    testImage.src = imageUrl;
                }
            });
        });
    </script>
</body>
</html>