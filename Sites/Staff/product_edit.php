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
$product_id = 0;
$product = [];
$genres = [];
$error = "";
$success = "";

// Check if product ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
} else {
    // Redirect to products list if no ID provided
    header("Location: products_list.php?error=" . urlencode("No product selected for editing."));
    exit();
}

// Fetch genres for dropdown
try {
    $query = "SELECT genre_id, genre_name FROM genres ORDER BY genre_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Genre fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Error loading genre data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $product_name = trim($_POST['product_name']);
    $genre_id = (int)$_POST['genre_id'];
    $list_price = (int)($_POST['list_price'] * 100); // Convert dollars to cents for storage
    $quantity = (int)$_POST['quantity'];
    $album_cover_url = trim($_POST['album_cover_url']);
    
    if (empty($product_name)) {
        $error = "Product name is required.";
    } elseif ($list_price <= 0) {
        $error = "Price must be greater than zero.";
    } elseif ($quantity < 0) {
        $error = "Quantity cannot be negative.";
    } elseif (empty($album_cover_url)) {
        $error = "Album cover URL is required.";
    } else {
        try {
            // Update product data
            $query = "UPDATE products SET 
                      product_name = :product_name,
                      genre_id = :genre_id,
                      list_price = :list_price,
                      quantity = :quantity,
                      album_cover_url = :album_cover_url
                      WHERE product_id = :product_id";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':product_name', $product_name);
            $stmt->bindParam(':genre_id', $genre_id);
            $stmt->bindParam(':list_price', $list_price);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':album_cover_url', $album_cover_url);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            
            // Redirect to products list with success message
            header("Location: products_list.php?status=updated&id=" . $product_id);
            exit();
        } catch (PDOException $e) {
            error_log("Product update error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
            $error = "Failed to update product. Please try again.";
        }
    }
}

// Fetch product data
try {
    $query = "SELECT p.*, g.genre_name FROM products p
              JOIN genres g ON p.genre_id = g.genre_id
              WHERE p.product_id = :product_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        // Redirect if product not found
        header("Location: products_list.php?error=" . urlencode("Product not found."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Product fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Failed to load product data.";
}

// Set page title
$pageTitle = "Edit Product - myDonut Staff Panel";
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
            max-width: 800px;
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
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 0 0 3px rgba(255, 99, 71, 0.1);
        }
        
        .album-preview {
            margin-top: 1rem;
            display: flex;
            align-items: center;
        }
        
        .album-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 1rem;
            border: 1px solid #ddd;
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
            .form-row {
                flex-direction: column;
                gap: 0;
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
                    <h2>Edit Product</h2>
                    <p>Update product information for ID: <?= $product_id ?></p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?= $_SERVER['PHP_SELF'] . '?id=' . $product_id ?>">
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="genre_id">Genre</label>
                            <select id="genre_id" name="genre_id" required>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?= $genre['genre_id'] ?>" <?= $product['genre_id'] == $genre['genre_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($genre['genre_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="list_price">Price ($)</label>
                            <input type="number" id="list_price" name="list_price" step="0.01" min="0" value="<?= number_format($product['list_price']/100, 2) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity in Stock</label>
                            <input type="number" id="quantity" name="quantity" min="0" value="<?= $product['quantity'] ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="album_cover_url">Album Cover URL</label>
                        <input type="url" id="album_cover_url" name="album_cover_url" value="<?= htmlspecialchars($product['album_cover_url']) ?>" required>
                        
                        <div class="album-preview">
                            <img id="cover-preview" src="<?= htmlspecialchars($product['album_cover_url']) ?>" alt="Album cover preview">
                            <div>
                                <p>Image Preview</p>
                                <small>Make sure the URL points to a valid image file</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <a href="products_list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        // Update image preview when URL changes
        document.getElementById('album_cover_url').addEventListener('input', function() {
            const previewImg = document.getElementById('cover-preview');
            previewImg.src = this.value || '/images/placeholder.png';
        });
    </script>
</body>
</html>