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
$customers = [];
$products = [];

// Fetch all available customers
try {
    $query = "SELECT customer_id, customer_name, email FROM customers ORDER BY customer_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Customer fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Error loading customers.";
}

// Fetch all available products with stock
try {
    $query = "SELECT p.product_id, p.product_name, p.list_price, p.quantity, p.album_cover_url, g.genre_name 
              FROM products p 
              LEFT JOIN genres g ON p.genre_id = g.genre_id 
              WHERE p.quantity > 0 
              ORDER BY p.product_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Product fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Error loading products.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $customer_id = $_POST['customer_id'] ?? '';
    $order_items = $_POST['order_items'] ?? [];
    
    // Validation
    $errors = [];
    
    if (empty($customer_id) || !is_numeric($customer_id)) {
        $errors[] = "Please select a valid customer.";
    }
    
    if (empty($order_items) || !is_array($order_items)) {
        $errors[] = "Please add at least one product to the order.";
    } else {
        // Validate each order item
        foreach ($order_items as $index => $item) {
            $product_id = $item['product_id'] ?? '';
            $quantity = $item['quantity'] ?? '';
            
            if (empty($product_id) || !is_numeric($product_id)) {
                $errors[] = "Invalid product selected for item " . ($index + 1) . ".";
            }
            
            if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
                $errors[] = "Invalid quantity for item " . ($index + 1) . " (must be greater than 0).";
            }
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Verify customer exists
            $customerCheckQuery = "SELECT customer_id FROM customers WHERE customer_id = ?";
            $customerCheckStmt = $conn->prepare($customerCheckQuery);
            $customerCheckStmt->execute([$customer_id]);
            
            if (!$customerCheckStmt->fetch()) {
                throw new Exception("Selected customer does not exist.");
            }
            
            // Generate new order_id
            $orderIdQuery = "SELECT ISNULL(MAX(order_id), 0) + 1 AS new_order_id FROM orders";
            $orderIdStmt = $conn->prepare($orderIdQuery);
            $orderIdStmt->execute();
            $new_order_id = $orderIdStmt->fetch(PDO::FETCH_ASSOC)['new_order_id'];
            
            // Insert new order
            $insertOrderQuery = "INSERT INTO orders (order_id, customer_id, order_date) VALUES (?, ?, GETDATE())";
            $insertOrderStmt = $conn->prepare($insertOrderQuery);
            $insertOrderStmt->execute([$new_order_id, $customer_id]);
            
            // Insert order items and update product quantities
            $item_id = 1;
            foreach ($order_items as $item) {
                $product_id = $item['product_id'];
                $quantity = (int)$item['quantity'];
                
                // Check if product exists and has enough stock
                $productCheckQuery = "SELECT product_id, quantity FROM products WHERE product_id = ?";
                $productCheckStmt = $conn->prepare($productCheckQuery);
                $productCheckStmt->execute([$product_id]);
                $product = $productCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception("Product with ID $product_id not found.");
                }
                
                if ($product['quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for product ID $product_id. Available: " . $product['quantity'] . ", Requested: $quantity");
                }
                
                // Insert order item
                $insertItemQuery = "INSERT INTO order_items (order_id, item_id, product_id, quantity) VALUES (?, ?, ?, ?)";
                $insertItemStmt = $conn->prepare($insertItemQuery);
                $insertItemStmt->execute([$new_order_id, $item_id, $product_id, $quantity]);
                
                // Update product quantity
                $updateQuantityQuery = "UPDATE products SET quantity = quantity - ? WHERE product_id = ?";
                $updateQuantityStmt = $conn->prepare($updateQuantityQuery);
                $updateQuantityStmt->execute([$quantity, $product_id]);
                
                $item_id++;
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = "Order has been successfully created with ID #$new_order_id!";
            
            // Clear form data on success
            $customer_id = '';
            $order_items = [];
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Order add error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        } catch (PDOException $e) {
            // Rollback transaction
            $conn->rollback();
            $error = "Database error occurred. Please try again.";
            error_log("Database error in order_add: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        }
    }
}

$pageTitle = "Add New Order - myDonut Staff Panel";
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
        .add-order-container {
            max-width: 1000px;
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
        
        .order-items-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .order-items-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .order-item {
            background: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            position: relative;
        }
        
        .order-item-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .product-select-wrapper {
            position: relative;
        }
        
        .product-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
        }
        
        .product-image {
            width: 30px;
            height: 30px;
            background-size: cover;
            background-position: center;
            border-radius: 3px;
            flex-shrink: 0;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .product-details {
            font-size: 0.8rem;
            color: #666;
        }
        
        .remove-item-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .remove-item-btn:hover {
            background: #c82333;
        }
        
        .add-item-btn {
            background: transparent;
            color: #ff6347;
            border: 2px solid #ff6347;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .add-item-btn:hover {
            background: #ff6347;
            color: white;
            transform: scale(1.05);
        }
        
        .order-summary {
            background: #e9ecef;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .summary-total {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 1px solid #adb5bd;
            padding-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .add-order-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .order-item-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
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
        <div class="add-order-container">
            <a href="orders_list.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Orders List
            </a>
            
            <div class="page-header">
                <h2>Add New Order</h2>
                <p>Create a new order for a customer</p>
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
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="order-form">
                    <div class="form-group">
                        <label for="customer_id">Customer <span class="required">*</span></label>
                        <select id="customer_id" name="customer_id" required>
                            <option value="">Select a customer...</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['customer_id'] ?>" 
                                        <?= (isset($customer_id) && $customer_id == $customer['customer_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['customer_name']) ?> (<?= htmlspecialchars($customer['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Choose the customer for this order</small>
                    </div>
                    
                    <div class="order-items-section">
                        <div class="order-items-header">
                            <h3>Order Items</h3>
                            <button type="button" class="add-item-btn" onclick="addOrderItem()">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                        
                        <div id="order-items-container">
                            <!-- Order items will be added here dynamically -->
                        </div>
                        
                        <div class="order-summary" id="order-summary" style="display: none;">
                            <div class="summary-row">
                                <span>Total Items:</span>
                                <span id="total-items">0</span>
                            </div>
                            <div class="summary-row summary-total">
                                <span>Order Total:</span>
                                <span id="order-total">$0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="orders_list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Create Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        let itemCounter = 0;
        const products = <?= json_encode($products) ?>;
        
        function addOrderItem() {
            itemCounter++;
            const container = document.getElementById('order-items-container');
            
            const itemDiv = document.createElement('div');
            itemDiv.className = 'order-item';
            itemDiv.id = `order-item-${itemCounter}`;
            
            itemDiv.innerHTML = `
                <button type="button" class="remove-item-btn" onclick="removeOrderItem(${itemCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="order-item-grid">
                    <div class="form-group">
                        <label>Product <span class="required">*</span></label>
                        <select name="order_items[${itemCounter}][product_id]" required onchange="updateItemTotal(${itemCounter})">
                            <option value="">Select a product...</option>
                            ${products.map(product => `
                                <option value="${product.product_id}" 
                                        data-price="${product.list_price}" 
                                        data-stock="${product.quantity}"
                                        data-image="${product.album_cover_url || ''}"
                                        data-genre="${product.genre_name || ''}">
                                    ${product.product_name} - $${(product.list_price/100).toFixed(2)} (Stock: ${product.quantity})
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity <span class="required">*</span></label>
                        <input type="number" 
                               name="order_items[${itemCounter}][quantity]" 
                               min="1" 
                               required 
                               placeholder="1"
                               onchange="updateItemTotal(${itemCounter})">
                    </div>
                    <div class="form-group">
                        <label>Item Total</label>
                        <input type="text" 
                               id="item-total-${itemCounter}" 
                               readonly 
                               value="$0.00"
                               style="background: #f8f9fa;">
                    </div>
                </div>
            `;
            
            container.appendChild(itemDiv);
            updateOrderSummary();
        }
        
        function removeOrderItem(itemId) {
            const item = document.getElementById(`order-item-${itemId}`);
            if (item) {
                item.remove();
                updateOrderSummary();
            }
        }
        
        function updateItemTotal(itemId) {
            const productSelect = document.querySelector(`#order-item-${itemId} select[name*="product_id"]`);
            const quantityInput = document.querySelector(`#order-item-${itemId} input[name*="quantity"]`);
            const totalField = document.getElementById(`item-total-${itemId}`);
            
            if (productSelect && quantityInput && totalField) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const price = parseFloat(selectedOption.dataset.price || 0);
                let quantity = parseInt(quantityInput.value || 0);
                const stock = parseInt(selectedOption.dataset.stock || 0);
                
                // Check stock availability
                if (quantity > stock && stock > 0) {
                    alert(`Only ${stock} units available for this product.`);
                    quantityInput.value = stock;
                    quantity = stock;
                }
                
                const total = (price * quantity) / 100;
                totalField.value = `$${total.toFixed(2)}`;
                
                updateOrderSummary();
            }
        }
        
        function updateOrderSummary() {
            const items = document.querySelectorAll('.order-item');
            const summary = document.getElementById('order-summary');
            
            if (items.length === 0) {
                summary.style.display = 'none';
                return;
            }
            
            let totalItems = 0;
            let orderTotal = 0;
            
            items.forEach(item => {
                const quantityInput = item.querySelector('input[name*="quantity"]');
                const productSelect = item.querySelector('select[name*="product_id"]');
                
                if (quantityInput && productSelect) {
                    const quantity = parseInt(quantityInput.value || 0);
                    const selectedOption = productSelect.options[productSelect.selectedIndex];
                    const price = parseFloat(selectedOption.dataset.price || 0);
                    
                    totalItems += quantity;
                    orderTotal += (price * quantity) / 100;
                }
            });
            
            document.getElementById('total-items').textContent = totalItems;
            document.getElementById('order-total').textContent = `$${orderTotal.toFixed(2)}`;
            summary.style.display = 'block';
        }
        
        // Add initial order item
        addOrderItem();
        
        // Form validation
        document.getElementById('order-form').addEventListener('submit', function(e) {
            const customerId = document.getElementById('customer_id').value;
            const items = document.querySelectorAll('.order-item');
            
            if (!customerId) {
                alert('Please select a customer.');
                e.preventDefault();
                return;
            }
            
            if (items.length === 0) {
                alert('Please add at least one product to the order.');
                e.preventDefault();
                return;
            }
            
            // Validate each item
            let valid = true;
            items.forEach((item, index) => {
                const productSelect = item.querySelector('select[name*="product_id"]');
                const quantityInput = item.querySelector('input[name*="quantity"]');
                
                if (!productSelect.value) {
                    alert(`Please select a product for item ${index + 1}.`);
                    valid = false;
                    return;
                }
                
                if (!quantityInput.value || parseInt(quantityInput.value) <= 0) {
                    alert(`Please enter a valid quantity for item ${index + 1}.`);
                    valid = false;
                    return;
                }
            });
            
            if (!valid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>