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
$order_id = 0;
$order = [];
$order_items = [];
$customers = [];
$products = [];
$error = "";
$success = "";

// Check if order ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = (int)$_GET['id'];
} else {
    // Redirect to orders list if no ID provided
    header("Location: orders_list.php?error=" . urlencode("No order selected for editing."));
    exit();
}

// Fetch customers for dropdown
try {
    $query = "SELECT customer_id, customer_name, email FROM customers ORDER BY customer_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Customer fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Error loading customer data.";
}

// Fetch products for dropdown
try {
    $query = "SELECT p.product_id, p.product_name, p.list_price, p.quantity, p.album_cover_url, g.genre_name 
              FROM products p 
              LEFT JOIN genres g ON p.genre_id = g.genre_id 
              ORDER BY p.product_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Product fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Error loading product data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $customer_id = (int)$_POST['customer_id'];
    $order_items_data = $_POST['order_items'] ?? [];
    
    // Validation
    $errors = [];
    
    if (empty($customer_id) || !is_numeric($customer_id)) {
        $errors[] = "Please select a valid customer.";
    }
    
    if (empty($order_items_data) || !is_array($order_items_data)) {
        $errors[] = "Please add at least one product to the order.";
    } else {
        // Validate each order item
        foreach ($order_items_data as $index => $item) {
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
            
            // Get current order items to restore stock
            $currentItemsQuery = "SELECT oi.product_id, oi.quantity 
                                 FROM order_items oi 
                                 WHERE oi.order_id = ?";
            $currentItemsStmt = $conn->prepare($currentItemsQuery);
            $currentItemsStmt->execute([$order_id]);
            $currentItems = $currentItemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Restore stock for current items
            foreach ($currentItems as $currentItem) {
                $restoreStockQuery = "UPDATE products SET quantity = quantity + ? WHERE product_id = ?";
                $restoreStockStmt = $conn->prepare($restoreStockQuery);
                $restoreStockStmt->execute([$currentItem['quantity'], $currentItem['product_id']]);
            }
            
            // Update order customer
            $updateOrderQuery = "UPDATE orders SET customer_id = ? WHERE order_id = ?";
            $updateOrderStmt = $conn->prepare($updateOrderQuery);
            $updateOrderStmt->execute([$customer_id, $order_id]);
            
            // Delete existing order items
            $deleteItemsQuery = "DELETE FROM order_items WHERE order_id = ?";
            $deleteItemsStmt = $conn->prepare($deleteItemsQuery);
            $deleteItemsStmt->execute([$order_id]);
            
            // Insert new order items and update product quantities
            $item_id = 1;
            foreach ($order_items_data as $item) {
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
                $insertItemStmt->execute([$order_id, $item_id, $product_id, $quantity]);
                
                // Update product quantity
                $updateQuantityQuery = "UPDATE products SET quantity = quantity - ? WHERE product_id = ?";
                $updateQuantityStmt = $conn->prepare($updateQuantityQuery);
                $updateQuantityStmt->execute([$quantity, $product_id]);
                
                $item_id++;
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to orders list with success message
            header("Location: orders_list.php?status=updated&id=" . $order_id);
            exit();
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Order update error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        } catch (PDOException $e) {
            // Rollback transaction
            $conn->rollback();
            $error = "Database error occurred. Please try again.";
            error_log("Database error in order_edit: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        }
    }
}

// Fetch order data
try {
    $query = "SELECT o.*, c.customer_name, c.email FROM orders o
              JOIN customers c ON o.customer_id = c.customer_id
              WHERE o.order_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$order_id]);
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        // Redirect if order not found
        header("Location: orders_list.php?error=" . urlencode("Order not found."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Order fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Failed to load order data.";
}

// Fetch order items
try {
    $itemsQuery = "SELECT oi.item_id, oi.product_id, oi.quantity, 
                          p.product_name, p.list_price, p.album_cover_url, g.genre_name
                   FROM order_items oi
                   JOIN products p ON oi.product_id = p.product_id
                   LEFT JOIN genres g ON p.genre_id = g.genre_id
                   WHERE oi.order_id = ?
                   ORDER BY oi.item_id";
    
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->execute([$order_id]);
    $order_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Order items fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Failed to load order items.";
}

// Set page title
$pageTitle = "Edit Order #" . $order_id . " - myDonut Staff Panel";
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
        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .form-header {
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        
        .order-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: bold;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: flex;
            gap: 1.5rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 0 0 3px rgba(255, 99, 71, 0.1);
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
        
        .product-preview {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .product-image {
            width: 40px;
            height: 40px;
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
        
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 1.5rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .order-item-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .button-group {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="container">
            <div class="form-container">
                <div class="form-header">
                    <h2>Edit Order #<?= $order_id ?></h2>
                    <p>Update order information and items</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <!-- Order Information -->
                <div class="order-info">
                    <div class="info-item">
                        <span class="info-label">Order Date:</span>
                        <span class="info-value"><?= date('F j, Y g:i A', strtotime($order['order_date'])) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current Customer:</span>
                        <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Customer Email:</span>
                        <span class="info-value"><?= htmlspecialchars($order['email']) ?></span>
                    </div>
                </div>
                
                <form method="post" action="<?= $_SERVER['PHP_SELF'] . '?id=' . $order_id ?>" id="order-form">
                    <div class="form-group">
                        <label for="customer_id">Customer <span style="color: #dc3545;">*</span></label>
                        <select id="customer_id" name="customer_id" required>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['customer_id'] ?>" 
                                        <?= $order['customer_id'] == $customer['customer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['customer_name']) ?> (<?= htmlspecialchars($customer['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="order-items-section">
                        <div class="order-items-header">
                            <h3>Order Items</h3>
                            <button type="button" class="add-item-btn" onclick="addOrderItem()">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                        
                        <div id="order-items-container">
                            <!-- Existing order items will be populated here -->
                        </div>
                        
                        <div class="order-summary" id="order-summary">
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
                    
                    <div class="button-group">
                        <a href="orders_list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Update Order
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
        const existingItems = <?= json_encode($order_items) ?>;
        
        function addOrderItem(existingItem = null) {
            itemCounter++;
            const container = document.getElementById('order-items-container');
            
            const itemDiv = document.createElement('div');
            itemDiv.className = 'order-item';
            itemDiv.id = `order-item-${itemCounter}`;
            
            const selectedProductId = existingItem ? existingItem.product_id : '';
            const selectedQuantity = existingItem ? existingItem.quantity : '';
            
            itemDiv.innerHTML = `
                <button type="button" class="remove-item-btn" onclick="removeOrderItem(${itemCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="order-item-grid">
                    <div class="form-group">
                        <label>Product <span style="color: #dc3545;">*</span></label>
                        <select name="order_items[${itemCounter}][product_id]" required onchange="updateItemTotal(${itemCounter}); updateProductPreview(${itemCounter})">
                            <option value="">Select a product...</option>
                            ${products.map(product => `
                                <option value="${product.product_id}" 
                                        data-price="${product.list_price}" 
                                        data-stock="${product.quantity}"
                                        data-image="${product.album_cover_url || ''}"
                                        data-genre="${product.genre_name || ''}"
                                        data-name="${product.product_name}"
                                        ${selectedProductId == product.product_id ? 'selected' : ''}>
                                    ${product.product_name} - $${(product.list_price/100).toFixed(2)} (Stock: ${product.quantity})
                                </option>
                            `).join('')}
                        </select>
                        <div id="product-preview-${itemCounter}" class="product-preview" style="display: none;">
                            <div class="product-image"></div>
                            <div class="product-info">
                                <div class="product-name"></div>
                                <div class="product-details"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Quantity <span style="color: #dc3545;">*</span></label>
                        <input type="number" 
                               name="order_items[${itemCounter}][quantity]" 
                               min="1" 
                               required 
                               value="${selectedQuantity}"
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
            
            // Update preview and total for existing items
            if (existingItem) {
                updateProductPreview(itemCounter);
                updateItemTotal(itemCounter);
            }
            
            updateOrderSummary();
        }
        
        function removeOrderItem(itemId) {
            const item = document.getElementById(`order-item-${itemId}`);
            if (item) {
                item.remove();
                updateOrderSummary();
            }
        }
        
        function updateProductPreview(itemId) {
            const productSelect = document.querySelector(`#order-item-${itemId} select[name*="product_id"]`);
            const preview = document.getElementById(`product-preview-${itemId}`);
            
            if (productSelect && preview) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                
                if (selectedOption.value) {
                    const imageDiv = preview.querySelector('.product-image');
                    const nameDiv = preview.querySelector('.product-name');
                    const detailsDiv = preview.querySelector('.product-details');
                    
                    imageDiv.style.backgroundImage = `url('${selectedOption.dataset.image}')`;
                    nameDiv.textContent = selectedOption.dataset.name;
                    detailsDiv.textContent = `${selectedOption.dataset.genre} • $${(selectedOption.dataset.price/100).toFixed(2)}`;
                    
                    preview.style.display = 'flex';
                } else {
                    preview.style.display = 'none';
                }
            }
        }
        
        function updateItemTotal(itemId) {
            const productSelect = document.querySelector(`#order-item-${itemId} select[name*="product_id"]`);
            const quantityInput = document.querySelector(`#order-item-${itemId} input[name*="quantity"]`);
            const totalField = document.getElementById(`item-total-${itemId}`);
            
            if (productSelect && quantityInput && totalField) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const price = parseFloat(selectedOption.dataset.price || 0);
                const quantity = parseInt(quantityInput.value || 0);
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
        }
        
        // Load existing order items
        existingItems.forEach(item => {
            addOrderItem(item);
        });
        
        // Add one empty item if no existing items
        if (existingItems.length === 0) {
            addOrderItem();
        }
        
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