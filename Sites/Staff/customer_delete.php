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

// Check if customer ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
    
    try {
        // Start transaction for data integrity
        $conn->beginTransaction();
        
        // First check if the customer exists
        $query = "SELECT customer_id, customer_name, email FROM customers WHERE customer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$customer_id]);
        
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            $conn->rollback();
            header("Location: customers_list.php?error=" . urlencode("Customer not found."));
            exit();
        }
        
        // Check if customer has any orders (business rule: may not want to delete customers with orders)
        $ordersCheckQuery = "SELECT COUNT(*) as order_count FROM orders WHERE customer_id = ?";
        $ordersCheckStmt = $conn->prepare($ordersCheckQuery);
        $ordersCheckStmt->execute([$customer_id]);
        $orderCount = $ordersCheckStmt->fetch(PDO::FETCH_ASSOC)['order_count'];
        
        if ($orderCount > 0) {
            $conn->rollback();
            header("Location: customers_list.php?error=" . urlencode("Cannot delete customer with existing orders. Customer has $orderCount order(s)."));
            exit();
        }
        
        // Check if customer has any cart items
        $cartCheckQuery = "SELECT COUNT(*) as cart_count FROM cart_items WHERE customer_id = ?";
        $cartCheckStmt = $conn->prepare($cartCheckQuery);
        $cartCheckStmt->execute([$customer_id]);
        $cartCount = $cartCheckStmt->fetch(PDO::FETCH_ASSOC)['cart_count'];
        
        // Delete cart items if any exist
        if ($cartCount > 0) {
            $deleteCartQuery = "DELETE FROM cart_items WHERE customer_id = ?";
            $deleteCartStmt = $conn->prepare($deleteCartQuery);
            $deleteCartStmt->execute([$customer_id]);
        }
        
        // Delete customer authentication record
        $deleteAuthQuery = "DELETE FROM customer_auth WHERE customer_id = ?";
        $deleteAuthStmt = $conn->prepare($deleteAuthQuery);
        $deleteAuthStmt->execute([$customer_id]);
        
        // Delete the customer
        $deleteCustomerQuery = "DELETE FROM customers WHERE customer_id = ?";
        $deleteCustomerStmt = $conn->prepare($deleteCustomerQuery);
        $deleteCustomerStmt->execute([$customer_id]);
        
        // Commit the transaction
        $conn->commit();
        
        header("Location: customers_list.php?status=deleted&id=" . $customer_id);
        exit();
        
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $conn->rollback();
        error_log("Customer delete error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        header("Location: customers_list.php?error=" . urlencode("Failed to delete customer. Please try again."));
        exit();
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        error_log("Customer delete error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        header("Location: customers_list.php?error=" . urlencode("Failed to delete customer. Please try again."));
        exit();
    }
} else {
    header("Location: customers_list.php?error=" . urlencode("Invalid customer selected for deletion."));
    exit();
}
?>