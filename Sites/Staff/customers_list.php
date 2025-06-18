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
$sort_by = "customer_name";
$sort_order = "ASC";
$error = "";
$success = "";
$customers = [];

// Process search and filter parameters
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['customer_id', 'customer_name', 'email', 'phone', 'total_orders'])) {
    $sort_by = $_GET['sort_by'];
}

if (isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC'])) {
    $sort_order = $_GET['sort_order'];
}

// Check for status messages
if (isset($_GET['status']) && $_GET['status'] === 'updated' && isset($_GET['id'])) {
    $success = "Customer ID " . $_GET['id'] . " has been successfully updated.";
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted' && isset($_GET['id'])) {
    $success = "Customer ID " . $_GET['id'] . " has been successfully deleted.";
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Fetch customers with search and sort
try {
    $params = [];
    $sql_conditions = [];
    
    $base_query = "SELECT c.customer_id, c.customer_name, c.email, c.phone,
                          COUNT(o.order_id) as total_orders,
                          ISNULL(SUM(oi.quantity * p.list_price), 0) as total_spent
                   FROM customers c
                   LEFT JOIN orders o ON c.customer_id = o.customer_id
                   LEFT JOIN order_items oi ON o.order_id = oi.order_id
                   LEFT JOIN products p ON oi.product_id = p.product_id";
    
    // Add search condition if provided
    if (!empty($search)) {
        $sql_conditions[] = "(c.customer_name LIKE :search)";
        $params[':search'] = "%" . $search . "%";
    }
    
    // Combine conditions if any
    $sql_where = "";
    if (!empty($sql_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $sql_conditions);
    }
    
    // Group by customer information
    $sql_group = " GROUP BY c.customer_id, c.customer_name, c.email, c.phone";
    
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
    
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Customers fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving customers.";
}

// Set page title
$pageTitle = "Customer Management - myDonut Staff Panel";
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
        .customers-header {
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
        
        .customers-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .customers-table th, .customers-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .customers-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: relative;
        }
        
        .customers-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .customer-actions {
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
        
        .customer-id {
            font-weight: bold;
            color: #007bff;
        }
        
        .customer-name {
            font-weight: bold;
            color: #333;
        }
        
        .customer-email {
            color: #666;
            font-size: 0.9rem;
        }
        
        .customer-phone {
            color: #555;
            font-family: monospace;
        }
        
        .orders-count {
            font-weight: bold;
            color: #ff6347;
        }
        
        .total-spent {
            font-weight: bold;
            color: #28a745;
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
        
        @media (max-width: 768px) {
            .customers-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                flex-direction: column;
                width: 100%;
            }
            
            .search-form input, .search-form button {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .customers-table {
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
            <div class="customers-header">
                <div>
                    <h2>Customer Management</h2>
                    <p>View, search, and manage customers.</p>
                </div>
                
                <a href="customer_add.php" class="btn">
                    <i class="fas fa-plus"></i> Add New Customer
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
                    <h3>Total Customers</h3>
                    <div class="number"><?= count($customers) ?></div>
                </div>
                <div class="stats-card">
                    <h3>Total Orders</h3>
                    <div class="number"><?= array_sum(array_column($customers, 'total_orders')) ?></div>
                </div>
                <div class="stats-card">
                    <h3>Total Revenue</h3>
                    <div class="number">
                        $<?= number_format(array_sum(array_column($customers, 'total_spent'))/100, 2) ?>
                    </div>
                </div>
            </div>
            
            <form method="get" action="<?= $_SERVER['PHP_SELF'] ?>" class="search-form">
                <div class="search-input-wrapper">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search customer's name...">
                </div>
                
                <div class="search-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($search)): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="table-container">
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&sort_by=customer_id&sort_order=<?= $sort_by == 'customer_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Customer ID
                                    <?php if ($sort_by == 'customer_id'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&sort_by=customer_name&sort_order=<?= $sort_by == 'customer_name' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Customer Name
                                    <?php if ($sort_by == 'customer_name'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&sort_by=email&sort_order=<?= $sort_by == 'email' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Email
                                    <?php if ($sort_by == 'email'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Phone</th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&sort_by=total_orders&sort_order=<?= $sort_by == 'total_orders' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Orders
                                    <?php if ($sort_by == 'total_orders'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Total Spent</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customers) > 0): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td class="customer-id">#<?= $customer['customer_id'] ?></td>
                                    <td class="customer-name"><?= htmlspecialchars($customer['customer_name']) ?></td>
                                    <td class="customer-email"><?= htmlspecialchars($customer['email']) ?></td>
                                    <td class="customer-phone"><?= htmlspecialchars($customer['phone']) ?></td>
                                    <td class="orders-count"><?= $customer['total_orders'] ?></td>
                                    <td class="total-spent">$<?= number_format(($customer['total_spent'] ?? 0)/100, 2) ?></td>
                                    <td>
                                        <div class="customer-actions">
                                            <a href="customer_view.php?id=<?= $customer['customer_id'] ?>" class="action-btn view-btn" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="customer_edit.php?id=<?= $customer['customer_id'] ?>" class="action-btn edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="action-btn delete-btn" 
                                                    onclick="confirmDelete(<?= $customer['customer_id'] ?>, '<?= addslashes(htmlspecialchars($customer['customer_name'])) ?>')" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    <p>No customers found matching your criteria.</p>
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
        function confirmDelete(customerId, customerName) {
            if (confirm(`Are you sure you want to delete customer #${customerId} "${customerName}"?\n\nThis action cannot be undone and will also delete all associated orders.`)) {
                window.location.href = `customer_delete.php?id=${customerId}`;
            }
        }
    </script>
</body>
</html>