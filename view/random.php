<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $connection->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Chat - Kết nối ngẫu nhiên</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/random.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="home.php" class="nav-logo">
                <i class="fas fa-comments"></i> Random-Chat
            </a>
            <div class="nav-menu">
                <a href="home.php" class="nav-item"><i class="fas fa-home"></i> Feed</a>
                <a href="messages.php" class="nav-item"><i class="fas fa-envelope"></i> Messenger</a>
                <a href="friends.php" class="nav-item"><i class="fas fa-user-friends"></i> Friends</a>
                <a href="random.php" class="nav-item active"><i class="fas fa-random"></i> Random</a>
                <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
                <a href="login.php?logout=true" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="random-container">
        <!-- Màn hình chờ -->
        <div id="idleScreen" class="screen active">
            <div class="idle-content">
                <div class="idle-icon">
                    <i class="fas fa-random"></i>
                </div>
                <h1>Kết nối ngẫu nhiên</h1>
                <p>Nhấn nút bên dưới để được ghép đôi với một người lạ ngẫu nhiên. Cuộc trò chuyện sẽ hoàn toàn ẩn danh!</p>
                <button id="startMatchBtn" class="start-btn">
                    <i class="fas fa-play"></i> Bắt đầu tìm kiếm
                </button>
            </div>
        </div>

        <!-- Màn hình đang tìm -->
        <div id="searchingScreen" class="screen">
            <div class="searching-content">
                <div class="searching-animation">
                    <div class="pulse-ring"></div>
                    <div class="pulse-ring"></div>
                    <div class="pulse-ring"></div>
                    <div class="searching-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                <h2>Đang tìm kiếm...</h2>
                <p>Vui lòng đợi trong khi chúng tôi tìm người phù hợp cho bạn</p>
                <div class="searching-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <button id="cancelSearchBtn" class="cancel-btn">
                    <i class="fas fa-times"></i> Hủy tìm kiếm
                </button>
            </div>
        </div>

        <!-- Màn hình chat -->
        <div id="chatScreen" class="screen">
            <div class="chat-header">
                <div class="partner-info">
                    <img id="partnerAvatar" src="../assets/default_avatar.jpg" alt="Partner">
                    <div class="partner-details">
                        <span id="partnerName">Người lạ</span>
                        <span class="status-text"><i class="fas fa-circle"></i> Đang online</span>
                    </div>
                </div>
                <div class="chat-actions">
                    <button id="skipBtn" class="action-btn skip-btn" title="Tìm người khác">
                        <i class="fas fa-forward"></i>
                    </button>
                    <button id="leaveChatBtn" class="action-btn leave-btn" title="Kết thúc">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </div>
            </div>
            
            <div id="chatMessages" class="chat-messages">
                <div class="system-message">
                    <i class="fas fa-info-circle"></i>
                    Bạn đã được kết nối với một người lạ. Hãy nói xin chào!
                </div>
            </div>
            
            <div class="chat-input-area">
                <div class="emoji-container">
                    <button type="button" class="emoji-btn" id="emojiBtn">
                        <i class="fas fa-face-smile"></i>
                    </button>
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
                <input type="text" id="messageInput" placeholder="Nhập tin nhắn..." autocomplete="off">
                <button id="sendMessageBtn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>

        <!-- Modal partner rời đi -->
        <div id="partnerLeftModal" class="modal">
            <div class="modal-content">
                <div class="modal-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
                <h3>Người lạ đã rời đi</h3>
                <p>Cuộc trò chuyện đã kết thúc. Bạn có muốn kết bạn với người này không?</p>
                <div class="modal-actions">
                    <button id="addFriendBtn" class="btn-primary">
                        <i class="fas fa-user-plus"></i> Kết bạn
                    </button>
                    <button id="findNewBtn" class="btn-secondary">
                        <i class="fas fa-search"></i> Tìm người mới
                    </button>
                    <button id="goHomeBtn" class="btn-outline">
                        <i class="fas fa-home"></i> Về trang chủ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // RANDOM CHAT JAVASCRIPT
        // ============================================
        
        const RandomChat = {
            currentSessionId: null,
            currentPartnerId: null,
            currentPartnerName: null,
            lastMessageId: 0,
            pollInterval: null,
            matchCheckInterval: null,
            
            // Khởi tạo
            init() {
                this.bindEvents();
                this.checkCurrentStatus();
            },
            
            // Bind events
            bindEvents() {
                document.getElementById('startMatchBtn').addEventListener('click', () => this.startMatching());
                document.getElementById('cancelSearchBtn').addEventListener('click', () => this.cancelSearch());
                document.getElementById('sendMessageBtn').addEventListener('click', () => this.sendMessage());
                document.getElementById('messageInput').addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') this.sendMessage();
                });
                document.getElementById('skipBtn').addEventListener('click', () => this.skipPartner());
                document.getElementById('leaveChatBtn').addEventListener('click', () => this.leaveChat());
                document.getElementById('addFriendBtn').addEventListener('click', () => this.addFriend());
                document.getElementById('findNewBtn').addEventListener('click', () => this.findNew());
                document.getElementById('goHomeBtn').addEventListener('click', () => {
                    window.location.href = 'home.php';
                });
                
                // Xử lý khi rời trang
                window.addEventListener('beforeunload', () => {
                    if (this.currentSessionId) {
                        navigator.sendBeacon('api/random_chat.php?action=leave_chat', 
                            new URLSearchParams({session_id: this.currentSessionId}));
                    }
                });
            },
            
            // Hiển thị màn hình
            showScreen(screenId) {
                document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
                document.getElementById(screenId).classList.add('active');
            },
            
            // Kiểm tra trạng thái hiện tại
            async checkCurrentStatus() {
                try {
                    const response = await fetch('api/random_chat.php?action=get_status');
                    const data = await response.json();
                    
                    if (data.status === 'waiting') {
                        this.showScreen('searchingScreen');
                        this.startMatchCheck();
                    } else if (data.status === 'matched') {
                        this.currentSessionId = data.session_id;
                        this.currentPartnerId = data.partner.id;
                        this.currentPartnerName = data.partner.name;
                        this.onMatchFound(data.partner);
                    }
                } catch (error) {
                    console.error('Error checking status:', error);
                }
            },
            
            // Bắt đầu tìm kiếm
            async startMatching() {
                try {
                    this.showScreen('searchingScreen');
                    
                    const response = await fetch('api/random_chat.php?action=join_queue', {
                        method: 'POST'
                    });
                    const data = await response.json();
                    
                    if (data.status === 'matched') {
                        this.currentSessionId = data.session_id;
                        this.currentPartnerId = data.partner.id;
                        this.currentPartnerName = data.partner.name;
                        this.onMatchFound(data.partner);
                    } else if (data.status === 'waiting') {
                        this.startMatchCheck();
                    } else if (data.status === 'already_in_session') {
                        this.checkCurrentStatus();
                    }
                } catch (error) {
                    console.error('Error starting match:', error);
                    this.showScreen('idleScreen');
                }
            },
            
            // Kiểm tra match định kỳ
            startMatchCheck() {
                if (this.matchCheckInterval) {
                    clearInterval(this.matchCheckInterval);
                }
                
                this.matchCheckInterval = setInterval(async () => {
                    try {
                        const response = await fetch('api/random_chat.php?action=check_match');
                        const data = await response.json();
                        
                        if (data.status === 'matched') {
                            clearInterval(this.matchCheckInterval);
                            this.currentSessionId = data.session_id;
                            this.currentPartnerId = data.partner.id;
                            this.currentPartnerName = data.partner.name;
                            this.onMatchFound(data.partner);
                        }
                    } catch (error) {
                        console.error('Error checking match:', error);
                    }
                }, 2000);
            },
            
            // Khi tìm thấy match
            onMatchFound(partner) {
                clearInterval(this.matchCheckInterval);
                
                document.getElementById('partnerAvatar').src = partner.avatar;
                document.getElementById('partnerName').textContent = partner.name;
                
                // Reset chat
                document.getElementById('chatMessages').innerHTML = `
                    <div class="system-message">
                        <i class="fas fa-info-circle"></i>
                        Bạn đã được kết nối với <strong>${partner.name}</strong>. Hãy nói xin chào!
                    </div>
                `;
                this.lastMessageId = 0;
                
                this.showScreen('chatScreen');
                this.startPolling();
            },
            
            // Hủy tìm kiếm
            async cancelSearch() {
                clearInterval(this.matchCheckInterval);
                
                try {
                    await fetch('api/random_chat.php?action=leave_queue', {
                        method: 'POST'
                    });
                } catch (error) {
                    console.error('Error canceling search:', error);
                }
                
                this.showScreen('idleScreen');
            },
            
            // Gửi tin nhắn
            async sendMessage() {
                const input = document.getElementById('messageInput');
                const message = input.value.trim();
                
                if (!message || !this.currentSessionId) return;
                
                input.value = '';
                
                // Thêm tin nhắn vào UI ngay
                this.appendMessage({
                    message: message,
                    is_mine: true,
                    time: new Date().toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})
                });
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('session_id', this.currentSessionId);
                    formData.append('message', message);
                    
                    await fetch('api/random_chat.php', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Error sending message:', error);
                }
            },
            
            // Thêm tin nhắn vào chat
            appendMessage(msg) {
                const chatMessages = document.getElementById('chatMessages');
                const div = document.createElement('div');
                div.className = `message ${msg.is_mine ? 'sent' : 'received'}`;
                div.innerHTML = `
                    <div class="message-content">${this.escapeHtml(msg.message)}</div>
                    <div class="message-time">${msg.time}</div>
                `;
                chatMessages.appendChild(div);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            },
            
            // Escape HTML
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },
            
            // Polling tin nhắn
            startPolling() {
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                }
                
                this.pollInterval = setInterval(() => this.fetchMessages(), 1500);
            },
            
            stopPolling() {
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
            },
            
            // Fetch tin nhắn mới
            async fetchMessages() {
                if (!this.currentSessionId) return;
                
                try {
                    const response = await fetch(`api/random_chat.php?action=get_messages&session_id=${this.currentSessionId}&last_id=${this.lastMessageId}`);
                    const data = await response.json();
                    
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    // Thêm tin nhắn mới
                    data.messages.forEach(msg => {
                        if (!msg.is_mine) {
                            this.appendMessage(msg);
                        }
                        this.lastMessageId = Math.max(this.lastMessageId, msg.id);
                    });
                    
                    // Partner rời đi
                    if (data.partner_left) {
                        this.stopPolling();
                        document.getElementById('partnerLeftModal').classList.add('active');
                    }
                } catch (error) {
                    console.error('Error fetching messages:', error);
                }
            },
            
            // Bỏ qua partner, tìm người mới
            async skipPartner() {
                if (!confirm('Bạn có chắc muốn tìm người mới?')) return;
                
                this.stopPolling();
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'skip_partner');
                    formData.append('session_id', this.currentSessionId);
                    
                    const response = await fetch('api/random_chat.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.status === 'matched') {
                        this.currentSessionId = data.session_id;
                        this.currentPartnerId = data.partner.id;
                        this.currentPartnerName = data.partner.name;
                        this.onMatchFound(data.partner);
                    } else {
                        this.currentSessionId = null;
                        this.currentPartnerId = null;
                        this.showScreen('searchingScreen');
                        this.startMatchCheck();
                    }
                } catch (error) {
                    console.error('Error skipping partner:', error);
                }
            },
            
            // Rời chat
            async leaveChat() {
                if (!confirm('Bạn có chắc muốn kết thúc cuộc trò chuyện?')) return;
                
                this.stopPolling();
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'leave_chat');
                    formData.append('session_id', this.currentSessionId);
                    
                    await fetch('api/random_chat.php', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Error leaving chat:', error);
                }
                
                // Hiện modal
                document.getElementById('partnerLeftModal').classList.add('active');
            },
            
            // Kết bạn
            async addFriend() {
                if (!this.currentPartnerId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'send_friend_request');
                    formData.append('partner_id', this.currentPartnerId);
                    
                    const response = await fetch('api/random_chat.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        alert('Đã gửi lời mời kết bạn thành công!');
                    } else if (data.status === 'already_friends') {
                        alert('Bạn và người này đã là bạn bè!');
                    } else if (data.status === 'request_pending') {
                        alert('Đã có lời mời kết bạn trước đó!');
                    }
                } catch (error) {
                    console.error('Error adding friend:', error);
                    alert('Có lỗi xảy ra!');
                }
                
                this.findNew();
            },
            
            // Tìm người mới
            findNew() {
                document.getElementById('partnerLeftModal').classList.remove('active');
                this.currentSessionId = null;
                this.currentPartnerId = null;
                this.startMatching();
            }
        };
        
        // Khởi động
        document.addEventListener('DOMContentLoaded', () => RandomChat.init());
    </script>
    <script src="../assets/emoji-picker.js"></script>
</body>
</html>
