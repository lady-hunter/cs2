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

// Lấy user_id được click nếu có
$conversation_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Lấy danh sách conversations
$conversations = array();
$sql = "SELECT 
            CASE 
                WHEN c.user_id_1 = ? THEN c.user_id_2 
                ELSE c.user_id_1 
            END as other_user_id,
            u.id, u.firstname, u.lastname, u.username, u.avatar,
            m.message, m.image, m.created_at, m.is_read,
            (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM conversations c
        JOIN users u ON (
            CASE 
                WHEN c.user_id_1 = ? THEN u.id = c.user_id_2
                ELSE u.id = c.user_id_1
            END
        )
        LEFT JOIN messages m ON m.id = c.last_message_id
        WHERE c.user_id_1 = ? OR c.user_id_2 = ?
        ORDER BY c.updated_at DESC";

$stmt = $connection->prepare($sql);
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $avatar = "../assets/default_avatar.jpg";
    if (!empty($row['avatar']) && file_exists($row['avatar'])) {
        $avatar = $row['avatar'];
    }
    
    // Format thời gian
    $message_time = $row['created_at'] ? strtotime($row['created_at']) : null;
    if ($message_time) {
        $current_time = time();
        $diff = $current_time - $message_time;
        
        if ($diff < 60) {
            $time_text = "just now";
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            $time_text = $mins . "m";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            $time_text = $hours . "h";
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            $time_text = $days . "d";
        } else {
            $time_text = date('M d', $message_time);
        }
    } else {
        $time_text = "";
    }
    
    $conversations[] = array(
        'user_id' => $row['id'],
        'firstname' => $row['firstname'],
        'lastname' => $row['lastname'],
        'username' => $row['username'],
        'avatar' => $avatar,
        'last_message' => $row['message'],
        'message_time' => $time_text,
        'unread_count' => $row['unread_count'],
        'is_read' => $row['is_read']
    );
}
$stmt->close();

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

// Lấy tin nhắn nếu chọn user
$messages = array();
$selected_user = null;

