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
$genres = [];

// Fetch all available genres
try {
    $query = "SELECT genre_id, genre_name FROM genres ORDER BY genre_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Genre fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Error loading genres.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $product_name = trim($_POST['product_name'] ?? '');
    $genre_id = $_POST['genre_id'] ?? '';
    $list_price = $_POST['list_price'] ?? '';
    $album_cover_url = trim($_POST['album_cover_url'] ?? '');
    $quantity = $_POST['quantity'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($product_name)) {
        $errors[] = "Product name is required.";
    }
    
    if (empty($genre_id) || !is_numeric($genre_id)) {
        $errors[] = "Please select a valid genre.";
    }
    
    if (empty($list_price) || !is_numeric($list_price) || $list_price <= 0) {
        $errors[] = "Please enter a valid price (greater than 0).";
    }
    
    if (empty($quantity) || !is_numeric($quantity) || $quantity < 0) {
        $errors[] = "Please enter a valid quantity (0 or greater).";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Generate new product_id
            $productIdQuery = "SELECT ISNULL(MAX(product_id), 0) + 1 AS new_product_id FROM products";
            $productIdStmt = $conn->prepare($productIdQuery);
            $productIdStmt->execute();
            $new_product_id = $productIdStmt->fetch(PDO::FETCH_ASSOC)['new_product_id'];
            
            // Verify genre exists
            $genreCheckQuery = "SELECT genre_id FROM genres WHERE genre_id = ?";
            $genreCheckStmt = $conn->prepare($genreCheckQuery);
            $genreCheckStmt->execute([$genre_id]);
            
            if (!$genreCheckStmt->fetch()) {
                throw new Exception("Selected genre does not exist.");
            }
            
            // Convert price to cents (multiply by 100)
            $price_in_cents = (int)round($list_price * 100);
            
            // Insert new product
            $insertQuery = "INSERT INTO products (product_id, product_name, genre_id, list_price, album_cover_url, quantity) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->execute([
                $new_product_id,
                $product_name,
                $genre_id,
                $price_in_cents,
                $album_cover_url,
                $quantity
            ]);
            
            // Commit transaction
            $conn->commit();
            
            $success = "Product has been successfully added with ID #$new_product_id!";
            
            // Clear form data on success
            $product_name = '';
            $genre_id = '';
            $list_price = '';
            $album_cover_url = '';
            $quantity = '';
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Product add error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        } catch (PDOException $e) {
            // Rollback transaction
            $conn->rollback();
            $error = "Database error occurred. Please try again.";
            error_log("Database error in product_add: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        }
    }
}

$pageTitle = "Add New Product - myDonut Staff Panel";
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
        .add-product-container {
            max-width: 800px;
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
        
        .price-input-group {
            display: flex;
            align-items: center;
        }
        
        .price-input-group span {
            background-color: #e9ecef;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-right: none;
            border-radius: 5px 0 0 5px;
            font-weight: bold;
        }
        
        .price-input-group input {
            border-radius: 0 5px 5px 0;
            border-left: none;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 0.5rem;
            border-radius: 5px;
            display: none;
        }
        
        @media (max-width: 768px) {
            .add-product-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
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
        <div class="add-product-container">
            <a href="products_list.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Product List
            </a>
            
            <div class="page-header">
                <h2>Add New Product</h2>
                <p>Fill in the details below to add a new product to the catalog</p>
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
                <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>">
                    <div class="form-group">
                        <label for="product_name">Product Name <span class="required">*</span></label>
                        <input type="text" 
                               id="product_name" 
                               name="product_name" 
                               value="<?= htmlspecialchars($product_name ?? '') ?>" 
                               required 
                               maxlength="255"
                               placeholder="Enter product name">
                        <small>Enter the full name of the product/album</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="genre_id">Genre <span class="required">*</span></label>
                        <select id="genre_id" name="genre_id" required>
                            <option value="">Select a genre...</option>
                            <?php foreach ($genres as $genre): ?>
                                <option value="<?= $genre['genre_id'] ?>" 
                                        <?= (isset($genre_id) && $genre_id == $genre['genre_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($genre['genre_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Choose the music genre for this product</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="list_price">Price <span class="required">*</span></label>
                        <div class="price-input-group">
                            <input type="number" 
                                   id="list_price" 
                                   name="list_price" 
                                   value="<?= htmlspecialchars($list_price ?? '') ?>" 
                                   step="0.01" 
                                   min="0.01" 
                                   required 
                                   placeholder="0.00">
                        </div>
                        <small>Enter the selling price in dollars (e.g., 19.99)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="album_cover_url">Album Cover URL</label>
                        <input type="url" 
                               id="album_cover_url" 
                               name="album_cover_url" 
                               value="<?= htmlspecialchars($album_cover_url ?? '') ?>" 
                               maxlength="500"
                               placeholder="https://example.com/image.jpg">
                        <small>Enter the URL for the album cover image (optional)</small>
                        <img id="image_preview" class="image-preview" alt="Image preview">
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Initial Stock Quantity <span class="required">*</span></label>
                        <input type="number" 
                               id="quantity" 
                               name="quantity" 
                               value="<?= htmlspecialchars($quantity ?? '') ?>" 
                               min="0" 
                               required 
                               placeholder="0">
                        <small>Enter the initial stock quantity (use 0 if out of stock)</small>
                    </div>
                    
                    <div class="form-actions">
                        <a href="products_list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        // Image preview functionality
        document.getElementById('album_cover_url').addEventListener('input', function() {
            const imageUrl = this.value;
            const preview = document.getElementById('image_preview');
            
            if (imageUrl && isValidUrl(imageUrl)) {
                preview.src = imageUrl;
                preview.style.display = 'block';
                preview.onerror = function() {
                    this.style.display = 'none';
                };
            } else {
                preview.style.display = 'none';
            }
        });
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const productName = document.getElementById('product_name').value.trim();
            const genreId = document.getElementById('genre_id').value;
            const price = document.getElementById('list_price').value;
            const quantity = document.getElementById('quantity').value;
            
            if (!productName) {
                alert('Please enter a product name.');
                e.preventDefault();
                return;
            }
            
            if (!genreId) {
                alert('Please select a genre.');
                e.preventDefault();
                return;
            }
            
            if (!price || parseFloat(price) <= 0) {
                alert('Please enter a valid price greater than 0.');
                e.preventDefault();
                return;
            }
            
            if (!quantity || parseInt(quantity) < 0) {
                alert('Please enter a valid quantity (0 or greater).');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>