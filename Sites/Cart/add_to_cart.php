<?php
session_start();

// Include database connection
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$customer_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid product ID.';
} else {
    $product_id = (int)$_GET['id'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // First, verify the product exists and has stock
        $productQuery = "SELECT product_name, quantity FROM products WHERE product_id = ?";
        $productStmt = $conn->prepare($productQuery);
        $productStmt->execute([$product_id]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception('Product not found.');
        }
        
        if ($product['quantity'] <= 0) {
            throw new Exception('Product is out of stock.');
        }
        
        // Check if item already exists in cart
        $checkQuery = "SELECT item_id, quantity FROM cart_items WHERE customer_id = ? AND product_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$customer_id, $product_id]);
        $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingItem) {
            // Item exists, increment quantity
            $newQuantity = $existingItem['quantity'] + 1;
            
            // Check if new quantity doesn't exceed available stock
            if ($newQuantity > $product['quantity']) {
                throw new Exception('Cannot add more items. Only ' . $product['quantity'] . ' items available in stock.');
            }
            
            $updateQuery = "UPDATE cart_items SET quantity = ?, added_at = GETDATE() WHERE customer_id = ? AND product_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->execute([$newQuantity, $customer_id, $product_id]);
            
            $message = 'Item quantity updated in your cart!';
        } else {
            // Item doesn't exist, add new item
            // Generate new item_id (get max item_id for this customer and increment)
            $maxItemQuery = "SELECT ISNULL(MAX(item_id), 0) + 1 AS new_item_id FROM cart_items WHERE customer_id = ?";
            $maxItemStmt = $conn->prepare($maxItemQuery);
            $maxItemStmt->execute([$customer_id]);
            $newItemId = $maxItemStmt->fetch(PDO::FETCH_ASSOC)['new_item_id'];
            
            $insertQuery = "INSERT INTO cart_items (customer_id, item_id, product_id, quantity, added_at) VALUES (?, ?, ?, 1, GETDATE())";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->execute([$customer_id, $newItemId, $product_id]);
            
            $message = 'Item added to your cart!';
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        $error = $e->getMessage();
        error_log("Add to cart error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    } catch (PDOException $e) {
        // Rollback transaction
        $conn->rollback();
        $error = "Unable to add item to cart at this time.";
        error_log("Database error in add_to_cart: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    }
}

// Get cart count for display
$cartCount = 0;
try {
    $countQuery = "SELECT SUM(quantity) as total_items FROM cart_items WHERE customer_id = ?";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute([$customer_id]);
    $cartResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $cartResult['total_items'] ?? 0;
} catch (PDOException $e) {
    error_log("Cart count error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
}

$pageTitle = "myDonut - Add to Cart";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .message-container {
            max-width: 600px;
            margin: 2rem auto;
            text-align: center;
        }
        
        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .cart-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
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
        
        .auto-redirect {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="message-container">
            <?php if ($message): ?>
                <div class="success-message">
                    <h3>Success!</h3>
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <h3>Error</h3>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="cart-info">
                <h4>Your Cart</h4>
                <p>You currently have <strong><?= $cartCount ?></strong> item(s) in your cart.</p>
            </div>
            
            <div class="action-buttons">
                <a href="../Products/products.php" class="btn btn-secondary">Continue Shopping</a>
                <a href="../Cart/cart.php" class="btn">View Cart</a>
            </div>
            
            <?php if ($message): ?>
                <div class="auto-redirect">
                    <p>You will be redirected to the products page in <span id="countdown">5</span> seconds...</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 myDonut Music Store. All rights reserved.</p>
    </footer>

    <?php if ($message): ?>
        <script>
            // Auto-redirect after successful addition
            let countdown = 5;
            const countdownElement = document.getElementById('countdown');
            
            const timer = setInterval(() => {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = '../Products/products.php';
                }
            }, 1000);
        </script>
    <?php endif; ?>
</body>
</html>