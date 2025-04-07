<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get filter type from URL
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$query = "
    SELECT n.*, 
           u.username,
           CASE 
               WHEN n.type = 'like' THEN (SELECT title FROM posts WHERE id = n.related_id)
               WHEN n.type = 'comment' THEN (SELECT title FROM posts WHERE id = n.related_id)
               WHEN n.type = 'mention' THEN (SELECT title FROM posts WHERE id = n.related_id)
               ELSE NULL
           END as post_title
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.user_id = ?
";

if ($filter !== 'all') {
    $query .= " AND n.type = ?";
}

$query .= " ORDER BY n.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if ($filter !== 'all') {
    $stmt->execute([$_SESSION['user_id'], $filter]);
} else {
    $stmt->execute([$_SESSION['user_id']]);
}
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Be Heard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #E74C3C;
            --secondary-gold: #D4AF37;
            --light-gold: #FFD700;
            --dark-grey: #2C3E50;
            --light-grey: #ECF0F1;
            --white: #FFFFFF;
            --sidebar-width: 70px;
            --sidebar-expanded-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-grey);
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background-color: var(--dark-grey);
            color: var(--white);
            padding: 20px 10px;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        .sidebar:hover {
            width: var(--sidebar-expanded-width);
        }

        .sidebar-header {
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-nav {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .sidebar-footer {
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .sidebar-header h1 {
            font-size: 1.5rem;
            margin: 0;
            color: var(--secondary-gold);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar:hover .sidebar-header h1 {
            opacity: 1;
        }

        .nav-link {
            color: var(--white);
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            white-space: nowrap;
            overflow: hidden;
        }

        .nav-link span {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar:hover .nav-link span {
            opacity: 1;
        }

        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: var(--secondary-gold);
        }

        .nav-link.active {
            background-color: var(--secondary-gold);
            color: var(--white);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.2rem;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .sidebar:hover + .main-content {
            margin-left: var(--sidebar-expanded-width);
        }

        /* Notification Specific Styles */
        .notifications-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .notification-filters {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            background: var(--light-grey);
            color: var(--dark-grey);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            color: white;
        }

        .notification-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
            border-image: linear-gradient(to bottom, var(--primary-red), var(--secondary-gold)) 1;
        }

        .notification-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .notification-card.unread {
            background: #fff9f9;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            color: white;
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .notification-message {
            color: var(--dark-grey);
            margin-bottom: 0.5rem;
        }

        .notification-meta {
            font-size: 0.85rem;
            color: #666;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 5px;
            background: var(--light-grey);
            color: var(--dark-grey);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--dark-grey);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--secondary-gold);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .toggle-sidebar {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--dark-grey);
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: 5px;
                cursor: pointer;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
                width: var(--sidebar-expanded-width);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        .toggle-sidebar {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--dark-grey);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h1>Freedom Wall</h1>
        </div>
        <nav class="nav flex-column sidebar-nav">
            <a class="nav-link" href="dashboard.php" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link" href="profile.php" title="Profile">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a class="nav-link" href="my_posts.php" title="My Posts">
                <i class="fas fa-file-alt"></i>
                <span>My Posts</span>
            </a>
            <a class="nav-link active" href="notifications.php" title="Notifications">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link" href="settings.php" title="Settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a class="nav-link" href="logout.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Toggle Sidebar Button (Mobile) -->
    <button class="toggle-sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <div class="notifications-container">
            <h2 class="mb-4">Notifications</h2>
            
            <!-- Filters -->
            <div class="notification-filters">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All
                </a>
                <a href="?filter=like" class="filter-btn <?php echo $filter === 'like' ? 'active' : ''; ?>">
                    Likes
                </a>
                <a href="?filter=comment" class="filter-btn <?php echo $filter === 'comment' ? 'active' : ''; ?>">
                    Comments
                </a>
                <a href="?filter=mention" class="filter-btn <?php echo $filter === 'mention' ? 'active' : ''; ?>">
                    Mentions
                </a>
                <a href="?filter=admin_message" class="filter-btn <?php echo $filter === 'admin_message' ? 'active' : ''; ?>">
                    Admin Messages
                </a>
            </div>

            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You're all caught up!</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                         data-id="<?php echo $notification['id']; ?>">
                        <div class="d-flex align-items-start gap-3">
                            <div class="notification-icon">
                                <?php
                                switch ($notification['type']) {
                                    case 'like':
                                        echo '<i class="fas fa-heart"></i>';
                                        break;
                                    case 'comment':
                                        echo '<i class="fas fa-comment"></i>';
                                        break;
                                    case 'mention':
                                        echo '<i class="fas fa-at"></i>';
                                        break;
                                    case 'admin_message':
                                        echo '<i class="fas fa-shield-alt"></i>';
                                        break;
                                    default:
                                        echo '<i class="fas fa-bell"></i>';
                                }
                                ?>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php
                                    switch ($notification['type']) {
                                        case 'like':
                                            echo htmlspecialchars($notification['username']) . ' liked your post';
                                            break;
                                        case 'comment':
                                            echo htmlspecialchars($notification['username']) . ' commented on your post';
                                            break;
                                        case 'mention':
                                            echo htmlspecialchars($notification['username']) . ' mentioned you in a comment';
                                            break;
                                        case 'admin_message':
                                            echo 'Admin Message';
                                            break;
                                        default:
                                            echo 'Notification';
                                    }
                                    ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['content']); ?>
                                    <?php if ($notification['post_title']): ?>
                                        <div class="text-muted mt-1">
                                            Post: <?php echo htmlspecialchars($notification['post_title']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-meta">
                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="action-btn mark-read" title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="action-btn delete-notification" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebar = document.querySelector('.sidebar');
            const toggleButton = document.querySelector('.toggle-sidebar');
            
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    !toggleButton.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });

            // Mark as read functionality
            document.querySelectorAll('.mark-read').forEach(button => {
                button.addEventListener('click', function() {
                    const notificationCard = this.closest('.notification-card');
                    const notificationId = notificationCard.dataset.id;
                    
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ notification_id: notificationId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            notificationCard.classList.remove('unread');
                            this.remove();
                            updateUnreadCount();
                        }
                    });
                });
            });

            // Delete notification functionality
            document.querySelectorAll('.delete-notification').forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this notification?')) {
                        const notificationCard = this.closest('.notification-card');
                        const notificationId = notificationCard.dataset.id;
                        
                        fetch('delete_notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ notification_id: notificationId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                notificationCard.remove();
                                updateUnreadCount();
                            }
                        });
                    }
                });
            });

            // Update unread count
            function updateUnreadCount() {
                fetch('get_unread_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.nav-link.active .badge');
                    if (data.count > 0) {
                        if (!badge) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge bg-danger rounded-pill';
                            newBadge.textContent = data.count;
                            document.querySelector('.nav-link.active').appendChild(newBadge);
                        } else {
                            badge.textContent = data.count;
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                });
            }

            // Poll for new notifications every 30 seconds
            setInterval(updateUnreadCount, 30000);
        });

        // Mobile Sidebar Toggle
        document.querySelector('.toggle-sidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!e.target.closest('.sidebar') && !e.target.closest('.toggle-sidebar')) {
                    document.querySelector('.sidebar').classList.remove('active');
                }
            }
        });
    </script>
</body>
</html> 