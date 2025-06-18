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
$search = "";
$date_filter = "";
$sort_by = "order_date";
$sort_order = "DESC";
$error = "";
$success = "";
$orders = [];

// Process search and filter parameters
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['date_filter'])) {
    $date_filter = $_GET['date_filter'];
}

if (isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['order_id', 'order_date', 'customer_name', 'total_amount', 'total_items'])) {
    $sort_by = $_GET['sort_by'];
}

if (isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC'])) {
    $sort_order = $_GET['sort_order'];
}

// Check for status messages
if (isset($_GET['status']) && $_GET['status'] === 'updated' && isset($_GET['id'])) {
    $success = "Order ID " . $_GET['id'] . " has been successfully updated.";
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted' && isset($_GET['id'])) {
    $success = "Order ID " . $_GET['id'] . " has been successfully deleted.";
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Fetch orders with search, filter and sort
try {
    $params = [];
    $sql_conditions = [];
    
    $base_query = "SELECT o.order_id, o.order_date, c.customer_name, c.email,
                          COUNT(oi.item_id) as total_items,
                          SUM(oi.quantity * p.list_price) as total_amount
                   FROM orders o
                   JOIN customers c ON o.customer_id = c.customer_id
                   LEFT JOIN order_items oi ON o.order_id = oi.order_id
                   LEFT JOIN products p ON oi.product_id = p.product_id";
    
    // Add search condition if provided (search by customer name or order ID)
    if (!empty($search)) {
        $sql_conditions[] = "(c.customer_name LIKE :search)";
        $params[':search'] = "%" . $search . "%";
    }
    
    // Add date filter if provided
    if (!empty($date_filter)) {
        switch ($date_filter) {
            case 'today':
                $sql_conditions[] = "CAST(o.order_date AS DATE) = CAST(GETDATE() AS DATE)";
                break;
            case 'this_week':
                $sql_conditions[] = "o.order_date >= DATEADD(week, -1, GETDATE())";
                break;
            case 'this_month':
                $sql_conditions[] = "o.order_date >= DATEADD(month, -1, GETDATE())";
                break;
        }
    }
    
    // Combine conditions if any
    $sql_where = "";
    if (!empty($sql_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $sql_conditions);
    }
    
    // Group by order information
    $sql_group = " GROUP BY o.order_id, o.order_date, c.customer_name, c.email";
    
    // Add sorting
    $sql_order = " ORDER BY " . $sort_by . " " . $sort_order;
    
    // Build final query
    $query = $base_query . $sql_where . $sql_group . $sql_order;
    
    // Prepare and execute query
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Orders fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving orders.";
}

// Set page title
$pageTitle = "Order Management - myDonut Staff Panel";
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
        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .search-form {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin: 2rem 0;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        
        .search-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(248, 249, 250, 0.8);
            backdrop-filter: blur(10px);
            z-index: 0;
        }
        
        .search-form > * {
            position: relative;
            z-index: 1;
        }
        
        .search-input-wrapper {
            flex: 2;
            min-width: 250px;
            position: relative;
            margin-bottom: 0;
        }
        
        .search-form input[type="text"] {
            width: 100%;
            padding: 1rem 1.25rem 1rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            font-size: 1rem;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            color: #333;
        }
        
        .search-form input[type="text"]:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .search-form input[type="text"]::placeholder {
            color: #666;
            font-weight: 400;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1rem;
            pointer-events: none;
        }
        
        .date-select-wrapper {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        
        .search-form select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            font-size: 1rem;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            color: #333;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 1.5rem;
            padding-right: 3rem;
        }
        
        .search-form select:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .search-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0rem;
        }
        
        .search-form button.btn {
            padding: 1rem 2rem;
            border: 2px solid #ff6347;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            background: #ff6347;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 99, 71, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .search-form .btn-secondary {
            padding: 1rem 1.5rem;
            border: 2px solid #6c757d;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            background: transparent;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            margin-bottom: 1rem;
        }
        
        /* Responsive design */
        @media (max-width: 1024px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
                gap: 1.5rem;
            }
            
            .search-input-wrapper,
            .date-select-wrapper {
                min-width: auto;
                flex: none;
            }
            
            .search-buttons {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .search-form {
                padding: 1.5rem;
                border-radius: 15px;
            }
            
            .search-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-form button.btn,
            .search-form .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Animation for form appearance */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .search-form {
            animation: slideInUp 0.6s ease-out;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .orders-table th, .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .orders-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: relative;
        }
        
        .orders-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.5rem;
            border-radius: 50%;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        .delete-btn {
            color: #dc3545;
        }
        
        .sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .sort-icon {
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }
        
        .order-id {
            font-weight: bold;
            color: #007bff;
        }
        
        .order-total {
            font-weight: bold;
            color: #28a745;
        }
        
        .customer-info {
            display: flex;
            flex-direction: column;
        }
        
        .customer-name {
            font-weight: bold;
        }
        
        .customer-email {
            font-size: 0.9rem;
            color: #666;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stats-card h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .stats-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #ff6347;
        }
        
        @media (max-width: 992px) {
            .orders-table {
                font-size: 0.9rem;
            }
            
            .orders-table th, .orders-table td {
                padding: 0.75rem;
            }
        }
        
        @media (max-width: 768px) {
            .orders-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                flex-direction: column;
                width: 100%;
            }
            
            .search-form input, .search-form select, .search-form button {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .orders-table {
                min-width: 800px;
                font-size: 0.85rem;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="container">
            <div class="orders-header">
                <div>
                    <h2>Order Management</h2>
                    <p>View, search, and manage customer orders.</p>
                </div>

                <a href="order_add.php" class="btn">
                    <i class="fas fa-plus"></i> Add New Order
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="stats-cards">
                <div class="stats-card">
                    <h3>Total Orders</h3>
                    <div class="number"><?= count($orders) ?></div>
                </div>
                <div class="stats-card">
                    <h3>Total Revenue</h3>
                    <div class="number">
                        $<?= number_format(array_sum(array_column($orders, 'total_amount'))/100, 2) ?>
                    </div>
                </div>
                <div class="stats-card">
                    <h3>Items Sold</h3>
                    <div class="number"><?= array_sum(array_column($orders, 'total_items')) ?></div>
                </div>
            </div>
            
            <form method="get" action="<?= $_SERVER['PHP_SELF'] ?>" class="search-form">
                <div class="search-input-wrapper">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search customer...">
                </div>
                
                <div class="date-select-wrapper">
                    <select name="date_filter">
                        <option value="">All Orders</option>
                        <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="this_week" <?= $date_filter == 'this_week' ? 'selected' : '' ?>>This Week</option>
                        <option value="this_month" <?= $date_filter == 'this_month' ? 'selected' : '' ?>>This Month</option>
                    </select>
                </div>
                
                <div class="search-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($search) || !empty($date_filter)): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&date_filter=<?= $date_filter ?>&sort_by=order_id&sort_order=<?= $sort_by == 'order_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Order ID
                                    <?php if ($sort_by == 'order_id'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&date_filter=<?= $date_filter ?>&sort_by=order_date&sort_order=<?= $sort_by == 'order_date' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Order Date
                                    <?php if ($sort_by == 'order_date'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&date_filter=<?= $date_filter ?>&sort_by=customer_name&sort_order=<?= $sort_by == 'customer_name' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Customer
                                    <?php if ($sort_by == 'customer_name'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&date_filter=<?= $date_filter ?>&sort_by=total_items&sort_order=<?= $sort_by == 'total_items' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Items
                                    <?php if ($sort_by == 'total_items'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&date_filter=<?= $date_filter ?>&sort_by=total_amount&sort_order=<?= $sort_by == 'total_amount' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Total
                                    <?php if ($sort_by == 'total_amount'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="order-id">#<?= $order['order_id'] ?></td>
                                    <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                    <td>
                                        <div class="customer-info">
                                            <span class="customer-name"><?= htmlspecialchars($order['customer_name']) ?></span>
                                            <span class="customer-email"><?= htmlspecialchars($order['email']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= $order['total_items'] ?></td>
                                    <td class="order-total">$<?= number_format(($order['total_amount'] ?? 0)/100, 2) ?></td>
                                    <td>
                                        <div class="order-actions">
                                            <a href="order_view.php?id=<?= $order['order_id'] ?>" class="action-btn view-btn" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="order_edit.php?id=<?= $order['order_id'] ?>" class="action-btn edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="action-btn delete-btn" 
                                                    onclick="confirmDelete(<?= $order['order_id'] ?>, '<?= addslashes(htmlspecialchars($order['customer_name'])) ?>')" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">
                                    <p>No orders found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        function confirmDelete(orderId, customerName) {
            if (confirm(`Are you sure you want to delete order #${orderId} for "${customerName}"?\n\nThis action cannot be undone.`)) {
                window.location.href = `order_delete.php?id=${orderId}`;
            }
        }
    </script>
</body>
</html>