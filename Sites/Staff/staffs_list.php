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
$search = "";
$role_filter = "";
$sort_by = "staff_name";
$sort_order = "ASC";
$error = "";
$success = "";
$staffs = [];

// Process search and filter parameters
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['role_filter'])) {
    $role_filter = trim($_GET['role_filter']);
}

if (isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['staff_id', 'staff_name', 'email', 'phone', 'role'])) {
    $sort_by = $_GET['sort_by'];
}

if (isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC'])) {
    $sort_order = $_GET['sort_order'];
}

// Check for status messages
if (isset($_GET['status']) && $_GET['status'] === 'updated' && isset($_GET['id'])) {
    $success = "Staff member ID " . $_GET['id'] . " has been successfully updated.";
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted' && isset($_GET['id'])) {
    $success = "Staff member ID " . $_GET['id'] . " has been successfully deleted.";
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Fetch staff members with search and sort
try {
    $params = [];
    $sql_conditions = [];
    
    $base_query = "SELECT s.staff_id, s.staff_name, s.email, s.phone, sa.username, sa.role
                   FROM staffs s
                   LEFT JOIN staff_auth sa ON s.staff_id = sa.staff_id";
    
    // Add search condition if provided
    if (!empty($search)) {
        $sql_conditions[] = "(s.staff_name LIKE :search)";
        $params[':search'] = "%" . $search . "%";
    }
    
    // Add role filter if provided
    if (!empty($role_filter)) {
        $sql_conditions[] = "sa.role = :role_filter";
        $params[':role_filter'] = $role_filter;
    }
    
    // Combine conditions if any
    $sql_where = "";
    if (!empty($sql_conditions)) {
        $sql_where = " WHERE " . implode(" AND ", $sql_conditions);
    }
    
    // Add sorting (fix sort_by for role)
    $sort_column = $sort_by;
    if ($sort_by == 'role') {
        $sort_column = 'sa.role';
    } else {
        $sort_column = 's.' . $sort_by;
    }
    $sql_order = " ORDER BY " . $sort_column . " " . $sort_order;
    
    // Build final query
    $query = $base_query . $sql_where . $sql_order;
    
    // Prepare and execute query
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Staff fetch error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    $error = "An error occurred while retrieving staff members.";
}

// Get role statistics
$role_stats = [];
try {
    $roleQuery = "SELECT sa.role, COUNT(*) as count FROM staff_auth sa GROUP BY sa.role";
    $roleStmt = $conn->prepare($roleQuery);
    $roleStmt->execute();
    $role_stats = $roleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Role stats error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
}

// Set page title
$pageTitle = "Staff Management - myDonut Staff Panel";
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
        .staffs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .search-form {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin: 2rem 0;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        
        .search-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(248, 249, 250, 0.8);
            backdrop-filter: blur(10px);
            z-index: 0;
        }
        
        .search-form > * {
            position: relative;
            z-index: 1;
        }
        
        .search-input-wrapper {
            flex: 2;
            min-width: 250px;
            position: relative;
            margin-bottom: 0;
        }
        
        .search-form input[type="text"] {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            font-size: 1rem;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            color: #333;
        }
        
        .search-form input[type="text"]:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .search-form input[type="text"]::placeholder {
            color: #666;
            font-weight: 400;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1rem;
            pointer-events: none;
        }
        
        .genre-select-wrapper {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        
        .search-form select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            font-size: 1rem;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            color: #333;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 1.5rem;
            padding-right: 3rem;
        }
        
        .search-form select:focus {
            outline: none;
            border-color: #ff6347;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .search-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0rem;
        }
        
        .search-form button.btn {
            padding: 1rem 2rem;
            border: 2px solid #ff6347;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            background: #ff6347;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 99, 71, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .search-form .btn-secondary {
            padding: 1rem 1.5rem;
            border: 2px solid #6c757d;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            background: transparent;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            margin-bottom: 1rem;
        }
        
        .staffs-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .staffs-table th, .staffs-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .staffs-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: relative;
        }
        
        .staffs-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .staff-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.5rem;
            border-radius: 50%;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn:hover {
            background: #e9ecef;
        }
        
        .view-btn {
            color: #007bff;
        }
        
        .edit-btn {
            color: #28a745;
        }
        
        .delete-btn {
            color: #dc3545;
        }
        
        .sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .sort-icon {
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }
        
        .staff-id {
            font-weight: bold;
            color: #007bff;
        }
        
        .staff-name {
            font-weight: bold;
            color: #333;
        }
        
        .staff-email {
            color: #666;
            font-size: 0.9rem;
        }
        
        .staff-phone {
            color: #555;
            font-family: monospace;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: #dc3545;
            color: white;
        }
        
        .role-manager {
            background: #007bff;
            color: white;
        }
        
        .role-cashier {
            background: #28a745;
            color: white;
        }
        
        .role-inventory {
            background: #ffc107;
            color: #333;
        }
        
        .role-staff {
            background: #6c757d;
            color: white;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stats-card h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .stats-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #ff6347;
        }
        
        @media (max-width: 1024px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
                gap: 1.5rem;
            }
            
            .search-input-wrapper,
            .genre-select-wrapper {
                min-width: auto;
                flex: none;
            }
            
            .search-buttons {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .search-form {
                padding: 1.5rem;
                border-radius: 15px;
            }
            
            .search-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-form button.btn,
            .search-form .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Animation for form appearance */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .search-form {
            animation: slideInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <?php include_once '../header.php'; ?>

    <main class="content">
        <div class="container">
            <div class="staffs-header">
                <div>
                    <h2>Staff Management</h2>
                    <p>View, search, and manage staff members.</p>
                </div>
                
                <a href="staff_add.php" class="btn">
                    <i class="fas fa-plus"></i> Add New Staff Member
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="stats-cards">
                <div class="stats-card">
                    <h3>Total Staff</h3>
                    <div class="number"><?= count($staffs) ?></div>
                </div>
                <div class="stats-card">
                    <h3>Managers</h3>
                    <div class="number"><?= $role_stats['manager'] ?? 0 ?></div>
                </div>
                <div class="stats-card">
                    <h3>Cashiers</h3>
                    <div class="number"><?= $role_stats['cashier'] ?? 0 ?></div>
                </div>
                <div class="stats-card">
                    <h3>Admins</h3>
                    <div class="number"><?= $role_stats['admin'] ?? 0 ?></div>
                </div>
            </div>
            
            <form method="get" action="<?= $_SERVER['PHP_SELF'] ?>" class="search-form">
                <div class="search-input-wrapper">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search staff's name...">
                </div>
                
                <div class="genre-select-wrapper">
                    <select name="role_filter">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Administrator</option>
                        <option value="manager" <?= $role_filter == 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="cashier" <?= $role_filter == 'cashier' ? 'selected' : '' ?>>Cashier</option>
                        <option value="inventory" <?= $role_filter == 'inventory' ? 'selected' : '' ?>>Inventory Specialist</option>
                        <option value="staff" <?= $role_filter == 'staff' ? 'selected' : '' ?>>General Staff</option>
                    </select>
                </div>
                
                <div class="search-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if (!empty($search) || !empty($role_filter)): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="table-container">
                <table class="staffs-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&role_filter=<?= urlencode($role_filter) ?>&sort_by=staff_id&sort_order=<?= $sort_by == 'staff_id' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Staff ID
                                    <?php if ($sort_by == 'staff_id'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&role_filter=<?= urlencode($role_filter) ?>&sort_by=staff_name&sort_order=<?= $sort_by == 'staff_name' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Staff Name
                                    <?php if ($sort_by == 'staff_name'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&role_filter=<?= urlencode($role_filter) ?>&sort_by=email&sort_order=<?= $sort_by == 'email' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Email
                                    <?php if ($sort_by == 'email'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Phone</th>
                            <th>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?search=<?= urlencode($search) ?>&role_filter=<?= urlencode($role_filter) ?>&sort_by=role&sort_order=<?= $sort_by == 'role' && $sort_order == 'ASC' ? 'DESC' : 'ASC' ?>" class="sort-link">
                                    Role
                                    <?php if ($sort_by == 'role'): ?>
                                        <i class="fas fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Username</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staffs) > 0): ?>
                            <?php foreach ($staffs as $staff): ?>
                                <tr>
                                    <td class="staff-id">#<?= $staff['staff_id'] ?></td>
                                    <td class="staff-name"><?= htmlspecialchars($staff['staff_name']) ?></td>
                                    <td class="staff-email"><?= htmlspecialchars($staff['email']) ?></td>
                                    <td class="staff-phone"><?= htmlspecialchars($staff['phone']) ?></td>
                                    <td>
                                        <?php if (!empty($staff['role'])): ?>
                                            <span class="role-badge role-<?= $staff['role'] ?>">
                                                <?= ucfirst($staff['role']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge role-staff">No Role</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($staff['username'] ?? 'N/A') ?></td>
                                    <td>
                                        <div class="staff-actions">
                                            <a href="staff_view.php?id=<?= $staff['staff_id'] ?>" class="action-btn view-btn" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="staff_edit.php?id=<?= $staff['staff_id'] ?>" class="action-btn edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="action-btn delete-btn" 
                                                    onclick="confirmDelete(<?= $staff['staff_id'] ?>, '<?= addslashes(htmlspecialchars($staff['staff_name'])) ?>')" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    <p>No staff members found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <?php include_once '../footer.php'; ?>
    
    <script>
        function confirmDelete(staffId, staffName) {
            if (confirm(`Are you sure you want to delete staff member #${staffId} "${staffName}"?\n\nThis action cannot be undone and will also delete their login account.`)) {
                window.location.href = `staff_delete.php?id=${staffId}`;
            }
        }
    </script>
</body>
</html>