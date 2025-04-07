<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get unread notification count
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();

// Get user's posts with statistics
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';

// Build the query
$query = "
    SELECT p.*, 
           c.name as category_name,
           c.color as category_color,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id) as dislike_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.user_id = ?
";

$params = [$_SESSION['user_id']];

if (!empty($search)) {
    $query .= " AND (p.title LIKE ? OR p.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

// Add sorting
switch ($sort_by) {
    case 'oldest':
        $query .= " ORDER BY p.created_at ASC";
        break;
    case 'most_likes':
        $query .= " ORDER BY like_count DESC, p.created_at DESC";
        break;
    case 'most_comments':
        $query .= " ORDER BY comment_count DESC, p.created_at DESC";
        break;
    default:
        $query .= " ORDER BY p.created_at DESC";
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get post statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_posts,
        SUM((SELECT COUNT(*) FROM likes WHERE post_id = posts.id)) as total_likes,
        SUM((SELECT COUNT(*) FROM dislikes WHERE post_id = posts.id)) as total_dislikes,
        SUM((SELECT COUNT(*) FROM comments WHERE post_id = posts.id)) as total_comments,
        AVG((SELECT COUNT(*) FROM likes WHERE post_id = posts.id)) as avg_likes,
        AVG((SELECT COUNT(*) FROM comments WHERE post_id = posts.id)) as avg_comments
    FROM posts 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get categories
$stmt = $conn->prepare("SELECT id, name, color FROM categories");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Posts - Be Heard</title>
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

        /* Posts Specific Styles */
        .posts-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .filter-bar {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .input-group-text {
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            color: white;
            border: none;
        }

        .form-control, .form-select {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 0.5rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }

        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            padding: 1rem;
            margin-bottom: 0;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .post-card {
            background: white;
            border-radius: 15px;
            padding: 1.25rem;
            margin-bottom: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid;
            border-image: linear-gradient(to bottom, var(--primary-red), var(--secondary-gold)) 1;
        }

        .post-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .post-header {
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .post-title {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: var(--dark-grey);
            font-weight: 600;
            flex: 1;
        }

        .post-meta {
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
            color: #666;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .post-content {
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            max-height: 100px;
            overflow-y: auto;
            color: var(--dark-grey);
            line-height: 1.5;
            padding-right: 0.5rem;
        }

        .post-content::-webkit-scrollbar {
            width: 4px;
        }

        .post-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        .post-content::-webkit-scrollbar-thumb {
            background: var(--secondary-gold);
            border-radius: 2px;
        }

        .post-stats {
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
            display: flex;
            gap: 1.5rem;
            color: #666;
        }

        .post-stats span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .post-stats i {
            color: var(--secondary-gold);
        }

        .post-actions {
            margin-top: 0.75rem;
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-edit {
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            color: white;
            border: none;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .category-badge {
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .category-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
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
            
            .toggle-sidebar {
                display: block;
            }

            .post-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .post-title {
                width: 100%;
            }

            .post-meta {
                flex-wrap: wrap;
            }

            .post-stats {
                flex-wrap: wrap;
            }

            .post-actions {
                flex-wrap: wrap;
            }

            .btn-action {
                flex: 1;
                justify-content: center;
            }

            .filter-bar {
                padding: 0.75rem;
            }

            .posts-grid {
                grid-template-columns: 1fr;
            }

            .stats-section {
                grid-template-columns: repeat(2, 1fr);
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
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.25rem;
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
            <a class="nav-link active" href="my_posts.php" title="My Posts">
                <i class="fas fa-file-alt"></i>
                <span>My Posts</span>
            </a>
            <a class="nav-link" href="notifications.php" title="Notifications">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
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
        <div class="posts-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>My Posts</h2>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Post
                </a>
            </div>

            <!-- Search and Filter Bar -->
            <div class="filter-bar mb-3">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search posts..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $category['id'] == $category_filter ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="most_likes" <?php echo $sort_by === 'most_likes' ? 'selected' : ''; ?>>Most Likes</option>
                            <option value="most_comments" <?php echo $sort_by === 'most_comments' ? 'selected' : ''; ?>>Most Comments</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                    </div>
                </form>
            </div>

            <!-- Statistics Section -->
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_posts']; ?></div>
                    <div class="stat-label">Total Posts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_likes']; ?></div>
                    <div class="stat-label">Total Likes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_comments']; ?></div>
                    <div class="stat-label">Total Comments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['avg_likes'], 1); ?></div>
                    <div class="stat-label">Avg. Likes per Post</div>
                </div>
            </div>

            <!-- Posts List -->
            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                            <span class="category-badge" style="background: <?php echo htmlspecialchars($post['category_color']); ?>">
                                <?php echo htmlspecialchars($post['category_name']); ?>
                            </span>
                        </div>
                        <div class="post-meta">
                            <span>Posted on <?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?></span>
                            <?php if ($post['is_anonymous']): ?>
                                <span><i class="fas fa-user-secret"></i> Anonymous</span>
                            <?php endif; ?>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                        <div class="post-stats">
                            <span><i class="fas fa-heart"></i> <?php echo $post['like_count']; ?></span>
                            <span><i class="fas fa-thumbs-down"></i> <?php echo $post['dislike_count']; ?></span>
                            <span><i class="fas fa-comments"></i> <?php echo $post['comment_count']; ?></span>
                        </div>
                        <div class="post-actions">
                            <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-action btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn btn-action btn-delete" onclick="deletePost(<?php echo $post['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($posts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-file-alt fa-2x mb-2" style="color: var(--dark-grey);"></i>
                    <h3>No Posts Found</h3>
                    <p class="text-muted">Try adjusting your search or filters</p>
                    <a href="create_post.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus"></i> Create New Post
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Delete post functionality
        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                fetch('delete_post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ post_id: postId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the post card from the DOM
                        const postCard = document.querySelector(`[data-post-id="${postId}"]`);
                        if (postCard) {
                            postCard.remove();
                        }
                        // Show success message
                        alert('Post deleted successfully!');
                    } else {
                        // Show error message
                        alert(data.message || 'Error deleting post');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the post');
                });
            }
        }
    </script>
</body>
</html> 