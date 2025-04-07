<?php
require_once 'check_admin.php';
require_once '../config/database.php';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header("Location: users.php?error=Invalid user ID");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $status = $_POST['status'];

        // Basic validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            throw new Exception("Required fields cannot be empty");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already exists");
        }

        // Update user
        $stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, status = ? 
            WHERE id = ? AND role != 'admin'
        ");
        $stmt->execute([$first_name, $last_name, $email, $status, $user_id]);

        // Handle password update if provided
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            if (strlen($password) < 6) {
                throw new Exception("Password must be at least 6 characters long");
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role != 'admin'");
            $stmt->execute([$hashed_password, $user_id]);
        }

        // Redirect back to users page with success message
        header("Location: users.php?success=User updated successfully");
        exit();

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: users.php?error=User not found");
        exit();
    }
} catch(PDOException $e) {
    header("Location: users.php?error=Error fetching user data");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Copy all styles from dashboard.php */
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

        /* ... (copy all styles from dashboard.php) ... */

        /* Core layout styles */
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

        /* Additional styles for edit user page */
        .edit-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h1 {
            margin: 0;
            color: var(--dark-grey);
        }

        .form-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
            <div class="edit-form">
                <div class="form-header">
                    <h1>Edit User</h1>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="banned" <?php echo $user['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password">
                    </div>

                    <div class="form-actions">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
    </script>
</body>
</html> 