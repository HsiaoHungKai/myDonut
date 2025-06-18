<?php
// Include database connection
require_once 'Sites/connect.php';

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

// Set page title
$pageTitle = "myDonut - Home";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .product-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .product-image {
            height: 200px;
            background-color: #eee;
            background-size: cover;
            background-position: center;
            border-radius: 10px 10px 0 0;
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
        
        .featured-section {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <?php include_once 'Sites/header.php'; ?>

    <main class="content">
        <section class="welcome-section">
            <h2>Welcome to myDonut Music Store</h2>
            <p>Discover the best vinyl records and digital albums from your favorite artists</p>
            <a href="Sites/Products/products.php" class="btn mt-3">Browse Full Catalog</a>
        </section>
    </main>

</body>
</html>