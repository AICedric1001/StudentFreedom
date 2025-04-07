<?php
session_start();
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-trending">Please log in to view trending posts.</div>';
    exit;
}

// Get time filter from request
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'present';

// Validate time filter
if (!in_array($time_filter, ['present', 'today', 'week', 'month'])) {
    $time_filter = 'present';
}

// Set time condition based on filter
$time_condition = "";
if ($time_filter == 'present') {
    $time_condition = ""; // No time restriction for real-time trending
} else if ($time_filter == 'today') {
    $time_condition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
} else if ($time_filter == 'week') {
    $time_condition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} else if ($time_filter == 'month') {
    $time_condition = "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Get trending posts based on time filter
$trending_query = "SELECT p.*, u.username, c.name as category_name, c.color,
                 (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                 (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                 FROM posts p 
                 JOIN users u ON p.user_id = u.id 
                 JOIN categories c ON p.category_id = c.id 
                 WHERE 1=1 $time_condition";

// No category filter for any time period - show all categories
// This allows the "Present" filter to show trending posts from all categories

$trending_query .= " ORDER BY (like_count + comment_count) DESC LIMIT 5"; // Always limit to 5 posts

try {
    $trending_stmt = $conn->prepare($trending_query);
    $trending_stmt->execute();
    $trending_posts = $trending_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($trending_posts) > 0) {
        foreach ($trending_posts as $index => $post) {
            $like_count = $post['like_count'];
            $comment_count = $post['comment_count'];
            $total_engagement = $like_count + $comment_count;
            $category_color = $post['color'];
            ?>
            <div class="trending-card" style="--category-color: <?php echo $category_color; ?>" data-post-id="<?php echo $post['id']; ?>">
                <div class="trending-rank">#<?php echo $index + 1; ?></div>
                <div class="trending-content">
                    <h4 class="trending-title"><?php echo htmlspecialchars($post['title']); ?></h4>
                    <p class="trending-text"><?php echo substr(htmlspecialchars($post['content']), 0, 100) . (strlen($post['content']) > 100 ? '...' : ''); ?></p>
                    <div class="trending-meta">
                        <span class="trending-author"><?php echo $post['is_anonymous'] ? 'Anonymous' : htmlspecialchars($post['username']); ?></span>
                        <span class="trending-category"><?php echo htmlspecialchars($post['category_name']); ?></span>
                    </div>
                    <div class="trending-stats">
                        <span class="trending-likes"><i class="fas fa-heart"></i> <?php echo $like_count; ?></span>
                        <span class="trending-comments"><i class="fas fa-comment"></i> <?php echo $comment_count; ?></span>
                        <span class="trending-engagement"><i class="fas fa-fire"></i> <?php echo $total_engagement; ?></span>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<div class="no-trending">No trending posts for this time period.</div>';
    }
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Error in get_trending_posts.php: " . $e->getMessage());
    echo '<div class="error-trending">Error loading trending posts: ' . $e->getMessage() . '</div>';
}
?> 