// Main JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle Post Form Submission
    const postForm = document.getElementById('postForm');
    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // Add post submission logic here
            console.log('Post submitted');
        });
    }

    // Handle Complaint Form Submission
    const complaintForm = document.getElementById('complaintForm');
    if (complaintForm) {
        complaintForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // Add complaint submission logic here
            console.log('Complaint submitted');
        });
    }

    // File Upload Preview
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                // Add file preview logic here
                console.log('File selected:', fileName);
            }
        });
    }

    // Load Posts
    function loadPosts() {
        // Add post loading logic here
        console.log('Loading posts...');
    }

    // Load Notifications
    function loadNotifications() {
        // Add notification loading logic here
        console.log('Loading notifications...');
    }

    // Initialize the page
    loadPosts();
    loadNotifications();
});

// Utility Functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function createPostElement(post) {
    const postDiv = document.createElement('div');
    postDiv.className = 'post';
    postDiv.innerHTML = `
        <div class="post-header">
            <div>
                <span class="anonymous-post">Anonymous</span>
                <span class="category-badge">${post.category}</span>
            </div>
            <small class="text-muted">${formatDate(post.date)}</small>
        </div>
        <div class="post-content">
            ${post.content}
        </div>
        <div class="post-actions">
            <button class="btn btn-sm btn-outline-primary" onclick="likePost(${post.id})">
                <i class="fas fa-heart"></i> Like
            </button>
        </div>
    `;
    return postDiv;
}

function createNotificationElement(notification) {
    const notificationDiv = document.createElement('div');
    notificationDiv.className = 'notification';
    notificationDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>${notification.title}</strong>
                <p class="mb-0">${notification.message}</p>
            </div>
            <small class="text-muted">${formatDate(notification.date)}</small>
        </div>
    `;
    return notificationDiv;
}

// Event Handlers
function likePost(postId) {
    // Add like functionality here
    console.log('Liked post:', postId);
}

// Export functions for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatDate,
        createPostElement,
        createNotificationElement,
        likePost
    };
} 