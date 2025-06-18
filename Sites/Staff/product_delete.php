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

// Check if product ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    try {
        // First check if the product exists and can be deleted (e.g., not in orders)
        $query = "SELECT product_id, product_name FROM products WHERE product_id = :product_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            header("Location: products-list.php?error=" . urlencode("Product not found."));
            exit();
        }
        
        // Check if product is in any orders (would require a join with order_items)
        // This is a simplified check - in a real system you might want to check more conditions
        $query = "SELECT COUNT(*) as count FROM order_items WHERE product_id = :product_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            header("Location: products-list.php?error=" . urlencode("Cannot delete product ID {$product_id}. It is associated with existing orders."));
            exit();
        }
        
        // Check if product is in any shopping carts
        $query = "SELECT COUNT(*) as count FROM cart_items WHERE product_id = :product_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            // Option 1: Prevent deletion
            // header("Location: products-list.php?error=" . urlencode("Cannot delete product ID {$product_id}. It is in active shopping carts."));
            // exit();
            
            // Option 2: Remove from carts then delete (used here)
            $query = "DELETE FROM cart_items WHERE product_id = :product_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
        }
        
        // Delete the product
        $query = "DELETE FROM products WHERE product_id = :product_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        
        header("Location: products-list.php?status=deleted&id=" . $product_id);
        exit();
        
    } catch (PDOException $e) {
        error_log("Product delete error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        header("Location: products-list.php?error=" . urlencode("Failed to delete product. Please try again."));
        exit();
    }
} else {
    header("Location: products-list.php?error=" . urlencode("Invalid product selected for deletion."));
    exit();
}
?>
