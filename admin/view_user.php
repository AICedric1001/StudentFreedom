<?php
require_once 'check_admin.php';
require_once '../config/database.php';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header("Location: users.php?error=Invalid user ID");
    exit();
}

try {
    // Fetch user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: users.php?error=User not found");
        exit();
    }

    // Fetch user's posts count
    $stmt = $conn->prepare("SELECT COUNT(*) as post_count FROM posts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $post_count = $stmt->fetch(PDO::FETCH_ASSOC)['post_count'];

    // Fetch user's comments count
    $stmt = $conn->prepare("SELECT COUNT(*) as comment_count FROM comments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $comment_count = $stmt->fetch(PDO::FETCH_ASSOC)['comment_count'];

    // Fetch recent posts
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name 
        FROM posts p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent comments
    $stmt = $conn->prepare("
        SELECT c.*, p.title as post_title 
        FROM comments c 
        JOIN posts p ON c.post_id = p.id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>View User - Admin Dashboard</title>
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

        /* Main content styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        /* User profile styles */
        .user-profile {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--light-grey);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-right: 20px;
        }

        .profile-info h1 {
            margin: 0;
            font-size: 24px;
        }

        .profile-info p {
            margin: 5px 0;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 5px;
        }

        .status-active {
            background: var(--success-color);
            color: white;
        }

        .status-banned {
            background: var(--danger-color);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--light-grey);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: #666;
            margin-top: 5px;
        }

        .activity-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .activity-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
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

        .activity-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-date {
            color: #666;
            font-size: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: var(--light-grey);
            color: var(--dark-grey);
        }

        .btn:hover {
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

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin: 0 0 15px 0;
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
                        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="user-profile">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="status-badge status-<?php echo isset($user['status']) ? $user['status'] : 'active'; ?>">
                            <?php echo isset($user['status']) ? ucfirst($user['status']) : 'Active'; ?>
                        </span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($post_count); ?></div>
                        <div class="stat-label">Total Posts</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($comment_count); ?></div>
                        <div class="stat-label">Total Comments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                        <div class="stat-label">Joined Date</div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Posts -->
                    <div class="col-md-6">
                        <div class="activity-section">
                            <h2>Recent Posts</h2>
                            <ul class="activity-list">
                                <?php foreach ($recent_posts as $post): ?>
                                <li class="activity-item">
                                    <div class="activity-title"><?php echo htmlspecialchars($post['title']); ?></div>
                                    <div class="activity-date">
                                        In <?php echo htmlspecialchars($post['category_name']); ?> • 
                                        <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Recent Comments -->
                    <div class="col-md-6">
                        <div class="activity-section">
                            <h2>Recent Comments</h2>
                            <ul class="activity-list">
                                <?php foreach ($recent_comments as $comment): ?>
                                <li class="activity-item">
                                    <div class="activity-title"><?php echo htmlspecialchars($comment['post_title']); ?></div>
                                    <div class="activity-date">
                                        <?php echo date('M d, Y', strtotime($comment['created_at'])); ?>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit User
                    </a>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>
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