<?php
// Include the database connection
require_once '../connect.php';

// Include the header
include_once '../header.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="form-section text-center">';
    echo '<h2>Please log in to view your cart</h2>';
    echo '<p>You need to be logged in to access your shopping cart.</p>';
    echo '<a href="/Sites/login.php" class="btn">Login</a>';
    echo '</div>';
} else {
    $customer_id = $_SESSION['user_id'];
    
    // Handle quantity updates
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantity'] as $item_id => $quantity) {
            if ($quantity > 0) {
                // Update quantity
                $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE customer_id = ? AND item_id = ?");
                $stmt->execute([$quantity, $customer_id, $item_id]);
            } else {
                // Remove item if quantity is 0
                $stmt = $conn->prepare("DELETE FROM cart_items WHERE customer_id = ? AND item_id = ?");
                $stmt->execute([$customer_id, $item_id]);
            }
        }
        // Redirect to refresh the page and avoid form resubmission
        header("Location: cart.php?updated=true");
        exit;
    }
    
    // Handle item removal
    if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
        $item_id = $_GET['remove'];
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE customer_id = ? AND item_id = ?");
        $stmt->execute([$customer_id, $item_id]);
        header("Location: cart.php?removed=true");
        exit;
    }
    
    // Fetch cart items with product details
    try {
        $stmt = $conn->prepare("
            SELECT ci.item_id, ci.quantity, ci.added_at, 
                   p.product_id, p.product_name, p.list_price, p.album_cover_url
            FROM cart_items ci
            INNER JOIN products p ON ci.product_id = p.product_id
            WHERE ci.customer_id = ?
            ORDER BY ci.added_at DESC
        ");
        $stmt->execute([$customer_id]);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate cart totals
        $total_items = 0;
        $subtotal = 0;
        
        foreach ($cart_items as $item) {
            $total_items += $item['quantity'];
            $subtotal += $item['quantity'] * $item['list_price'];
        }
    } catch (PDOException $e) {
        error_log("Cart query error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
        $error_message = "An error occurred while retrieving your cart. Please try again later.";
    }
?>

<main>
    <div class="form-section">
        <h2>Your Shopping Cart</h2>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">
                Your cart has been updated successfully.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['removed'])): ?>
            <div class="alert alert-info">
                Item has been removed from your cart.
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?= $error_message ?>
            </div>
        <?php elseif (empty($cart_items)): ?>
            <div class="empty-cart">
                <p>Your cart is empty. Browse our catalog to add items.</p>
                <a href="/Sites/Products/products.php" class="btn mt-2">Browse Products</a>
            </div>
        <?php else: ?>
            <form method="post" action="cart.php">
                <div class="cart-table-container">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <tr>
                                    <td class="product-info">
                                        <?php if (!empty($item['album_cover_url'])): ?>
                                            <img src="<?= htmlspecialchars($item['album_cover_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="product-thumbnail">
                                        <?php endif; ?>
                                        <div>
                                            <h3><?= htmlspecialchars($item['product_name']) ?></h3>
                                            <span class="added-date">Added: <?= date('M j, Y', strtotime($item['added_at'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="price">$<?= number_format($item['list_price']/100, 2) ?></td>
                                    <td class="quantity">
                                        <input type="number" name="quantity[<?= $item['item_id'] ?>]" value="<?= $item['quantity'] ?>" min="0" class="quantity-input">
                                    </td>
                                    <td class="total">$<?= number_format(($item['quantity'] * $item['list_price'])/100, 2) ?></td>
                                    <td class="actions">
                                        <a href="cart.php?remove=<?= $item['item_id'] ?>" class="remove-btn" onclick="return confirm('Are you sure you want to remove this item?')">Remove</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="cart-summary">
                                    <strong>Items in cart:</strong> <?= $total_items ?>
                                </td>
                                <td class="text-right"><strong>Subtotal:</strong></td>
                                <td colspan="2" class="cart-subtotal">$<?= number_format($subtotal/100, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="cart-actions">
                    <button type="submit" name="update_cart" class="btn">Update Cart</button>
                    <a href="/Sites/Products/products.php" class="btn btn-secondary">Continue Shopping</a>
                    <a href="/Sites/Cart/place_order.php" class="btn">Place Order</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<style>
    .alert {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .empty-cart {
        text-align: center;
        padding: 30px;
    }
    
    .cart-table-container {
        overflow-x: auto;
    }
    
    .cart-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .cart-table th, .cart-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .cart-table th {
        background-color: #f8f9fa;
        text-align: left;
    }
    
    .product-info {
        display: flex;
        align-items: center;
    }
    
    .product-thumbnail {
        width: 60px;
        height: 60px;
        object-fit: cover;
        margin-right: 15px;
        border-radius: 5px;
    }
    
    .added-date {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .quantity-input {
        width: 60px;
        padding: 5px;
        text-align: center;
    }
    
    .remove-btn {
        color: #dc3545;
        text-decoration: none;
    }
    
    .remove-btn:hover {
        text-decoration: underline;
    }
    
    .cart-summary, .cart-subtotal {
        font-size: 1.1rem;
    }
    
    .text-right {
        text-align: right;
    }
    
    .cart-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
        .cart-actions {
            flex-direction: column;
        }
        
        .cart-actions .btn {
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>

<?php
}
// Close the content div from header
echo '</div>';
?>