if ($conversation_user_id) {
    // Lấy thông tin user được chọn
    $sql = "SELECT id, firstname, lastname, username, avatar FROM users WHERE id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $conversation_user_id);
    $stmt->execute();
    $selected_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($selected_user) {
        if (empty($selected_user['avatar']) || !file_exists($selected_user['avatar'])) {
            $selected_user['avatar'] = "../assets/default_avatar.jpg";
        }
        
        // Lấy tin nhắn giữa 2 user
        $sql = "SELECT id, sender_id, receiver_id, message, image, is_read, created_at 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                ORDER BY created_at ASC";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("iiii", $user_id, $conversation_user_id, $conversation_user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $message_time = strtotime($row['created_at']);
            $current_time = time();
            $diff = $current_time - $message_time;
            
            if ($diff < 60) {
                $time_text = "just now";
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                $time_text = $mins . " min";
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                $time_text = $hours . " hours";
            } else {
                $time_text = date('M d, H:i', $message_time);
            }
            
            $messages[] = array(
                'id' => $row['id'],
                'sender_id' => $row['sender_id'],
                'message' => $row['message'],
                'image' => $row['image'],
                'time' => $time_text,
                'is_read' => $row['is_read']
            );
        }
        $stmt->close();
        
        // Đánh dấu tin nhắn là đã đọc
        $sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("ii", $user_id, $conversation_user_id);
        $stmt->execute();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Random-Chat</title>
    <link rel="stylesheet" href="../css/messages.css">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="messages-container">
        <!-- Sidebar conversations -->
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h2>Messages</h2>
                <div class="sidebar-actions">
                    <button class="btn-icon" id="searchBtn"><i class="fas fa-search"></i></button>
                    <button class="btn-icon" id="newChatBtn"><i class="fas fa-pen"></i></button>
                </div>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search conversations...">
            </div>
            
            <div class="conversations-list" id="conversationsList">
                <?php if (empty($conversations)): ?>
                    <div class="empty-message">
                        <i class="fas fa-comments"></i>
                        <p>No conversations yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?php echo $conversation_user_id == $conv['user_id'] ? 'active' : ''; ?>" 
                             onclick="openConversation(<?php echo $conv['user_id']; ?>)" 
                             data-user-id="<?php echo $conv['user_id']; ?>">
                            <div class="conv-avatar">
                                <img src="<?php echo $conv['avatar']; ?>" alt="<?php echo $conv['firstname']; ?>">
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="conv-info">
                                <div class="conv-header">
                                    <h3><?php echo htmlspecialchars($conv['firstname'] . ' ' . $conv['lastname']); ?></h3>
                                    <span class="conv-time"><?php echo $conv['message_time']; ?></span>
                                </div>
                                <p class="conv-preview"><?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 50)); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat area -->
        <div class="chat-area">
            <?php if ($selected_user): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-user-info">
                        <img src="<?php echo $selected_user['avatar']; ?>" alt="<?php echo $selected_user['firstname']; ?>">
                        <div class="user-info">
                            <h2><?php echo htmlspecialchars($selected_user['firstname'] . ' ' . $selected_user['lastname']); ?></h2>
                            <p class="user-status">Active now</p>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <button class="btn-icon"><i class="fas fa-phone"></i></button>
                        <button class="btn-icon" onclick="initiateVideoCall(<?php echo $conversation_user_id; ?>)"><i class="fas fa-video"></i></button>
                        <button class="btn-icon"><i class="fas fa-info-circle"></i></button>
                    </div>
                </div>
                
                <!-- Messages -->
                <div class="messages-list" id="messagesList">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-group <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                            <div class="message-item">
                                <?php if (!empty($msg['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($msg['image']); ?>" alt="message image" class="message-image">
                                <?php endif; ?>
                                <?php if (!empty($msg['message'])): ?>
                                    <div class="message-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="message-time"><?php echo $msg['time']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Message Input -->
                <div class="message-input-area">
                    <form id="messageForm" onsubmit="sendMessage(event)">
                        <input type="hidden" id="receiverId" value="<?php echo $conversation_user_id; ?>">
                        
                        <div class="input-wrapper">
                            <div class="emoji-container">
                                <button type="button" class="btn-icon" id="emojiBtn"><i class="fas fa-face-smile"></i></button>
                                <div class="emoji-picker" id="emojiPicker">
                                    <div class="emoji-header">
                                        <span class="emoji-tab active" data-category="smileys">😀</span>
                                        <span class="emoji-tab" data-category="gestures">👋</span>
                                        <span class="emoji-tab" data-category="hearts">❤️</span>
                                        <span class="emoji-tab" data-category="animals">🐱</span>
                                        <span class="emoji-tab" data-category="food">🍕</span>
                                        <span class="emoji-tab" data-category="activities">⚽</span>
                                        <span class="emoji-tab" data-category="objects">💡</span>
                                    </div>
                                    <div class="emoji-content" id="emojiContent"></div>
                                </div>
                            </div>
                            <button type="button" class="btn-icon" id="attachBtn"><i class="fas fa-plus"></i></button>
                            
                            <input type="text" id="messageInput" placeholder="Aa" autocomplete="off">
                            <input type="file" id="imageInput" accept="image/*" style="display:none;">
                            
                            <button type="submit" class="btn-send"><i class="fas fa-heart"></i></button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-chat">
                    <i class="fas fa-comments"></i>
                    <h2>Select a conversation to start messaging</h2>
                    <p>Choose from your existing conversations or start a new one</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- New Chat Modal -->
    <div class="modal" id="newChatModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>New Message</h2>
                <button class="btn-close" onclick="closeModal('newChatModal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchModalTab('friends-list')">Friends</button>
                    <button class="modal-tab" onclick="switchModalTab('search-people')">Search</button>
                </div>
                
                <!-- Friends List Tab -->
                <div id="friendsListTab" class="modal-tab-content active">
                    <div id="friendsList" class="friends-list-modal">
                        <?php if (empty($friends)): ?>
                            <div class="modal-empty">No friends yet</div>
                        <?php else: ?>
                            <?php foreach ($friends as $friend): ?>
                                <div class="friend-item" onclick="openConversation(<?php echo $friend['friend_id']; ?>); closeModal('newChatModal');">
                                    <img src="<?php echo $friend['avatar']; ?>" alt="<?php echo $friend['firstname']; ?>" class="friend-item-avatar">
                                    <div class="friend-item-info">
                                        <h4><?php echo htmlspecialchars($friend['firstname'] . ' ' . $friend['lastname']); ?></h4>
                                        <p>@<?php echo htmlspecialchars($friend['username']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Search Tab -->
                <div id="searchPeopleTab" class="modal-tab-content">
                    <input type="text" id="userSearchInput" placeholder="Search people...">
                    <div id="userSearchResults"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const userId = <?php echo $user_id; ?>;
        const currentConversationUserId = <?php echo json_encode($conversation_user_id); ?>;
        
        // Switch modal tabs
        function switchModalTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.modal-tab-content').forEach(el => {
                el.classList.remove('active');
            });
            
            // Remove active from all tab buttons
            document.querySelectorAll('.modal-tab').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show selected tab
            if (tabName === 'friends-list') {
                document.getElementById('friendsListTab').classList.add('active');
            } else {
                document.getElementById('searchPeopleTab').classList.add('active');
            }
            
            // Mark button as active
            event.target.classList.add('active');
        }
        
        // Open conversation
        function openConversation(userId) {
            window.location.href = 'messages.php?user_id=' + userId;
        }
        
        // Send message
        async function sendMessage(event) {
            event.preventDefault();
            
            const receiverId = document.getElementById('receiverId').value;
            const messageInput = document.getElementById('messageInput');
            const imageInput = document.getElementById('imageInput');
            const message = messageInput.value.trim();
            
            if (!message && imageInput.files.length === 0) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_id', receiverId);
            formData.append('message', message);
            
            if (imageInput.files.length > 0) {
                formData.append('image', imageInput.files[0]);
            }
            
            try {
                const response = await fetch('api/messages.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageInput.value = '';
                    imageInput.value = '';
                    loadMessages();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to send message');
            }
        }
        
        // Load messages periodically (real-time effect)
        function loadMessages() {
            if (!currentConversationUserId) return;
            
            fetch('api/messages.php?action=get_messages&user_id=' + currentConversationUserId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const messagesList = document.getElementById('messagesList');
                        messagesList.innerHTML = '';
                        
                        data.messages.forEach(msg => {
                            const messageGroup = document.createElement('div');
                            messageGroup.className = 'message-group ' + (msg.sender_id == userId ? 'sent' : 'received');
                            
                            let content = '';
                            if (msg.image) {
                                content += '<img src="' + msg.image + '" alt="message image" class="message-image">';
                            }
                            if (msg.message) {
                                content += '<div class="message-text">' + escapeHtml(msg.message) + '</div>';
                            }
                            
                            messageGroup.innerHTML = `
                                <div class="message-item">
                                    ${content}
                                </div>
                                <span class="message-time">${msg.time}</span>
                            `;
                            
                            messagesList.appendChild(messageGroup);
                        });
                        
                        // Scroll to bottom
                        messagesList.scrollTop = messagesList.scrollHeight;
                    }
                });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // New chat modal
        document.getElementById('newChatBtn').addEventListener('click', () => {
            document.getElementById('newChatModal').style.display = 'block';
            document.getElementById('userSearchInput').focus();
        });
        
        // Search users
        document.getElementById('userSearchInput').addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            if (query.length < 2) {
                document.getElementById('userSearchResults').innerHTML = '';
                return;
            }
            
            try {
                const response = await fetch('api/messages.php?action=search_users&q=' + encodeURIComponent(query));
                const data = await response.json();
                
                if (data.success) {
                    let html = '';
                    data.users.forEach(user => {
                        html += `
                            <div class="search-result" onclick="openConversation(${user.id}); closeModal('newChatModal');">
                                <img src="${user.avatar}" alt="${user.firstname}">
                                <div>
                                    <h4>${user.firstname} ${user.lastname}</h4>
                                    <p>@${user.username}</p>
                                </div>
                            </div>
                        `;
                    });
                    document.getElementById('userSearchResults').innerHTML = html;
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Search conversations
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.conversation-item');
            
            items.forEach(item => {
                const name = item.querySelector('.conv-header h3').textContent.toLowerCase();
                const preview = item.querySelector('.conv-preview').textContent.toLowerCase();
                
                if (name.includes(query) || preview.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Attach image
        document.getElementById('attachBtn').addEventListener('click', () => {
            document.getElementById('imageInput').click();
        });
        
        document.getElementById('imageInput').addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    // Có thể hiển thị preview tại đây nếu muốn
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        // Auto-load messages every 2 seconds
        setInterval(loadMessages, 2000);
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('newChatModal');
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>

    <!-- Video Call Modal -->
<div id="videoCallModal" class="video-call-modal" style="display: none;">
    <div class="video-call-container">
        <div class="video-grid">
            <video id="remoteVideo" autoplay playsinline></video>
            <video id="localVideo" autoplay muted playsinline></video>
        </div>
        
        <div id="callStatus" class="call-status">Connecting...</div>
        
        <div class="call-controls">
            <button id="toggleAudio" class="control-btn" title="Mute/Unmute">🎤</button>
            <button id="toggleVideo" class="control-btn" title="Camera On/Off">📹</button>
            <button id="toggleScreen" class="control-btn" title="Share Screen">🖥️</button>
            <button id="endCall" class="control-btn end-btn" title="End Call">📵</button>
        </div>
    </div>
</div>

<!-- Incoming Call Notification -->
<div id="incomingCallNotification" class="incoming-call" style="display: none;">
    <div class="incoming-call-card">
        <img id="callerAvatar" src="../assets/default_avatar.jpg" alt="Caller" class="caller-avatar">
        <h3 id="callerName">Incoming Video Call</h3>
        <div class="incoming-call-buttons">
            <button class="btn-accept" onclick="acceptVideoCall()">✓ Accept</button>
            <button class="btn-decline" onclick="declineVideoCall()">✗ Decline</button>
        </div>
    </div>
</div>

<script src="../assets/video-call.js"></script>
<script src="../assets/emoji-picker.js"></script>
</body>
</html>
