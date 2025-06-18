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

// Check if order ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    
    try {
        // Start transaction for data integrity
        $conn->beginTransaction();
        
        // First check if the order exists
        $query = "SELECT o.order_id, c.customer_name 
                  FROM orders o 
                  JOIN customers c ON o.customer_id = c.customer_id 
                  WHERE o.order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$order_id]);
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $conn->rollback();
            header("Location: orders_list.php?error=" . urlencode("Order not found."));
            exit();
        }
        
        // Get all order items to restore product quantities
        $query = "SELECT oi.product_id, oi.quantity, p.product_name
                  FROM order_items oi
                  JOIN products p ON oi.product_id = p.product_id
                  WHERE oi.order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$order_id]);
        
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Restore product quantities back to inventory
        foreach ($order_items as $item) {
            $updateQuery = "UPDATE products SET quantity = quantity + ? WHERE product_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Delete order items first (due to foreign key constraint)
        $query = "DELETE FROM order_items WHERE order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$order_id]);
        
        // Delete the order
        $query = "DELETE FROM orders WHERE order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$order_id]);
        
        // Commit the transaction
        $conn->commit();
        
        header("Location: orders_list.php?status=deleted&id=" . $order_id);
        exit();
        
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $conn->rollback();
        error_log("Order delete error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        header("Location: orders_list.php?error=" . urlencode("Failed to delete order. Please try again."));
        exit();
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        error_log("Order delete error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        header("Location: orders_list.php?error=" . urlencode("Failed to delete order. Please try again."));
        exit();
    }
} else {
    header("Location: orders_list.php?error=" . urlencode("Invalid order selected for deletion."));
    exit();
}
?>