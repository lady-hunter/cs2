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

// Lấy danh sách bạn bè
$friends = array();
$sql = "SELECT CASE
            WHEN user_id_1 = ? THEN user_id_2
            ELSE user_id_1
        END as friend_id,
        u.firstname, u.lastname, u.username, u.avatar
        FROM friends f
        JOIN users u ON (
            CASE
                WHEN user_id_1 = ? THEN u.id = f.user_id_2
                ELSE u.id = f.user_id_1
            END
        )
        WHERE (user_id_1 = ? OR user_id_2 = ?)
        AND status = 'accepted'
        ORDER BY u.firstname ASC";

$stmt = $connection->prepare($sql);
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $avatar = "../assets/default_avatar.jpg";
    if (!empty($row['avatar']) && file_exists($row['avatar'])) {
        $avatar = $row['avatar'];
    }
    
    $friends[] = array(
        'friend_id' => $row['friend_id'],
        'firstname' => $row['firstname'],
        'lastname' => $row['lastname'],
        'username' => $row['username'],
        'avatar' => $avatar
    );
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - Random-Chat</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/friends.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="home-page">
    <!-- HEADER -->
    <div class="header">
        <div class="header-container">
            <div class="logo">Random-Chat</div>
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
                <a href="home.php" class="menu-item">Feed</a>
                <a href="messages.php" class="menu-item">Messenger</a>
                <a href="friends.php" class="menu-item active">Friends</a>
                <a href="#" class="menu-item">Photos</a>
                <a href="settings.php" class="menu-item">Settings</a>
            </div>
        </aside>

        <!-- FRIENDS SECTION -->
        <main class="feed" id="friends-section">
            <div class="friends-container">
                <div class="friends-header">
                    <h2>Friends</h2>
                    <span class="friends-count"><?php echo count($friends); ?></span>
                </div>
                
                <div class="search-friends-box">
                    <input type="text" id="searchFriendsInput" placeholder="Search friends...">
                </div>
                
                <div class="friends-grid" id="friendsGrid">
                    <?php if (empty($friends)): ?>
                        <div class="friends-empty">
                            <i class="fas fa-user-friends"></i>
                            <p>No friends yet</p>
                            <a href="home.php" class="btn-btn-primary">Find Friends</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($friends as $friend): ?>
                            <div class="friend-card" data-friend-name="<?php echo htmlspecialchars($friend['firstname'] . ' ' . $friend['lastname']); ?>">
                                <div class="friend-card-image">
                                    <img src="<?php echo $friend['avatar']; ?>" alt="<?php echo $friend['firstname']; ?>">
                                </div>
                                <div class="friend-card-info">
                                    <h3><?php echo htmlspecialchars($friend['firstname'] . ' ' . $friend['lastname']); ?></h3>
                                    <p>@<?php echo htmlspecialchars($friend['username']); ?></p>
                                </div>
                                <div class="friend-card-actions">
                                    <button class="btn-action btn-message" onclick="openMessages(<?php echo $friend['friend_id']; ?>)" title="Message">
                                        <i class="fas fa-comment"></i> Message
                                    </button>
                                    <button class="btn-action btn-remove" onclick="removeFriendAction(<?php echo $friend['friend_id']; ?>, this)" title="Remove">
                                        <i class="fas fa-user-minus"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        const userId = <?php echo $user_id; ?>;
        
        // Search friends
        document.getElementById('searchFriendsInput').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.friend-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-friend-name').toLowerCase();
                if (name.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Open messages
        function openMessages(friendId) {
            window.location.href = 'messages.php?user_id=' + friendId;
        }
        
        // Remove friend
        function removeFriendAction(friendId, btn) {
            if (confirm('Remove this friend?')) {
                btn.disabled = true;
                btn.textContent = 'Removing...';
                
                fetch('api/friends.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=remove_friend&user_id=' + friendId
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.closest('.friend-card').remove();
                        showToast('Friend removed');
                        
                        // Update friends count
                        const count = document.querySelectorAll('.friend-card').length;
                        document.querySelector('.friends-count').textContent = count;
                        
                        if (count === 0) {
                            document.getElementById('friendsGrid').innerHTML = `
                                <div class="friends-empty">
                                    <i class="fas fa-user-friends"></i>
                                    <p>No friends yet</p>
                                    <a href="home.php" class="btn-btn-primary">Find Friends</a>
                                </div>
                            `;
                        }
                    } else {
                        alert('Error: ' + data.message);
                        btn.disabled = false;
                    }
                });
            }
        }
        
        // Toast notification
        function showToast(message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `
                <div class="toast-content">
                    <span>${message}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('hide');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 3000);
        }
    </script>
</body>
</html>
