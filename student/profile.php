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

// Get user profile data
$stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as total_posts,
           (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as total_comments,
           (SELECT COUNT(*) FROM likes WHERE post_id IN (SELECT id FROM posts WHERE user_id = u.id)) as total_likes_received,
           (SELECT COUNT(*) FROM dislikes WHERE post_id IN (SELECT id FROM posts WHERE user_id = u.id)) as total_dislikes_received,
           (SELECT COUNT(*) FROM comments WHERE post_id IN (SELECT id FROM posts WHERE user_id = u.id)) as total_comments_received
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's most active categories
$stmt = $conn->prepare("
    SELECT c.name, COUNT(*) as post_count 
    FROM posts p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.user_id = ? 
    GROUP BY c.id 
    ORDER BY post_count DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$top_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$stmt = $conn->prepare("
    (SELECT 'post' as type, p.id, p.title, p.created_at, NULL as content
     FROM posts p 
     WHERE p.user_id = ?)
    UNION ALL
    (SELECT 'comment' as type, c.id, NULL as title, c.created_at, c.content
     FROM comments c 
     WHERE c.user_id = ?)
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Be Heard</title>
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

        /* Profile Specific Styles */
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .profile-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            border-left: 4px solid;
            border-image: linear-gradient(to bottom, var(--primary-red), var(--secondary-gold)) 1;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
            border-top: 3px solid;
            border-image: linear-gradient(to right, var(--primary-red), var(--secondary-gold)) 1;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

        .stat-label {
            color: var(--dark-grey);
            font-size: 0.85rem;
        }

        .activity-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            border-right: 4px solid;
            border-image: linear-gradient(to bottom, var(--secondary-gold), var(--primary-red)) 1;
        }

        .activity-item {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            color: var(--white);
            font-size: 0.9rem;
        }

        .category-badge {
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            color: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
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

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-red), var(--secondary-gold));
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, var(--secondary-gold), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            <a class="nav-link active" href="profile.php" title="Profile">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a class="nav-link" href="my_posts.php" title="My Posts">
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
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                        <div class="mb-2">
                            <strong>Student ID:</strong> <?php echo htmlspecialchars($user['student_id']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Department:</strong> <?php echo htmlspecialchars($user['department'] ?? 'Not specified'); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Year Level:</strong> <?php echo htmlspecialchars($user['year_level'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <strong>Bio:</strong> <?php echo nl2br(htmlspecialchars($user['bio'] ?? 'No bio yet.')); ?>
                </div>
            </div>

            <!-- Activity Statistics -->
            <div class="profile-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user['total_posts']; ?></div>
                    <div class="stat-label">Posts Created</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user['total_comments']; ?></div>
                    <div class="stat-label">Comments Made</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user['total_likes_received']; ?></div>
                    <div class="stat-label">Likes Received</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user['total_dislikes_received']; ?></div>
                    <div class="stat-label">Dislikes Received</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user['total_comments_received']; ?></div>
                    <div class="stat-label">Comments Received</div>
                </div>
            </div>

            <div class="profile-grid">
                <!-- Recent Activity -->
                <div class="activity-section">
                    <h3 class="mb-3">Recent Activity</h3>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="d-flex align-items-center">
                                <div class="activity-icon me-3">
                                    <?php if ($activity['type'] === 'post'): ?>
                                        <i class="fas fa-file-alt"></i>
                                    <?php else: ?>
                                        <i class="fas fa-comment"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <?php if ($activity['type'] === 'post'): ?>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h5>
                                        <small class="text-muted">Posted on <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></small>
                                    <?php else: ?>
                                        <p class="mb-1"><?php echo htmlspecialchars($activity['content']); ?></p>
                                        <small class="text-muted">Commented on <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Top Categories -->
                <div class="activity-section">
                    <h3 class="mb-3">Most Active Categories</h3>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($top_categories as $category): ?>
                            <span class="category-badge">
                                <?php echo htmlspecialchars($category['name']); ?>
                                <small class="text-muted">(<?php echo $category['post_count']; ?> posts)</small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editProfileForm" action="update_profile.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="BSIT" <?php echo ($user['department'] ?? '') === 'BSIT' ? 'selected' : ''; ?>>BSIT</option>
                                <option value="BSCS" <?php echo ($user['department'] ?? '') === 'BSCS' ? 'selected' : ''; ?>>BSCS</option>
                                <option value="BSCE" <?php echo ($user['department'] ?? '') === 'BSCE' ? 'selected' : ''; ?>>BSCE</option>
                                <option value="BSEE" <?php echo ($user['department'] ?? '') === 'BSEE' ? 'selected' : ''; ?>>BSEE</option>
                                <option value="BSME" <?php echo ($user['department'] ?? '') === 'BSME' ? 'selected' : ''; ?>>BSME</option>
                                <option value="BSCHE" <?php echo ($user['department'] ?? '') === 'BSCHE' ? 'selected' : ''; ?>>BSCHE</option>
                                <option value="BSCPE" <?php echo ($user['department'] ?? '') === 'BSCPE' ? 'selected' : ''; ?>>BSCPE</option>
                                <option value="BSIE" <?php echo ($user['department'] ?? '') === 'BSIE' ? 'selected' : ''; ?>>BSIE</option>
                                <option value="BSCE" <?php echo ($user['department'] ?? '') === 'BSCE' ? 'selected' : ''; ?>>BSCE</option>
                                <option value="BSCE" <?php echo ($user['department'] ?? '') === 'BSCE' ? 'selected' : ''; ?>>BSCE</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="year_level" class="form-label">Year Level</label>
                            <select class="form-select" id="year_level" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1st" <?php echo ($user['year_level'] ?? '') === '1st' ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2nd" <?php echo ($user['year_level'] ?? '') === '2nd' ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3rd" <?php echo ($user['year_level'] ?? '') === '3rd' ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4th" <?php echo ($user['year_level'] ?? '') === '4th' ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <div class="form-text">Required to save changes</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editProfileForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const toggleButton = document.querySelector('.toggle-sidebar');
            
            // Toggle sidebar on mobile
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    !toggleButton.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });

            // Handle form submission
            const editProfileForm = document.getElementById('editProfileForm');
            editProfileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert('Profile updated successfully!');
                        // Reload the page to show updated information
                        window.location.reload();
                    } else {
                        // Show error message
                        alert(data.message || 'Error updating profile');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the profile');
                });
            });
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