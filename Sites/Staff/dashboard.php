<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../connect.php';

// Get staff role for permission checks
$staffRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$displayName = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'Staff Member';

// Initialize dashboard data
$dashboardData = [
    'total_orders' => 0,
    'total_revenue' => 0,
    'total_customers' => 0,
    'total_products' => 0,
    'recent_orders' => [],
    'top_products' => [],
    'daily_sales' => [],
    'genre_sales' => [],
    'customer_activity' => [],
    'low_stock_products' => []
];

try {
    // Basic Statistics
    $statsQueries = [
        'total_orders' => "SELECT COUNT(*) as count FROM orders",
        'total_customers' => "SELECT COUNT(*) as count FROM customers",
        'total_products' => "SELECT COUNT(*) as count FROM products"
    ];
    
    foreach ($statsQueries as $key => $query) {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $dashboardData[$key] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    // Total Revenue
    $revenueQuery = "SELECT SUM(oi.quantity * p.list_price) as total_revenue 
                    FROM order_items oi 
                    INNER JOIN products p ON oi.product_id = p.product_id";
    $stmt = $conn->prepare($revenueQuery);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $dashboardData['total_revenue'] = $result['total_revenue'] ?? 0;
    
    // Recent Orders (Last 10)
    $recentOrdersQuery = "SELECT TOP 10 o.order_id, o.order_date, c.customer_name,
                         SUM(oi.quantity * p.list_price) as order_total,
                         COUNT(oi.item_id) as item_count
                         FROM orders o
                         INNER JOIN customers c ON o.customer_id = c.customer_id
                         LEFT JOIN order_items oi ON o.order_id = oi.order_id
                         LEFT JOIN products p ON oi.product_id = p.product_id
                         GROUP BY o.order_id, o.order_date, c.customer_name
                         ORDER BY o.order_date DESC";
    $stmt = $conn->prepare($recentOrdersQuery);
    $stmt->execute();
    $dashboardData['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Selling Products
    $topProductsQuery = "SELECT TOP 5 p.product_name, p.album_cover_url,
                        SUM(oi.quantity) as total_sold,
                        SUM(oi.quantity * p.list_price) as revenue
                        FROM products p
                        INNER JOIN order_items oi ON p.product_id = oi.product_id
                        GROUP BY p.product_id, p.product_name, p.album_cover_url
                        ORDER BY total_sold DESC";
    $stmt = $conn->prepare($topProductsQuery);
    $stmt->execute();
    $dashboardData['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily Sales (Last 30 days)
    $dailySalesQuery = "SELECT 
                   CAST(o.order_date AS DATE) as day,
                   COUNT(*) as order_count,
                   SUM(oi.quantity * p.list_price) as revenue
                   FROM orders o
                   INNER JOIN order_items oi ON o.order_id = oi.order_id
                   INNER JOIN products p ON oi.product_id = p.product_id
                   WHERE o.order_date >= DATEADD(day, -30, GETDATE())
                   GROUP BY CAST(o.order_date AS DATE)
                   ORDER BY day DESC";
    $stmt = $conn->prepare($dailySalesQuery);
    $stmt->execute();
    $dashboardData['daily_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sales by Genre
    $genreSalesQuery = "SELECT g.genre_name,
                       SUM(oi.quantity) as units_sold,
                       SUM(oi.quantity * p.list_price) as revenue
                       FROM genres g
                       INNER JOIN products p ON g.genre_id = p.genre_id
                       INNER JOIN order_items oi ON p.product_id = oi.product_id
                       GROUP BY g.genre_id, g.genre_name
                       ORDER BY revenue DESC";
    $stmt = $conn->prepare($genreSalesQuery);
    $stmt->execute();
    $dashboardData['genre_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Customer Activity (Recent logins)
    $customerActivityQuery = "SELECT TOP 10 c.customer_name, ca.last_login, ca.username
                             FROM customers c
                             INNER JOIN customer_auth ca ON c.customer_id = ca.customer_id
                             WHERE ca.last_login IS NOT NULL
                             ORDER BY ca.last_login DESC";
    $stmt = $conn->prepare($customerActivityQuery);
    $stmt->execute();
    $dashboardData['customer_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low Stock Products
    $lowStockQuery = "SELECT product_name, quantity, list_price
                     FROM products 
                     WHERE quantity <= 5 AND quantity > 0
                     ORDER BY quantity ASC";
    $stmt = $conn->prepare($lowStockQuery);
    $stmt->execute();
    $dashboardData['low_stock_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "Unable to load dashboard data at this time.";
}

$pageTitle = "Analytics Dashboard - myDonut Staff Panel";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #343a40, #212529);
            padding: 2rem;
            border-radius: 15px;
            color: white;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 4px solid #ff6347;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .stat-card .value {
            color: #ff6347;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .subtitle {
            color: #999;
            font-size: 0.85rem;
        }
        
        .stat-card:nth-child(1) { border-left-color: #007bff; }
        .stat-card:nth-child(1) .value { color: #007bff; }
        
        .stat-card:nth-child(2) { border-left-color: #28a745; }
        .stat-card:nth-child(2) .value { color: #28a745; }
        
        .stat-card:nth-child(3) { border-left-color: #ffc107; }
        .stat-card:nth-child(3) .value { color: #ffc107; }
        
        .stat-card:nth-child(4) { border-left-color: #dc3545; }
        .stat-card:nth-child(4) .value { color: #dc3545; }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .section-header h3 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-header i {
            color: #ff6347;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        
        .data-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .product-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-good {
            background: #d4edda;
            color: #155724;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-user {
            font-weight: bold;
            color: #333;
        }
        
        .activity-time {
            color: #666;
            font-size: 0.9rem;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            padding: 2rem;
            font-style: italic;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h2><i class="fas fa-chart-line"></i> Analytics Dashboard</h2>
                <p>Real-time insights into your business performance</p>
            </div>
            
            <!-- Key Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="value"><?= number_format($dashboardData['total_orders']) ?></div>
                    <div class="subtitle">All time orders</div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value">$<?= number_format($dashboardData['total_revenue'] / 100, 0) ?></div>
                    <div class="subtitle">Total sales</div>
                </div>
                <div class="stat-card">
                    <h3>Total Customers</h3>
                    <div class="value"><?= number_format($dashboardData['total_customers']) ?></div>
                    <div class="subtitle">Registered users</div>
                </div>
                <div class="stat-card">
                    <h3>Products</h3>
                    <div class="value"><?= number_format($dashboardData['total_products']) ?></div>
                    <div class="subtitle">In catalog</div>
                </div>
            </div>
            
            <!-- Charts and Tables Grid -->
            <div class="dashboard-grid">
                <!-- Daily Sales Chart -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3><i class="fas fa-chart-area"></i> Daily Sales Trend</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="dailySalesChart"></canvas>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($dashboardData['recent_orders'])): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardData['recent_orders'] as $order): ?>
                                        <tr>
                                            <td>#<?= $order['order_id'] ?></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td>$<?= number_format(($order['order_total'] ?? 0) / 100, 2) ?></td>
                                            <td><?= date('M j', strtotime($order['order_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No recent orders found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Second Row -->
            <div class="dashboard-grid">
                <!-- Top Products -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3><i class="fas fa-star"></i> Top Selling Products</h3>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($dashboardData['top_products'])): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Units Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardData['top_products'] as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <?php if (!empty($product['album_cover_url'])): ?>
                                                        <img src="<?= htmlspecialchars($product['album_cover_url']) ?>" 
                                                             alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                                             class="product-thumbnail">
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($product['product_name']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= number_format($product['total_sold']) ?></td>
                                            <td>$<?= number_format($product['revenue'] / 100, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No sales data available</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Genre Sales Chart -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3><i class="fas fa-music"></i> Sales by Genre</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="genreSalesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Third Row -->
            <div class="dashboard-grid">
                <!-- Low Stock Alert -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($dashboardData['low_stock_products'])): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Stock Level</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardData['low_stock_products'] as $product): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                                            <td><?= $product['quantity'] ?> units</td>
                                            <td>
                                                <?php 
                                                $status = $product['quantity'] <= 2 ? 'low' : ($product['quantity'] <= 5 ? 'medium' : 'good');
                                                $statusText = $product['quantity'] <= 2 ? 'Critical' : ($product['quantity'] <= 5 ? 'Low' : 'Good');
                                                ?>
                                                <span class="status-badge status-<?= $status ?>"><?= $statusText ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">All products are well stocked</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Customer Activity -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3><i class="fas fa-users"></i> Recent Customer Activity</h3>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($dashboardData['customer_activity'])): ?>
                            <?php foreach ($dashboardData['customer_activity'] as $activity): ?>
                                <div class="activity-item">
                                    <div>
                                        <div class="activity-user"><?= htmlspecialchars($activity['customer_name']) ?></div>
                                        <div class="activity-time">@<?= htmlspecialchars($activity['username']) ?></div>
                                    </div>
                                    <div class="activity-time">
                                        <?= $activity['last_login'] ? date('M j, g:i A', strtotime($activity['last_login'])) : 'Never' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">No recent customer activity</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>
    </main>
    
    <script>
        // Genre Sales Chart
        const genreSalesCtx = document.getElementById('genreSalesChart').getContext('2d');
        const genreSalesData = <?= json_encode($dashboardData['genre_sales']) ?>;
        
        new Chart(genreSalesCtx, {
            type: 'doughnut',
            data: {
                labels: genreSalesData.map(item => item.genre_name),
                datasets: [{
                    data: genreSalesData.map(item => (item.revenue || 0) / 100),
                    backgroundColor: [
                        '#ff6347', '#007bff', '#28a745', '#ffc107', 
                        '#dc3545', '#6f42c1', '#20c997', '#fd7e14'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
        
        // Daily Sales Chart using actual data
        const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
        const dailySalesData = <?= json_encode($dashboardData['daily_sales']) ?>;

        new Chart(dailySalesCtx, {
            type: 'line',
            data: {
                labels: dailySalesData.map(item => {
                    const date = new Date(item.day);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue ($)',
                    data: dailySalesData.map(item => (item.revenue || 0) / 100),
                    borderColor: '#ff6347',
                    backgroundColor: 'rgba(255, 99, 71, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>