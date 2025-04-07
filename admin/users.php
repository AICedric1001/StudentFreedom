<?php
require_once 'check_admin.php';
require_once '../config/database.php';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'delete':
                    if (isset($_POST['user_id'])) {
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                        $stmt->execute([$_POST['user_id']]);
                        $success = "User deleted successfully";
                    }
                    break;

                case 'ban':
                    if (isset($_POST['user_id'])) {
                        $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = ? AND role != 'admin'");
                        $stmt->execute([$_POST['user_id']]);
                        $success = "User banned successfully";
                    }
                    break;

                case 'unban':
                    if (isset($_POST['user_id'])) {
                        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role != 'admin'");
                        $stmt->execute([$_POST['user_id']]);
                        $success = "User unbanned successfully";
                    }
                    break;
            }
        }
    } catch(PDOException $e) {
        error_log("User action error: " . $e->getMessage());
        $error = "An error occurred while performing the action";
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$query = "SELECT * FROM users WHERE role != 'admin'";
$params = [];

if ($status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$query .= " ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $error = "Error loading users";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Core layout styles */
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f5f6fa;
            --dark-grey: #2c3e50;
            --light-grey: #ecf0f1;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --sidebar-width: 250px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--secondary-color);
            color: var(--dark-grey);
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .admin-header {
            padding: 20px;
            background: var(--primary-color);
            color: white;
        }

        .admin-title {
            font-size: 20px;
            font-weight: bold;
        }

        .admin-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-group {
            margin-bottom: 10px;
        }

        .nav-group-title {
            padding: 10px 20px;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            font-weight: bold;
        }

        .nav-group ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-group li {
            margin: 0;
        }

        .nav-group a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark-grey);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .nav-group a:hover {
            background: var(--light-grey);
        }

        .nav-group i {
            width: 20px;
            margin-right: 10px;
        }

        .nav-dropdown-items {
            display: none;
            background: var(--light-grey);
        }

        .nav-dropdown.active .nav-dropdown-items {
            display: block;
        }

        /* Main content styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        /* Additional styles for users page */
        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .users-table table {
            margin: 0;
        }

        .users-table th {
            background: var(--light-grey);
            border-bottom: 2px solid var(--dark-grey);
            color: var(--dark-grey);
        }

        .users-table td {
            vertical-align: middle;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: var(--success-color);
            color: white;
        }

        .status-banned {
            background: var(--danger-color);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-buttons button {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-view {
            background: var(--primary-color);
            color: white;
        }

        .btn-edit {
            background: var(--warning-color);
            color: white;
        }

        .btn-ban {
            background: var(--danger-color);
            color: white;
        }

        .btn-unban {
            background: var(--success-color);
            color: white;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .action-buttons button:hover {
            opacity: 0.9;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .toggle-sidebar {
                display: block;
            }

            .users-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="admin-header">
                <div class="admin-title">Admin Panel</div>
            </div>
            <ul class="admin-nav">
                <li class="nav-group">
                    <div class="nav-group-title">Main</div>
                    <ul>
                        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    </ul>
                </li>

                <li class="nav-group">
                    <div class="nav-group-title">Content Management</div>
                    <ul>
                        <li class="nav-dropdown">
                            <a><i class="fas fa-file-alt"></i> Posts</a>
                            <ul class="nav-dropdown-items">
                                <li><a href="posts.php">All Posts</a></li>
                                <li><a href="posts.php?status=pending">Pending Posts</a></li>
                                <li><a href="posts.php?status=reported">Reported Posts</a></li>
                            </ul>
                        </li>
                        <li class="nav-dropdown">
                            <a><i class="fas fa-comments"></i> Comments</a>
                            <ul class="nav-dropdown-items">
                                <li><a href="comments.php">All Comments</a></li>
                                <li><a href="comments.php?status=reported">Reported Comments</a></li>
                            </ul>
                        </li>
                        <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                    </ul>
                </li>

                <li class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <ul>
                        <li><a href="users.php"><i class="fas fa-users"></i> All Users</a></li>
                        <li><a href="users.php?status=banned"><i class="fas fa-user-slash"></i> Banned Users</a></li>
                        <li><a href="users.php?status=reported"><i class="fas fa-user-shield"></i> Reported Users</a></li>
                    </ul>
                </li>

                <li class="nav-group">
                    <div class="nav-group-title">Communication</div>
                    <ul>
                        <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                        <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
                    </ul>
                </li>

                <li class="nav-group">
                    <div class="nav-group-title">System</div>
                    <ul>
                        <li><a href="logs.php"><i class="fas fa-history"></i> System Logs</a></li>
                        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="users-header">
                <h1>User Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="banned" <?php echo $status === 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="users-table">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo isset($user['status']) ? $user['status'] : 'active'; ?>">
                                    <?php echo isset($user['status']) ? ucfirst($user['status']) : 'Active'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-view" onclick="viewUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!isset($user['status']) || $user['status'] === 'active'): ?>
                                        <button class="btn-ban" onclick="banUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-unban" onclick="unbanUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_user.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Handle dropdown toggles
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.nav-dropdown > a');
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.parentElement;
                parent.classList.toggle('active');
            });
        });
    });

    // User action functions
    function viewUser(userId) {
        window.location.href = `view_user.php?id=${userId}`;
    }

    function editUser(userId) {
        window.location.href = `edit_user.php?id=${userId}`;
    }

    function banUser(userId) {
        if (confirm('Are you sure you want to ban this user?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function unbanUser(userId) {
        if (confirm('Are you sure you want to unban this user?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="unban">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html> 