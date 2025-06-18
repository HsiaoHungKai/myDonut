<?php
// filepath: /Users/Repositories/myDonut/Sites/Products/products.php

// Include database connection
require_once '../connect.php';

// Prepare and execute query to get all products with genre information
try {
    $query = "SELECT p.product_id, p.product_name, g.genre_name, p.list_price, 
              p.album_cover_url, p.quantity
              FROM products p
              JOIN genres g ON p.genre_id = g.genre_id
              ORDER BY p.product_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Query error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Unable to retrieve products at this time.";
}

// Set page title for header
$pageTitle = "myDonut - Product Catalog";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .product-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .product-image {
            height: 300px;
            background-color: #eee;
            background-size: cover;
            background-position: center;
            border-radius: 10px 10px 10px 10px;
        }
        
        .product-details {
            padding: 1rem;
            flex-grow: 1;
        }
        
        .product-price {
            font-weight: bold;
            color: #ff6347;
            font-size: 1.2rem;
        }
        
        .inventory {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .inventory.low {
            color: #dc3545;
        }
        
        .inventory.medium {
            color: #ffc107;
        }
        
        .inventory.high {
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <section class="welcome-section">
            <h2>Our Music Collection</h2>
            <p>Browse our catalog of vinyl records and digital albums from top artists</p>
        </section>

        <?php if (isset($error)): ?>
            <div class="form-section">
                <p><?= $error ?></p>
            </div>
        <?php else: ?>
            <div class="form-section">
                <h2>Available Products</h2>
                
                <?php if (empty($products)): ?>
                    <p>No products found in the catalog.</p>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="card product-card">
                                <div class="product-image" style="background-image: url('<?= htmlspecialchars($product['album_cover_url']) ?>')"></div>
                                <div class="product-details">
                                    <h3><?= htmlspecialchars($product['product_name']) ?></h3>
                                    <p><?= htmlspecialchars($product['genre_name']) ?></p>
                                    <p class="product-price">$<?= number_format($product['list_price']/100, 2) ?></p>
                                    
                                    <?php
                                    // Determine inventory status
                                    $inventoryClass = 'low';
                                    $inventoryText = 'Low Stock';
                                    
                                    if ($product['quantity'] > 20) {
                                        $inventoryClass = 'high';
                                        $inventoryText = 'In Stock';
                                    } elseif ($product['quantity'] > 5) {
                                        $inventoryClass = 'medium';
                                        $inventoryText = 'Limited Stock';
                                    } elseif ($product['quantity'] <= 0) {
                                        $inventoryClass = 'low';
                                        $inventoryText = 'Out of Stock';
                                    }
                                    ?>
                                    
                                    <p class="inventory <?= $inventoryClass ?>">
                                        <strong><?= $inventoryText ?></strong> 
                                        (<?= $product['quantity'] ?> remaining)
                                    </p>
                                    
                                    <a href="../Cart/add_to_cart.php?id=<?= $product['product_id'] ?>" class="btn mt-2">Add to Cart</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
    
</body>
</html>