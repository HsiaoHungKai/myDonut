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
$genre_filter = "";
$sort_by = "product_id";
$sort_order = "ASC";
$error = "";
$success = "";
$products = [];
$genres = [];

// Process search and filter parameters
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['genre'])) {
    $genre_filter = $_GET['genre'];
}

if (isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['product_id', 'product_name', 'list_price', 'quantity'])) {
    $sort_by = $_GET['sort_by'];
}

if (isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC'])) {
    $sort_order = $_GET['sort_order'];
}

// Check for status messages
if (isset($_GET['status']) && $_GET['status'] === 'updated' && isset($_GET['id'])) {
    $success = "Product ID " . $_GET['id'] . " has been successfully updated.";
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted' && isset($_GET['id'])) {
    $success = "Product ID " . $_GET['id'] . " has been successfully deleted.";
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Fetch all available genres for filter dropdown
try {
    $query = "SELECT genre_id, genre_name FROM genres ORDER BY genre_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Genre fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Error loading genre filters.";
}

// Fetch products with search, filter and sort
try {
    $params = [];
    $sql_conditions = [];
    
    $base_query = "SELECT p.product_id, p.product_name, g.genre_name, p.list_price, 
                   p.album_cover_url, p.quantity, g.genre_id
                   FROM products p
                   JOIN genres g ON p.genre_id = g.genre_id";
    
    // Add search condition if provided
    if (!empty($search)) {
        $sql_conditions[] = "(p.product_name LIKE :search)";
        $params[':search'] = "%" . $search . "%";
    }
    
    // Add genre filter if provided
    if (!empty($genre_filter)) {
        $sql_conditions[] = "g.genre_id = :genre_id";
        $params[':genre_id'] = $genre_filter;
    }
    
    // Combine conditions if any
    $sql_where = "";
    if (!empty($sql_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $sql_conditions);
    }
    
    // Add sorting
    $sql_order = " ORDER BY " . $sort_by . " " . $sort_order;
    
    // Build final query
    $query = $base_query . $sql_where . $sql_order;
    
    // Prepare and execute query
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Product fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving products.";
}

// Set page title
$pageTitle = "Product Management - myDonut Staff Panel";
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
        .products-header {
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
        
        .genre-select-wrapper {
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
            .genre-select-wrapper {
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
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .products-table th, .products-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .products-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: relative;
        }
        
        .products-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            background-size: cover;
            background-position: center;
            border-radius: 5px;
        }
        
        .product-actions {
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
        
        .edit-btn {
            color: #007bff;
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
        
        .inventory-status {
            font-weight: bold;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }
        
        .status-instock {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-low {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-outofstock {
            background-color: #f8d7da;
            color: #721c24;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover, .pagination a.active {
            background: #ff6347;
            color: white;
            border-color: #ff6347;
        }
        
        @media (max-width: 992px) {
            .products-table {
                font-size: 0.9rem;
            }
            
            .products-table th, .products-table td {
                padding: 0.75rem;
            }
            
            .product-image {
                width: 50px;
                height: 50px;
            }
        }
        
        @media (max-width: 768px) {
            .products-header {
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
            
            .products-table {
                min-width: 800px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="container">
            <div class="products-header">
                <div>
                    <h2>Product Management</h2>
                    <p>View, search, and modify product information.</p>
                </div>
                
                <a href="product_add.php" class="btn">
                    <i class="fas fa-plus"></i> Add New Product
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
            
            <form method="get" action="<?= $_SERVER['PHP_SELF'] ?>" class="search-form">
                <div class="search-input-wrapper">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products...">
                    <!-- <span class="search-icon"><i class="fas fa-search"></i></span> -->
                </div>
                
                <div class="genre-select-wrapper">
                    <select name="genre">
                        <option value="">All Genres</option>
                        <?php foreach ($genres as $genre): ?>
                            <option value="<?= $genre['genre_id'] ?>" <?= $genre_filter == $genre['genre_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($genre['genre_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($search) || !empty($genre_filter)): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="table-container">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&genre=<?= $genre_filter ?>&sort_by=product_id&sort_order=<?= $sort_by == 'product_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    ID
                                    <?php if ($sort_by == 'product_id'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Image</th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&genre=<?= $genre_filter ?>&sort_by=product_name&sort_order=<?= $sort_by == 'product_name' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Name
                                    <?php if ($sort_by == 'product_name'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Genre</th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&genre=<?= $genre_filter ?>&sort_by=list_price&sort_order=<?= $sort_by == 'list_price' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Price
                                    <?php if ($sort_by == 'list_price'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&genre=<?= $genre_filter ?>&sort_by=quantity&sort_order=<?= $sort_by == 'quantity' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Stock
                                    <?php if ($sort_by == 'quantity'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= $product['product_id'] ?></td>
                                    <td>
                                        <div class="product-image" style="background-image: url('<?= htmlspecialchars($product['album_cover_url']) ?>')"></div>
                                    </td>
                                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= htmlspecialchars($product['genre_name']) ?></td>
                                    <td>$<?= number_format($product['list_price']/100, 2) ?></td>
                                    <td><?= $product['quantity'] ?></td>
                                    <td>
                                        <?php
                                        if ($product['quantity'] <= 0) {
                                            echo '<span class="inventory-status status-outofstock">Out of Stock</span>';
                                        } elseif ($product['quantity'] <= 5) {
                                            echo '<span class="inventory-status status-low">Low Stock</span>';
                                        } else {
                                            echo '<span class="inventory-status status-instock">In Stock</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="product-actions">
                                            <a href="product_edit.php?id=<?= $product['product_id'] ?>" class="action-btn edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="action-btn delete-btn" 
                                                    onclick="confirmDelete(<?= $product['product_id'] ?>, '<?= addslashes(htmlspecialchars($product['product_name'])) ?>')" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">
                                    <p>No products found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination can be added here in the future if needed -->
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        function confirmDelete(productId, productName) {
            if (confirm(`Are you sure you want to delete "${productName}" (ID: ${productId})?\n\nThis action cannot be undone.`)) {
                window.location.href = `product-delete.php?id=${productId}`;
            }
        }
    </script>
</body>
</html>