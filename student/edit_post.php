<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get post ID from URL
$post_id = $_GET['id'] ?? null;

// Validate post ID
if (!$post_id || !is_numeric($post_id)) {
    header('Location: my_posts.php');
    exit();
}

// Get post data
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name 
    FROM posts p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.user_id = ?
");
$stmt->execute([$post_id, $_SESSION['user_id']]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

// If post not found or doesn't belong to user
if (!$post) {
    header('Location: my_posts.php');
    exit();
}

// Get categories for dropdown
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = $_POST['category_id'];
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    // Validate input
    $errors = [];
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    if (empty($content)) {
        $errors[] = "Content is required";
    }
    if (empty($category_id)) {
        $errors[] = "Category is required";
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE posts 
                SET title = ?, content = ?, category_id = ?, is_anonymous = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$title, $content, $category_id, $is_anonymous, $post_id, $_SESSION['user_id']]);

            header('Location: my_posts.php');
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error updating post";
            error_log("Post update error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - Be Heard</title>
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

        /* Edit Post Specific Styles */
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-grey);
        }

        .form-control, .form-select {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 0.75rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, var(--secondary-gold), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .form-check-input:checked {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
        }

        /* Responsive Design */
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
            <a class="nav-link" href="../logout.php" title="Logout">
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
        <div class="edit-container">
            <h2 class="mb-4">Edit Post</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?php echo htmlspecialchars($post['title']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo $category['id'] == $post['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">Content</label>
                    <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_anonymous" name="is_anonymous" 
                           <?php echo $post['is_anonymous'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_anonymous">Post Anonymously</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="my_posts.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
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
    </script>
</body>
</html> 