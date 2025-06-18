<?php
// Include the database connection
require_once '../connect.php';

// Set page title for the header
$pageTitle = "All Products";

// Include header
include '../header.php';

// Prepare and execute query to get all products
$sql = "SELECT p.product_id, p.product_name, p.list_price, c.category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        ORDER BY p.product_id";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Query error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error_message = "Error retrieving products";
}
?>

<main class="content">
    <div class="welcome-section">
        <h2>Product Catalog</h2>
        <p>Browse our complete product listing</p>
    </div>
    
    <?php if(isset($error_message)): ?>
        <div class="welcome-section" style="background: linear-gradient(135deg, #ffebee, #ffcdd2);">
            <h2 style="color: #c62828;"><?php echo $error_message; ?></h2>
            <p>Please try again later or contact support.</p>
        </div>
    <?php endif; ?>

    <div class="form-section">
        <div class="mb-3 text-right">
            <a href="add_product.php" class="btn">Add New Product</a>
            <a href="search_product.php" class="btn btn-secondary">Search Products</a>
        </div>
        
        <?php if(!empty($products)): ?>
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 12px 15px; text-align: left;">ID</th>
                        <th style="padding: 12px 15px; text-align: left;">Product Name</th>
                        <th style="padding: 12px 15px; text-align: left;">Category</th>
                        <th style="padding: 12px 15px; text-align: right;">Price</th>
                        <th style="padding: 12px 15px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $product): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px 15px;"><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td style="padding: 12px 15px;"><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td style="padding: 12px 15px;"><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td style="padding: 12px 15px; text-align: right;">$<?php echo number_format($product['list_price'], 2); ?></td>
                        <td style="padding: 12px 15px; text-align: center;">
                            <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" style="color: #0d6efd; text-decoration: none; margin-right: 10px;">Edit</a>
                            <a href="delete_product.php?id=<?php echo $product['product_id']; ?>" 
                               style="color: #dc3545; text-decoration: none;"
                               onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="text-center p-3">
                <p style="color: #6c757d; font-style: italic;">No products found in the database.</p>
                <a href="add_product.php" class="btn mt-2">Add Your First Product</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../footer.php'; ?>