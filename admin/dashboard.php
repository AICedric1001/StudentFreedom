<?php
require_once 'check_admin.php';
require_once '../config/database.php';

// Fetch dashboard statistics
try {
    // Total users count
    $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Total posts count
    $stmt = $conn->query("SELECT COUNT(*) as total_posts FROM posts");
    $total_posts = $stmt->fetch(PDO::FETCH_ASSOC)['total_posts'];

    // Total comments count
    $stmt = $conn->query("SELECT COUNT(*) as total_comments FROM comments");
    $total_comments = $stmt->fetch(PDO::FETCH_ASSOC)['total_comments'];

    // Recent posts (last 5)
    $stmt = $conn->query("
        SELECT p.*, u.first_name, u.last_name, c.name as category_name 
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent users (last 5)
    $stmt = $conn->query("
        SELECT * FROM users 
        WHERE role != 'admin' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Category statistics
    $stmt = $conn->query("
        SELECT c.name, COUNT(p.id) as post_count 
        FROM categories c 
        LEFT JOIN posts p ON c.id = p.category_id 
        GROUP BY c.id, c.name 
        ORDER BY post_count DESC
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error fetching dashboard statistics: " . $e->getMessage());
    $error = "Error loading dashboard statistics";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Freedom Wall</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--secondary-color);
            color: var(--dark-grey);
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-grey);
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .admin-title {
            font-size: 24px;
            font-weight: bold;
            color: white;
        }

        .admin-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .admin-nav li {
            margin-bottom: 10px;
        }

        .admin-nav a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .admin-nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .admin-nav i {
            margin-right: 10px;
            width: 20px;
        }

        /* Add these new styles for dropdowns */
        .nav-group {
            margin-bottom: 15px;
        }

        .nav-group-title {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px;
            margin-bottom: 5px;
        }

        .nav-dropdown {
            position: relative;
        }

        .nav-dropdown > a {
            cursor: pointer;
        }

        .nav-dropdown > a::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            margin-left: auto;
            transition: transform 0.3s;
        }

        .nav-dropdown.active > a::after {
            transform: rotate(180deg);
        }

        .nav-dropdown-items {
            display: none;
            padding-left: 20px;
        }

        .nav-dropdown.active .nav-dropdown-items {
            display: block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            margin: 0;
            font-size: 14px;
            color: var(--dark-grey);
            opacity: 0.8;
        }

        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-card i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .activity-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: var(--dark-grey);
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--light-grey);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item .title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-item .meta {
            font-size: 12px;
            color: #666;
        }

        .category-stats {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--light-grey);
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-name {
            font-weight: 500;
        }

        .category-count {
            background: var(--light-grey);
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h1 class="mb-4">Dashboard Overview</h1>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3>Total Users</h3>
                    <div class="number"><?php echo number_format($total_users); ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>Total Posts</h3>
                    <div class="number"><?php echo number_format($total_posts); ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-comments"></i>
                    <h3>Total Comments</h3>
                    <div class="number"><?php echo number_format($total_comments); ?></div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Posts -->
                <div class="col-md-6">
                    <div class="recent-activity">
                        <h2 class="activity-title">Recent Posts</h2>
                        <ul class="activity-list">
                            <?php foreach ($recent_posts as $post): ?>
                            <li class="activity-item">
                                <div class="title"><?php echo htmlspecialchars($post['title']); ?></div>
                                <div class="meta">
                                    By <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?> 
                                    in <?php echo htmlspecialchars($post['category_name']); ?> • 
                                    <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="col-md-6">
                    <div class="recent-activity">
                        <h2 class="activity-title">Recent Users</h2>
                        <ul class="activity-list">
                            <?php foreach ($recent_users as $user): ?>
                            <li class="activity-item">
                                <div class="title"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                <div class="meta">
                                    Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Category Statistics -->
            <div class="category-stats mt-4">
                <h2 class="activity-title">Category Statistics</h2>
                <?php foreach ($category_stats as $category): ?>
                <div class="category-item">
                    <span class="category-name"><?php echo htmlspecialchars($category['name']); ?></span>
                    <span class="category-count"><?php echo number_format($category['post_count']); ?> posts</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle dropdown toggles
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