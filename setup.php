<?php
session_start();
require_once './config/db.php';

$errors = [];
$success = [];

// Tạo bảng messages
$create_messages_sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message LONGTEXT NOT NULL,
    image VARCHAR(255),
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX(sender_id, receiver_id),
    INDEX(receiver_id)
)";

if ($connection->query($create_messages_sql)) {
    $success[] = "✓ Bảng 'messages' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'messages': " . $connection->error;
}

// Tạo bảng conversations
$create_conversations_sql = "CREATE TABLE IF NOT EXISTS conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    last_message_id INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL,
    UNIQUE KEY unique_conversation (user_id_1, user_id_2)
)";

if ($connection->query($create_conversations_sql)) {
    $success[] = "✓ Bảng 'conversations' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'conversations': " . $connection->error;
}

// Tạo bảng friends
$create_friends_sql = "CREATE TABLE IF NOT EXISTS friends (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friend (user_id_1, user_id_2),
    INDEX(user_id_1),
    INDEX(user_id_2),
    INDEX(status)
)";

if ($connection->query($create_friends_sql)) {
    $success[] = "✓ Bảng 'friends' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'friends': " . $connection->error;
}

// Tạo bảng blocks
$create_blocks_sql = "CREATE TABLE IF NOT EXISTS blocks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    INDEX(blocker_id),
    INDEX(blocked_id)
)";

if ($connection->query($create_blocks_sql)) {
    $success[] = "✓ Bảng 'blocks' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'blocks': " . $connection->error;
}

// Tạo bảng call_history cho Video Call
$create_call_history_sql = "CREATE TABLE IF NOT EXISTS call_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    caller_id INT NOT NULL,
    receiver_id INT NOT NULL,
    call_type ENUM('video', 'audio') DEFAULT 'video',
    status ENUM('pending', 'completed', 'missed', 'declined') DEFAULT 'pending',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    duration INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_caller (caller_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_status (status)
)";

if ($connection->query($create_call_history_sql)) {
    $success[] = "✓ Bảng 'call_history' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'call_history': " . $connection->error;
}

// Tạo bảng call_signals cho WebRTC signaling
$create_call_signals_sql = "CREATE TABLE IF NOT EXISTS call_signals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    signal_type VARCHAR(20) NOT NULL,
    signal_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_to_user (to_user_id),
    INDEX idx_from_user (from_user_id)
)";

if ($connection->query($create_call_signals_sql)) {
    $success[] = "✓ Bảng 'call_signals' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'call_signals': " . $connection->error;
}

// Tạo bảng random_queue cho Random Chat
$create_random_queue_sql = "CREATE TABLE IF NOT EXISTS random_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_joined_at (joined_at)
)";

if ($connection->query($create_random_queue_sql)) {
    $success[] = "✓ Bảng 'random_queue' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'random_queue': " . $connection->error;
}

// Tạo bảng random_sessions cho Random Chat
$create_random_sessions_sql = "CREATE TABLE IF NOT EXISTS random_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    status ENUM('active', 'user1_left', 'user2_left', 'ended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user1 (user_id_1),
    INDEX idx_user2 (user_id_2),
    INDEX idx_status (status)
)";

if ($connection->query($create_random_sessions_sql)) {
    $success[] = "✓ Bảng 'random_sessions' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'random_sessions': " . $connection->error;
}

// Tạo bảng random_messages cho Random Chat
$create_random_messages_sql = "CREATE TABLE IF NOT EXISTS random_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES random_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_created (created_at)
)";

if ($connection->query($create_random_messages_sql)) {
    $success[] = "✓ Bảng 'random_messages' đã được tạo/cập nhật thành công";
} else {
    $errors[] = "✗ Lỗi tạo bảng 'random_messages': " . $connection->error;
}

$connection->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Messaging System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }
        
        .message {
            padding: 12px 16px;
            margin-bottom: 12px;
            border-radius: 6px;
            font-size: 15px;
            line-height: 1.5;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
            margin-top: 30px;
        }
        
        .next-step {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
        }
        
        .next-step a {
            display: inline-block;
            padding: 12px 30px;
            background: #31a24c;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .next-step a:hover {
            background: #2a8a40;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(49, 162, 76, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Database Setup - Messaging System</h1>
        
        <?php if (!empty($success)): ?>
            <?php foreach ($success as $msg): ?>
                <div class="message success"><?php echo $msg; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $msg): ?>
                <div class="message error"><?php echo $msg; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($errors)): ?>
            <div class="message info">
                <strong>✓ Cài đặt thành công!</strong><br><br>
                Hệ thống nhắn tin của bạn đã sẵn sàng. 
                Các bảng dữ liệu cho messages và conversations đã được tạo thành công.
            </div>
            
            <div class="next-step">
                <p style="color: #666; margin-bottom: 15px;">Nhấn nút bên dưới để bắt đầu sử dụng</p>
                <a href="view/home.php">Go to Home</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
