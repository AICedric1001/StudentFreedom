<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_id = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Get all categories for the dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$query = "SELECT p.*, u.username, c.name as category_name, c.color,
          (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
          (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id) as dislike_count,
          (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
          (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = :user_id) as user_liked,
          (SELECT COUNT(*) FROM dislikes WHERE post_id = p.id AND user_id = :user_id) as user_disliked,
          p.is_anonymous
          FROM posts p 
          JOIN users u ON p.user_id = u.id 
          JOIN categories c ON p.category_id = c.id 
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (p.title LIKE :search OR p.content LIKE :search)";
}

if (!empty($category_id)) {
    $query .= " AND p.category_id = :category_id";
}

// Add sorting based on the sort parameter
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY p.created_at ASC";
        break;
    case 'most_likes':
        $query .= " ORDER BY like_count DESC, p.created_at DESC";
        break;
    case 'most_comments':
        $query .= " ORDER BY comment_count DESC, p.created_at DESC";
        break;
    case 'trending':
        // Trending is based on a combination of likes, comments, and recency
        $query .= " ORDER BY (like_count * 2 + comment_count) DESC, p.created_at DESC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

$stmt = $conn->prepare($query);

// Bind all parameters
$stmt->bindParam(':user_id', $_SESSION['user_id']);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->bindParam(':search', $searchParam);
}

if (!empty($category_id)) {
    $stmt->bindParam(':category_id', $category_id);
}

$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output the filtered posts HTML
foreach ($posts as $post): ?>
    <div class="post-card" style="--category-color: <?php echo htmlspecialchars($post['color']); ?>" data-post-id="<?php echo $post['id']; ?>">
        <div class="post-content">
            <div class="post-header">
                <h5 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                <span class="category-badge">
                    <?php echo ucfirst(htmlspecialchars($post['category_name'])); ?>
                    <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </span>
            </div>
            <p class="post-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
            <div class="post-meta">
                <span><?php echo $post['is_anonymous'] ? 'Anonymous' : htmlspecialchars($post['username']); ?></span>
                <span><?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?></span>
            </div>
            <div class="post-actions">
                <a href="#" class="action-btn <?php echo $post['user_liked'] ? 'active' : ''; ?>" onclick="handleReaction(event, <?php echo $post['id']; ?>, 'like')">
                    <i class="fas fa-heart"></i>
                    <span class="like-count"><?php echo $post['like_count']; ?></span>
                </a>
                <a href="#" class="action-btn <?php echo $post['user_disliked'] ? 'active' : ''; ?>" onclick="handleReaction(event, <?php echo $post['id']; ?>, 'dislike')">
                    <i class="fas fa-thumbs-down"></i>
                    <span class="dislike-count"><?php echo $post['dislike_count']; ?></span>
                </a>
                <span class="action-btn text-muted" style="opacity: 0.5;">
                    <i class="fas fa-comment"></i>
                    <span><?php echo $post['comment_count']; ?></span>
                </span>
            </div>
        </div>
    </div>
<?php endforeach; ?> 