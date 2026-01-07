<?php
session_start();
require_once '../config/db.php';

// Kiểm tra user đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin user hiện tại
$sql = "SELECT firstname, lastname, username, avatar FROM users WHERE id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Xử lý avatar
$user_avatar = "../assets/default_avatar.jpg";
if (!empty($current_user['avatar']) && file_exists($current_user['avatar'])) {
    $user_avatar = $current_user['avatar'];
}

// Lấy danh sách bài viết từ database
$posts = array();
$sql = "SELECT p.id, p.user_id, p.content, p.image, p.likes_count, p.comments_count, p.created_at,
                u.firstname, u.lastname, u.avatar
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 50";

$result = $connection->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $post_avatar = "../assets/default_avatar.jpg";
        if (!empty($row['avatar']) && file_exists($row['avatar'])) {
            $post_avatar = $row['avatar'];
        }
        
        // Tính toán thời gian
        $post_time = strtotime($row['created_at']);
        $current_time = time();
        $diff = $current_time - $post_time;
        
        if ($diff < 60) {
            $time_ago = "just now";
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            $time_ago = $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            $time_ago = $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            $time_ago = $days . " day" . ($days > 1 ? "s" : "") . " ago";
        } else {
            $time_ago = date('M d, Y', $post_time);
        }
        
        // Kiểm tra user đã like bài viết chưa
        $like_sql = "SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?";
        $like_stmt = $connection->prepare($like_sql);
        $like_stmt->bind_param("ii", $row['id'], $user_id);
        $like_stmt->execute();
        $liked = $like_stmt->get_result()->num_rows > 0;
        $like_stmt->close();
        
        // Lấy danh sách comments
        $comments = array();
        $comment_sql = "SELECT c.id, c.content, c.created_at, u.firstname, u.lastname, u.avatar
                        FROM comments c
                        JOIN users u ON c.user_id = u.id
                        WHERE c.post_id = ?
                        ORDER BY c.created_at ASC
                        LIMIT 10";
        $comment_stmt = $connection->prepare($comment_sql);
        $comment_stmt->bind_param("i", $row['id']);
        $comment_stmt->execute();
        $comment_result = $comment_stmt->get_result();
        
        while ($comment_row = $comment_result->fetch_assoc()) {
            $comment_avatar = "../assets/default_avatar.jpg";
            if (!empty($comment_row['avatar']) && file_exists($comment_row['avatar'])) {
                $comment_avatar = $comment_row['avatar'];
            }
            
            // Tính thời gian comment
            $comment_time = strtotime($comment_row['created_at']);
            $diff = $current_time - $comment_time;
            
            if ($diff < 60) {
                $comment_time_ago = "just now";
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                $comment_time_ago = $mins . "m";
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                $comment_time_ago = $hours . "h";
            } else {
                $days = floor($diff / 86400);
                $comment_time_ago = $days . "d";
            }
            
            $comments[] = array(
                'id' => $comment_row['id'],
                'user_name' => $comment_row['firstname'] . ' ' . $comment_row['lastname'],
                'avatar' => $comment_avatar,
                'content' => $comment_row['content'],
                'time' => $comment_time_ago
            );
        }
        $comment_stmt->close();
        
        $posts[] = array(
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'author' => $row['firstname'] . ' ' . $row['lastname'],
            'avatar' => $post_avatar,
            'time' => $time_ago,
            'content' => $row['content'],
            'image' => $row['image'] ? $row['image'] : '',
            'likes' => $row['likes_count'],
            'comments' => $row['comments_count'],
            'liked' => $liked,
            'comments_list' => $comments
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Random-Chat</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/home.css?v=2">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body class="home-page">
    <!-- HEADER -->
    <div class="header">
        <div class="header-container">
            <a href="home.php" style="text-decoration: none; color: inherit;">
                <div class="logo">Random-Chat</div>
            </a>
            <div class="search-bar-container">
                <input type="text" id="searchInput" class="search-bar" placeholder="Search friends or posts...">
                <button class="search-btn" onclick="performSearch(document.getElementById('searchInput').value.trim())">🔍</button>
                <div class="search-results-dropdown" id="searchResults" style="display: none;"></div>
            </div>
            </div>
            <div class="header-actions">
                <button class="notification-bell" id="notificationBell" onclick="toggleNotificationModal()">
                    🔔
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <a href="settings.php" class="header-link">Profile</a>
                <a href="messages.php" class="header-link">Messages</a>
                <a href="login.php" class="header-link logout">Logout</a>
            </div>
        </div>
    </div>

    <!-- Notification Modal Panel -->
    <div class="notification-overlay" id="notificationOverlay" onclick="toggleNotificationModal()"></div>
    <div class="notification-modal" id="notificationModal">
        <div class="notification-header">
            <h3>Notifications</h3>
            <div>
                <a href="#" class="clear-all-btn" onclick="clearAllNotifications(); return false;">Clear all</a>
                <button class="notification-close-btn" onclick="toggleNotificationModal()">✕</button>
            </div>
        </div>
        
        <!-- Notification Tabs -->
        <div class="notification-tabs">
            <button class="notification-tab active" onclick="switchNotificationTab('notifications')">
                <i class="fas fa-bell"></i> Notifications
            </button>
            <button class="notification-tab" onclick="switchNotificationTab('friend-requests')">
                <i class="fas fa-user-plus"></i> Friend Requests
                <span id="friendRequestBadge" class="badge" style="display: none;">0</span>
            </button>
            <button class="notification-tab" onclick="switchNotificationTab('suggestions')">
                <i class="fas fa-user-friends"></i> Suggestions
            </button>
            <button class="notification-tab" onclick="switchNotificationTab('search')">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
        
        <!-- Notifications Content -->
        <div id="notificationsContent" class="notification-content active">
            <div class="notification-list" id="notificationList">
                <div class="notification-empty">No notifications yet</div>
            </div>
        </div>
        
        <!-- Friend Requests Content -->
        <div id="friendRequestsContent" class="notification-content">
            <div class="friend-requests-list" id="friendRequestsList">
                <div class="notification-empty">No friend requests</div>
            </div>
        </div>
        
        <!-- Suggestions Content -->
        <div id="suggestionsContent" class="notification-content">
            <div class="suggestions-list" id="suggestionsList">
                <div class="notification-empty">Loading suggestions...</div>
            </div>
        </div>
        
        <!-- Search Content -->
        <div id="searchContent" class="notification-content">
            <div class="search-friends-container">
                <input type="text" id="searchFriendsInput" placeholder="Search people...">
                <div id="searchFriendsResults"></div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- MAIN CONTENT -->
    <div class="main-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="user-card">
                <img src="<?php echo $user_avatar; ?>" alt="Profile" class="user-avatar">
                <h3><?php echo htmlspecialchars($current_user['firstname'] . " " . $current_user['lastname']); ?></h3>
                <p>@<?php echo htmlspecialchars($current_user['username']); ?></p>
                <a href="settings.php" class="edit-profile-btn">Edit Profile</a>
            </div>
            <div class="sidebar-menu">
                <a href="#" class="menu-item active" onclick="showSection('feed'); return false;">Feed</a>
                <a href="messages.php" class="menu-item">Messenger</a>
                <a href="friends.php" class="menu-item">Friends</a>
                <a href="#" class="menu-item" onclick="goToRandom(); return false;">🎲 Random</a>
                <a href="#" class="menu-item">Photos</a>
                <a href="settings.php" class="menu-item">Settings</a>
            </div>
        </aside>

        <!-- FEED -->
        <main class="feed" id="feed-section">
            <!-- CREATE POST -->
            <div class="create-post">
                <img src="<?php echo $user_avatar; ?>" alt="Avatar" class="post-avatar">
                <div class="create-post-input">
                    <form id="createPostForm" enctype="multipart/form-data">
                        <input type="text" placeholder="What's on your mind?" class="post-input" id="postContent" required>
                        <div class="post-actions">
                            <label for="postImage" class="action-btn" style="margin: 0; cursor: pointer; border: none; padding: 10px 14px;">📷 Photo</label>
                            <input type="file" id="postImage" accept="image/*" style="display: none;">
                            <button type="submit" class="post-btn">Post</button>
                        </div>
                        <div id="imagePreview" style="display: none; margin-top: 12px;">
                            <img id="previewImg" style="max-width: 100%; max-height: 300px; border-radius: 8px;">
                            <button type="button" class="action-btn" id="removeImage" style="margin-top: 8px;">✕ Remove</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- POSTS FEED -->
            <?php foreach($posts as $post): ?>
            <div class="post" data-post-id="<?php echo $post['id']; ?>" data-post-title="<?php echo htmlspecialchars($post['content'], ENT_QUOTES); ?>" data-post-author="<?php echo htmlspecialchars($post['author'], ENT_QUOTES); ?>">
                <!-- POST HEADER -->
                <div class="post-header">
                    <img src="<?php echo $post['avatar']; ?>" alt="Avatar" class="post-avatar">
                    <div class="post-info">
                        <h4><?php echo $post['author']; ?></h4>
                        <span class="post-time"><?php echo $post['time']; ?></span>
                    </div>
                    <div style="position: relative;">
                        <button class="post-menu" onclick="togglePostMenu(event, <?php echo $post['id']; ?>)">...</button>
                        <div class="post-menu-dropdown" id="post-menu-<?php echo $post['id']; ?>">
                            <div class="post-menu-item" onclick="editPost(<?php echo $post['id']; ?>)">✏️ Edit</div>
                            <div class="post-menu-item delete" onclick="deletePost(<?php echo $post['id']; ?>)">🗑️ Delete</div>
                        </div>
                    </div>
                </div>

                <!-- POST CONTENT -->
                <div class="post-content">
                    <p id="post-content-<?php echo $post['id']; ?>"><?php echo $post['content']; ?></p>
                    <?php if(!empty($post['image'])): ?>
                    <img src="<?php echo $post['image']; ?>" alt="Post image" class="post-image">
                    <?php endif; ?>
                </div>

                <!-- POST STATS -->
                <div class="post-stats">
                    <span>👍 <?php echo $post['likes']; ?> Likes</span>
                    <span><?php echo $post['comments']; ?> Comments</span>
                </div>

                <!-- POST ACTIONS -->
                <div class="post-actions-bar">
                    <button class="action-btn <?php echo $post['liked'] ? 'liked' : ''; ?>" onclick="toggleLike(<?php echo $post['id']; ?>)">
                        👍 Like
                    </button>
                    <button class="action-btn" onclick="toggleComment(<?php echo $post['id']; ?>)">
                        💬 Comment
                    </button>
                    <button class="action-btn">
                        ↗️ Share
                    </button>
                </div>

                <!-- COMMENTS SECTION -->
                <div class="comments-section" id="comments-<?php echo $post['id']; ?>" style="display: none;">
                    <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                        <?php foreach($post['comments_list'] as $comment): ?>
                        <div class="comment">
                            <img src="<?php echo $comment['avatar']; ?>" alt="Avatar" class="comment-avatar">
                            <div class="comment-content">
                                <h5><?php echo htmlspecialchars($comment['user_name']); ?></h5>
                                <p><?php echo htmlspecialchars($comment['content']); ?></p>
                                <span class="comment-time"><?php echo $comment['time']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="add-comment">
                        <img src="<?php echo $user_avatar; ?>" alt="Avatar" class="comment-avatar">
                        <form class="comment-form" data-post-id="<?php echo $post['id']; ?>" style="display: flex; flex: 1; gap: 8px;">
                            <input type="text" placeholder="Write a comment..." class="comment-input" required>
                            <button type="submit" class="comment-btn">Post</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </main>

        <!-- MESSENGER -->
        <main class="messenger" id="messenger-section" style="display: none;">
            <div class="messenger-container">
                <!-- CONVERSATION LIST -->
                <div class="conversation-list">
                    <div class="conversation-header">
                        <h3>Messages</h3>
                        <button class="new-message-btn">+</button>
                    </div>
                    <div class="conversation-item active" onclick="selectConversation(1)">
                        <img src="https://via.placeholder.com/50" alt="Avatar" class="conversation-avatar">
                        <div class="conversation-info">
                            <h4>Kiet</h4>
                            <p>ok</p>
                        </div>
                        <span class="unread-badge">1</span>
                    </div>
                    <div class="conversation-item" onclick="selectConversation(2)">
                        <img src="https://via.placeholder.com/50" alt="Avatar" class="conversation-avatar">
                        <div class="conversation-info">
                            <h4>Nguyen Anh Kiet</h4>
                            <p>check mes</p>
                        </div>
                    </div>
                </div>

                <!-- CHAT WINDOW -->
                <div class="chat-window">
                    <div class="chat-header">
                        <img src="https://via.placeholder.com/40" alt="Avatar" class="chat-avatar">
                        <div class="chat-header-info">
                            <h3>Kiet</h3>
                            <p>Active now</p>
                        </div>
                        <div class="chat-header-actions">
                            <button class="chat-action-btn">☎️</button>
                            <button class="chat-action-btn">📹</button>
                            <button class="chat-action-btn">ℹ️</button>
                        </div>
                    </div>

                    <!-- MESSAGES -->
                    <div class="messages-container">
                        <div class="message-group">
                            <div class="message-date">Today</div>
                            <div class="message sent">
                                <p>check mes</p>
                                <span class="message-time">10:30 AM</span>
                            </div>
                            <div class="message received">
                                <p>ok</p>
                                <span class="message-time">10:32 AM</span>
                            </div>
                        </div>
                    </div>

                    <!-- MESSAGE INPUT -->
                    <div class="message-input-container">
                        <button class="input-action-btn">➕</button>
                        <input type="text" placeholder="Aa" class="message-input">
                        <button class="input-action-btn">😊</button>
                        <button class="send-btn">➤</button>
                    </div>
                </div>
            </div>
        </main>

        <!-- FRIENDS PAGE -->
        <main class="feed" id="friends-section" style="display: none;">
            <div class="friends-container">
                <h2 style="font-size: 28px; font-weight: 700; margin-bottom: 24px;">Friends</h2>
                
                <div class="friends-grid">
                    <!-- Friend 1: Khoa -->
                    <div class="friend-card">
                        <img src="https://via.placeholder.com/150" alt="Avatar" class="friend-avatar">
                        <h4>Khoa</h4>
                        <p class="friend-status">Online</p>
                        <div class="friend-actions">
                            <button class="friend-btn message-btn">Message</button>
                            <button class="friend-btn remove-btn">Remove</button>
                        </div>
                    </div>

                    <!-- Friend 2:Kiet -->
                    <div class="friend-card">
                        <img src="https://via.placeholder.com/150" alt="Avatar" class="friend-avatar">
                        <h4>Kiet</h4>
                        <p class="friend-status">Online</p>
                        <div class="friend-actions">
                            <button class="friend-btn message-btn">Message</button>
                            <button class="friend-btn remove-btn">Remove</button>
                        </div>
                    </div>

                    <!-- Friend 3: Jean -->
                    <div class="friend-card">
                        <img src="https://via.placeholder.com/150" alt="Avatar" class="friend-avatar">
                        <h4>Jean</h4>
                        <p class="friend-status">Offline</p>
                        <div class="friend-actions">
                            <button class="friend-btn message-btn">Message</button>
                            <button class="friend-btn remove-btn">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleLike(postId) {
            const formData = new FormData();
            formData.append('post_id', postId);
            
            // Get the button and post
            const btn = event.target;
            const post = btn.closest('.post');
            const likeStatsSpan = post.querySelector('.post-stats span:first-child');

            console.log('Like post:', postId);
            console.log('Button element:', btn);
            console.log('Like stats text:', likeStatsSpan?.textContent);

            fetch('toggle_like.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response from server:', data);
                
                if (data.success) {
                    // Extract current like count from text like "👍 245 Likes"
                    const likeText = likeStatsSpan.textContent;
                    const likeCount = parseInt(likeText.match(/\d+/)?.[0] || 0);
                    
                    console.log('Like count extracted:', likeCount);
                    
                    if (data.action === 'liked') {
                        btn.classList.add('liked');
                        likeStatsSpan.textContent = '👍 ' + (likeCount + 1) + ' Likes';
                        console.log('Liked! New count:', likeCount + 1);
                        
                        // Show toast notification
                        if (data.toast) {
                            showToast(data.toast);
                        }
                    } else {
                        btn.classList.remove('liked');
                        likeStatsSpan.textContent = '👍 ' + Math.max(0, likeCount - 1) + ' Likes';
                        console.log('Unliked! New count:', Math.max(0, likeCount - 1));
                    }
                } else {
                    console.error('Error from server:', data.message);
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Có lỗi xảy ra');
            });
        }

        // Notification functions
        function toggleNotificationModal() {
            const modal = document.getElementById('notificationModal');
            const overlay = document.getElementById('notificationOverlay');
            
            // Toggle modal visibility
            modal.classList.toggle('show');
            overlay.classList.toggle('show');
            
            // Load notifications when opening modal
            if (modal.classList.contains('show')) {
                loadNotifications();
            }
        }

        function loadNotifications() {
            fetch('get_notifications.php?limit=10')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notifList = document.getElementById('notificationList');
                    const badge = document.getElementById('notificationBadge');
                    
                    // Update badge
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                    
                    // Clear and rebuild list
                    notifList.innerHTML = '';
                    
                    if (data.notifications.length === 0) {
                        notifList.innerHTML = '<div class="notification-empty">No notifications yet</div>';
                        return;
                    }
                    
                    data.notifications.forEach(notif => {
                        const notifItem = document.createElement('div');
                        notifItem.className = 'notification-item' + (notif.is_read ? '' : ' unread');
                        notifItem.innerHTML = `
                            <img src="${notif.actor_avatar}" alt="Avatar" class="notification-avatar">
                            <div class="notification-content">
                                <div class="notification-text">
                                    <span class="notification-actor">${escapeHtml(notif.actor_name)}</span>
                                    <span class="notification-message">${notif.message}</span>
                                </div>
                                ${notif.content ? `<div class="notification-preview">${escapeHtml(notif.content)}</div>` : ''}
                                <div class="notification-time">${notif.time}</div>
                            </div>
                        `;
                        
                        notifItem.onclick = (e) => {
                            e.stopPropagation();
                            markAsRead(notif.id);
                            // Navigate to post
                            window.location.href = '#post-' + notif.post_id;
                        };
                        
                        notifList.appendChild(notifItem);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
        }

        function markAsRead(notificationId) {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            fetch('mark_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                } else {
                    console.error('Error marking as read:', data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
        }

        function clearAllNotifications() {
            if (confirm('Are you sure you want to clear all notifications?')) {
                const formData = new FormData();
                formData.append('action', 'clear_all');
                
                fetch('mark_notification_read.php?action=clear_all', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                    } else {
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                });
            }
        }

        // Toast Notification Functions
        function showToast(notificationData) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            
            const avatarSrc = notificationData.actor_avatar || '../assets/default_avatar.jpg';
            const actorName = escapeHtml(notificationData.actor_name || 'Someone');
            const actionText = notificationData.action || 'interacted with your post';
            const currentTime = new Date();
            const timeString = currentTime.toLocaleTimeString('en-US', { hour: '2-digit', minute:'2-digit', hour12: true });
            
            toast.innerHTML = `
                <img src="${avatarSrc}" alt="Avatar" class="toast-avatar">
                <div class="toast-content">
                    <div class="toast-message">
                        <span class="toast-actor">${actorName}</span>
                        <span class="toast-action"> ${actionText}</span>
                    </div>
                    <div class="toast-time">${timeString}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
            `;
            
            container.appendChild(toast);
            
            // Auto remove toast after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('hide');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }
            }, 5000);
            
            // Reload notifications to update badge
            loadNotifications();
        }

        // Load notifications on page load
        window.addEventListener('load', function() {
            setTimeout(function() {
                console.log('Loading notifications...');
                loadNotifications();
            }, 2000);
        });

        // Close modal when clicking outside
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('notificationModal');
                if (modal.classList.contains('show')) {
                    toggleNotificationModal();
                }
            }
        });

        // ===== FRIEND & NOTIFICATIONS TAB FUNCTIONS =====
        
        function switchNotificationTab(tabName) {
            // Hide all contents
            document.querySelectorAll('.notification-content').forEach(el => {
                el.classList.remove('active');
            });
            
            // Remove active from all tabs
            document.querySelectorAll('.notification-tab').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show selected content
            const contentId = {
                'notifications': 'notificationsContent',
                'friend-requests': 'friendRequestsContent',
                'suggestions': 'suggestionsContent',
                'search': 'searchContent'
            }[tabName];
            
            if (contentId) {
                document.getElementById(contentId).classList.add('active');
            }
            
            // Mark tab as active
            event.target.closest('.notification-tab').classList.add('active');
            
            // Load data based on tab
            if (tabName === 'friend-requests') {
                loadFriendRequests();
            } else if (tabName === 'suggestions') {
                loadSuggestions();
            }
        }
        
        function loadFriendRequests() {
            fetch('api/friends.php?action=get_friend_requests')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const list = document.getElementById('friendRequestsList');
                        
                        if (data.requests.length === 0) {
                            list.innerHTML = '<div class="notification-empty">No friend requests</div>';
                            return;
                        }
                        
                        list.innerHTML = '';
                        data.requests.forEach(req => {
                            const item = document.createElement('div');
                            item.className = 'friend-request-item';
                            item.innerHTML = `
                                <img src="${req.avatar}" alt="${req.firstname}" class="friend-request-avatar">
                                <div class="friend-request-info">
                                    <h5>${escapeHtml(req.firstname + ' ' + req.lastname)}</h5>
                                    <p>@${escapeHtml(req.username)}</p>
                                </div>
                                <div class="friend-request-actions">
                                    <button class="btn-small btn-accept" onclick="acceptFriendRequest(${req.requester_id}, this)">Accept</button>
                                    <button class="btn-small btn-reject" onclick="rejectFriendRequest(${req.requester_id}, this)">Reject</button>
                                </div>
                            `;
                            list.appendChild(item);
                        });
                        
                        // Update badge
                        const badge = document.getElementById('friendRequestBadge');
                        if (data.requests.length > 0) {
                            badge.textContent = data.requests.length;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
        }
        
        function loadSuggestions() {
            fetch('api/friends.php?action=get_suggestions&limit=10')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const list = document.getElementById('suggestionsList');
                        
                        if (data.suggestions.length === 0) {
                            list.innerHTML = '<div class="notification-empty">No more suggestions</div>';
                            return;
                        }
                        
                        list.innerHTML = '';
                        data.suggestions.forEach(user => {
                            const item = document.createElement('div');
                            item.className = 'suggestion-item';
                            item.innerHTML = `
                                <img src="${user.avatar}" alt="${user.firstname}" class="suggestion-avatar">
                                <div class="suggestion-info">
                                    <h5>${escapeHtml(user.firstname + ' ' + user.lastname)}</h5>
                                    <p>@${escapeHtml(user.username)}</p>
                                </div>
                                <div class="suggestion-actions">
                                    <button class="btn-small btn-accept" onclick="addFriendFromSuggestion(${user.id}, this)">Add</button>
                                    <button class="btn-small btn-reject" onclick="this.parentElement.parentElement.remove()">Dismiss</button>
                                </div>
                            `;
                            list.appendChild(item);
                        });
                    }
                });
        }
        
        function acceptFriendRequest(userId, btn) {
            btn.disabled = true;
            btn.textContent = 'Accepting...';
            
            fetch('api/friends.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=accept_request&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.closest('.friend-request-item').remove();
                    loadFriendRequests();
                    showToast({
                        actor_name: 'Friend',
                        action: 'request accepted',
                        actor_avatar: '../assets/default_avatar.jpg'
                    });
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Accept';
                }
            });
        }
        
        function rejectFriendRequest(userId, btn) {
            btn.disabled = true;
            btn.textContent = 'Rejecting...';
            
            fetch('api/friends.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reject_request&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.closest('.friend-request-item').remove();
                    loadFriendRequests();
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Reject';
                }
            });
        }
        
        function addFriendFromSuggestion(userId, btn) {
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            fetch('api/friends.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=send_request&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = '✓ Sent';
                    btn.disabled = true;
                    showToast({
                        actor_name: 'Friend',
                        action: 'request sent',
                        actor_avatar: '../assets/default_avatar.jpg'
                    });
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Add';
                }
            });
        }
        
        // Search friends and posts - Move outside DOMContentLoaded so it can be called from button
        function performSearch(query) {
            const searchResults = document.getElementById('searchResults');
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
                return;
            }
            
            // Search friends
            fetch('api/friends.php?action=search_friends&q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(friendData => {
                    let html = '';
                    
                    // Show friends section
                    if (friendData.success && friendData.users.length > 0) {
                        html += '<div class="search-section"><h4>Friends</h4>';
                        friendData.users.slice(0, 3).forEach(user => {
                            console.log('User avatar:', user.avatar); // Debug
                            html += `
                                <div class="search-item" onclick="showUserProfile(${user.id}, '${user.firstname}', '${user.avatar}')">
                                    <img src="${user.avatar}" alt="${user.firstname}" class="search-item-avatar" onerror="this.src='assets/default_avatar.jpg'">
                                    <div class="search-item-info">
                                        <span class="search-item-name">@${escapeHtml(user.username)}</span>
                                        <span class="search-item-desc">${escapeHtml(user.firstname + ' ' + user.lastname)}</span>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                    }
                    
                    // Search posts by title
                    const posts = document.querySelectorAll('[data-post-title]');
                    let matchedPosts = [];
                    posts.forEach(post => {
                        const title = post.getAttribute('data-post-title') || '';
                        if (title.toLowerCase().includes(query.toLowerCase())) {
                            matchedPosts.push(post);
                        }
                    });
                    
                    if (matchedPosts.length > 0) {
                        html += '<div class="search-section"><h4>Posts</h4>';
                        matchedPosts.slice(0, 3).forEach(post => {
                            const title = post.getAttribute('data-post-title');
                            const author = post.getAttribute('data-post-author');
                            const postId = post.getAttribute('data-post-id');
                            html += `
                                <div class="search-item" onclick="scrollToPost(${postId})">
                                    <div style="flex: 1;">
                                        <span class="search-item-name">${escapeHtml(title.substring(0, 50))}</span>
                                        <span class="search-item-desc">by ${escapeHtml(author)}</span>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                    }
                    
                    if (html) {
                        searchResults.innerHTML = html;
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.innerHTML = '<div style="padding: 15px; text-align: center; color: #999;">No results found</div>';
                        searchResults.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    searchResults.innerHTML = '<div style="padding: 15px; text-align: center; color: red;">Search error</div>';
                    searchResults.style.display = 'block';
                });
        }
        
        // Search friends and posts
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchResults = document.getElementById('searchResults');
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    performSearch(e.target.value.trim());
                });
                
                // Search with Enter key
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        performSearch(e.target.value.trim());
                    }
                });
                
                // Close search dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                        searchResults.style.display = 'none';
                    }
                });
            }
            
            // Search friends in notification tab
            const searchFriendsInput = document.getElementById('searchFriendsInput');
            if (searchFriendsInput) {
                searchFriendsInput.addEventListener('input', function(e) {
                    const query = e.target.value.trim();
                    if (query.length < 2) {
                        document.getElementById('searchFriendsResults').innerHTML = '';
                        return;
                    }
                    
                    fetch('api/friends.php?action=search_friends&q=' + encodeURIComponent(query))
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                const results = document.getElementById('searchFriendsResults');
                                results.innerHTML = '';
                                
                                data.users.forEach(user => {
                                    const item = document.createElement('div');
                                    item.className = 'search-result-item';
                                    
                                    let actionBtn = '<button class="search-result-action" onclick="addFriendFromSearch(' + user.id + ', this)">Add</button>';
                                    if (user.status === 'friend') {
                                        actionBtn = '<span style="color: #31a24c; font-weight: 600;">✓ Friend</span>';
                                    } else if (user.status === 'blocked') {
                                        actionBtn = '<button class="search-result-action" onclick="unblockFromSearch(' + user.id + ', this)">Unblock</button>';
                                    }
                                    
                                    item.innerHTML = `
                                        <img src="${user.avatar}" alt="${user.firstname}" class="search-result-avatar">
                                        <div class="search-result-info">
                                            <h5>${escapeHtml(user.firstname + ' ' + user.lastname)}</h5>
                                            <p>@${escapeHtml(user.username)}</p>
                                        </div>
                                        ${actionBtn}
                                    `;
                                    results.appendChild(item);
                                });
                            }
                        });
                });
            }
        });
        
        // Scroll to post
        function scrollToPost(postId) {
            const post = document.querySelector('[data-post-id="' + postId + '"]');
            if (post) {
                post.scrollIntoView({ behavior: 'smooth', block: 'center' });
                document.getElementById('searchResults').style.display = 'none';
                document.getElementById('searchInput').value = '';
            }
        }
        
        function addFriendFromSearch(userId, btn) {
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            fetch('api/friends.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=send_request&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = '✓ Sent';
                    btn.disabled = true;
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Add';
                }
            });
        }
        
        function unblockFromSearch(userId, btn) {
            fetch('api/friends.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=unblock_user&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = 'Add';
                    btn.onclick = 'addFriendFromSearch(' + userId + ', this)';
                }
            });
        }

        function toggleComment(postId) {
            const commentsSection = document.getElementById('comments-' + postId);
            if (commentsSection.style.display === 'none') {
                commentsSection.style.display = 'block';
            } else {
                commentsSection.style.display = 'none';
            }
        }

        // Handle Comment Submission
        document.querySelectorAll('.comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const postId = this.dataset.postId;
                const input = this.querySelector('.comment-input');
                const content = input.value.trim();
                
                if (!content) {
                    alert('Vui lòng nhập bình luận');
                    return;
                }
                
                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', content);
                
                const btn = this.querySelector('.comment-btn');
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Posting...';
                
                fetch('add_comment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear input
                        input.value = '';
                        
                        // Add comment to the list
                        const commentsList = document.getElementById('comments-list-' + postId);
                        if (commentsList) {
                            const newComment = document.createElement('div');
                            newComment.className = 'comment';
                            newComment.innerHTML = `
                                <img src="${data.comment.avatar}" alt="Avatar" class="comment-avatar">
                                <div class="comment-content">
                                    <h5>${escapeHtml(data.comment.user_name)}</h5>
                                    <p>${escapeHtml(data.comment.content)}</p>
                                    <span class="comment-time">${data.comment.time}</span>
                                </div>
                            `;
                            commentsList.appendChild(newComment);
                        } else {
                            console.error('Comments list not found for post:', postId);
                        }
                        
                        // Update comment count
                        const post = this.closest('.post');
                        if (post) {
                            const commentStats = post.querySelector('.post-stats span:last-child');
                            if (commentStats) {
                                let commentCount = parseInt(commentStats.textContent) || 0;
                                commentStats.textContent = (commentCount + 1) + ' Comments';
                            }
                        }
                        
                        // Show toast notification
                        if (data.toast) {
                            showToast(data.toast);
                        } else {
                            console.log('No toast data received from server');
                        }
                    } else {
                        alert('Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
            });
        });

        function showSection(section) {
            const feedSection = document.getElementById('feed-section');
            const messengerSection = document.getElementById('messenger-section');
            const friendsSection = document.getElementById('friends-section');
            const menuItems = document.querySelectorAll('.menu-item');

            // Remove active class from all menu items
            menuItems.forEach(item => item.classList.remove('active'));

            // Hide all sections
            feedSection.style.display = 'none';
            messengerSection.style.display = 'none';
            friendsSection.style.display = 'none';

            // Show selected section
            if (section === 'feed') {
                feedSection.style.display = 'block';
                menuItems[0].classList.add('active');
            } else if (section === 'messenger') {
                messengerSection.style.display = 'block';
                menuItems[1].classList.add('active');
            } else if (section === 'friends') {
                friendsSection.style.display = 'block';
                menuItems[2].classList.add('active');
            }
        }

        function selectConversation(id) {
            const conversations = document.querySelectorAll('.conversation-item');
            conversations.forEach(conv => conv.classList.remove('active'));
            event.target.closest('.conversation-item').classList.add('active');
        }

        // Handle Post Creation
        const createPostForm = document.getElementById('createPostForm');
        const postContent = document.getElementById('postContent');
        const postImage = document.getElementById('postImage');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const removeImage = document.getElementById('removeImage');

        // Preview image
        postImage.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Remove image
        removeImage.addEventListener('click', function(e) {
            e.preventDefault();
            postImage.value = '';
            imagePreview.style.display = 'none';
        });

        // Submit form
        createPostForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!postContent.value.trim()) {
                alert('Vui lòng nhập nội dung bài viết');
                return;
            }

            const formData = new FormData();
            formData.append('content', postContent.value);
            if (postImage.files.length > 0) {
                formData.append('image', postImage.files[0]);
            }

            const submitBtn = createPostForm.querySelector('.post-btn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting...';

            fetch('create_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    postContent.value = '';
                    postImage.value = '';
                    imagePreview.style.display = 'none';

                    const newPost = createPostElement(data.post);
                    const feedSection = document.getElementById('feed-section');
                    const firstPost = feedSection.querySelector('.post');
                    
                    if (firstPost) {
                        feedSection.insertBefore(newPost, firstPost);
                    } else {
                        feedSection.appendChild(newPost);
                    }

                    alert(data.message);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra. Vui lòng thử lại');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });

        // Create post element
        function createPostElement(post) {
            const postDiv = document.createElement('div');
            postDiv.className = 'post';
            postDiv.setAttribute('data-post-id', post.id);
            postDiv.innerHTML = `
                <div class="post-header">
                    <img src="${post.avatar}" alt="Avatar" class="post-avatar" onclick="showUserProfile(${post.user_id}, '${escapeHtml(post.author)}', '${post.avatar}')" style="cursor: pointer;">
                    <div class="post-info">
                        <h4>${escapeHtml(post.author)}</h4>
                        <span class="post-time">${post.time}</span>
                    </div>
                    <div style="position: relative;">
                        <button class="post-menu" onclick="togglePostMenu(event, ${post.id})">...</button>
                        <div class="post-menu-dropdown" id="post-menu-${post.id}">
                            <div class="post-menu-item" onclick="editPost(${post.id})">✏️ Edit</div>
                            <div class="post-menu-item delete" onclick="deletePost(${post.id})">🗑️ Delete</div>
                        </div>
                    </div>
                </div>

                <div class="post-content">
                    <p id="post-content-${post.id}">${escapeHtml(post.content)}</p>
                    ${post.image ? `<img src="${post.image}" alt="Post image" class="post-image">` : ''}
                </div>

                <div class="post-stats">
                    <span>👍 ${post.likes} Likes</span>
                    <span>${post.comments} Comments</span>
                </div>

                <div class="post-actions-bar">
                    <button class="action-btn ${post.liked ? 'liked' : ''}" onclick="toggleLike(${post.id})">
                        👍 Like
                    </button>
                    <button class="action-btn" onclick="toggleComment(${post.id})">
                        💬 Comment
                    </button>
                    <button class="action-btn">
                        ↗️ Share
                    </button>
                </div>

                <div class="comments-section" id="comments-${post.id}" style="display: none;">
                    <div class="comments-list" id="comments-list-${post.id}"></div>
                    <div class="add-comment">
                        <img src="${post.avatar}" alt="Avatar" class="comment-avatar">
                        <form class="comment-form" data-post-id="${post.id}" style="display: flex; flex: 1; gap: 8px;">
                            <input type="text" placeholder="Write a comment..." class="comment-input" required>
                            <button type="submit" class="comment-btn">Post</button>
                        </form>
                    </div>
                </div>
            `;
            
            // Bind comment form handler for newly created post
            const commentForm = postDiv.querySelector('.comment-form');
            if (commentForm) {
                commentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const postId = this.dataset.postId;
                    const input = this.querySelector('.comment-input');
                    const content = input.value.trim();
                    
                    if (!content) {
                        alert('Vui lòng nhập bình luận');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('post_id', postId);
                    formData.append('content', content);
                    
                    const btn = this.querySelector('.comment-btn');
                    const originalText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = 'Posting...';
                    
                    fetch('add_comment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            input.value = '';
                            const commentsList = document.getElementById('comments-list-' + postId);
                            if (commentsList) {
                                const newComment = document.createElement('div');
                                newComment.className = 'comment';
                                newComment.innerHTML = `
                                    <img src="${data.comment.avatar}" alt="Avatar" class="comment-avatar">
                                    <div class="comment-content">
                                        <h5>${escapeHtml(data.comment.user_name)}</h5>
                                        <p>${escapeHtml(data.comment.content)}</p>
                                        <span class="comment-time">${data.comment.time}</span>
                                    </div>
                                `;
                                commentsList.appendChild(newComment);
                            } else {
                                console.error('Comments list not found for post:', postId);
                            }
                            
                            const post = this.closest('.post');
                            if (post) {
                                const commentStats = post.querySelector('.post-stats span:last-child');
                                if (commentStats) {
                                    let commentCount = parseInt(commentStats.textContent) || 0;
                                    commentStats.textContent = (commentCount + 1) + ' Comments';
                                }
                            }
                            
                            // Show toast notification
                            if (data.toast) {
                                showToast(data.toast);
                            }
                        } else {
                            alert('Lỗi: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Có lỗi xảy ra');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    });
                });
            }
            
            return postDiv;
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ===== FRIEND FUNCTIONS =====
        
        // Toggle post menu
        function togglePostMenu(event, postId) {
            event.stopPropagation();
            const menu = document.getElementById('post-menu-' + postId);
            
            // Close other menus
            document.querySelectorAll('.post-menu-dropdown').forEach(m => {
                if (m.id !== 'post-menu-' + postId) {
                    m.classList.remove('show');
                }
            });
            
            menu.classList.toggle('show');
        }
        
        // Edit post
        function editPost(postId) {
            const postContent = document.getElementById('post-content-' + postId).textContent;
            
            const overlay = document.createElement('div');
            overlay.className = 'edit-modal-overlay';
            overlay.onclick = function(e) {
                if (e.target === this) {
                    this.remove();
                }
            };
            
            overlay.innerHTML = `
                <div class="edit-modal">
                    <h3>Edit Post</h3>
                    <textarea class="edit-modal-input" id="edit-post-input">${escapeHtml(postContent)}</textarea>
                    <div class="edit-modal-actions">
                        <button class="edit-modal-btn cancel" onclick="this.closest('.edit-modal-overlay').remove()">Cancel</button>
                        <button class="edit-modal-btn save" onclick="saveEditPost(${postId})">Save</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            document.getElementById('edit-post-input').focus();
        }
        
        // Save edited post
        function saveEditPost(postId) {
            const newContent = document.getElementById('edit-post-input').value.trim();
            
            if (!newContent) {
                alert('Post content cannot be empty');
                return;
            }
            
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('content', newContent);
            
            fetch('edit_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('post-content-' + postId).textContent = newContent;
                    document.querySelector('.edit-modal-overlay').remove();
                    showToast({
                        actor_name: 'Success',
                        action: 'post updated',
                        actor_avatar: '../assets/default_avatar.jpg'
                    });
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
        
        // Delete post
        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post?')) {
                fetch('delete_post.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'post_id=' + postId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector(`.post[data-post-id="${postId}"]`).remove();
                        showToast({
                            actor_name: 'Success',
                            action: 'post deleted',
                            actor_avatar: '../assets/default_avatar.jpg'
                        });
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
            }
        }
    </script>
</body>
</html>