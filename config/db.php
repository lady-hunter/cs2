<?php
/**
 * Database Connection
 * Kết nối MySQL - Hỗ trợ cả mysqli và PDO
 */

// Load config
require_once __DIR__ . '/../config.php';

// ============================================
// KẾT NỐI MYSQLI (giữ tương thích code cũ)
// ============================================
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

$connection->set_charset("utf8mb4");

// Alias cho các file cũ sử dụng $conn
$conn = $connection;

// ============================================
// KẾT NỐI PDO (cho code mới)
// ============================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $db = new PDO($dsn, DB_USER, DB_PASS);
    
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    // PDO là optional, không die nếu lỗi
}

/*
===== DATABASE STRUCTURE =====

TABLE: users
- id (INT PRIMARY KEY AUTO_INCREMENT)
- firstname (VARCHAR)
- lastname (VARCHAR)
- username (VARCHAR UNIQUE)
- email (VARCHAR UNIQUE)
- password (VARCHAR)
- avatar (VARCHAR)
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

TABLE: posts
- id (INT PRIMARY KEY AUTO_INCREMENT)
- user_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- content (LONGTEXT)
- image (VARCHAR) - NULL (path to image file)
- likes_count (INT DEFAULT 0)
- comments_count (INT DEFAULT 0)
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
- updated_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)

CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    image VARCHAR(255),
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

TABLE: post_likes
- id (INT PRIMARY KEY AUTO_INCREMENT)
- post_id (INT FOREIGN KEY REFERENCES posts(id) ON DELETE CASCADE)
- user_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
- UNIQUE(post_id, user_id)

TABLE: comments
- id (INT PRIMARY KEY AUTO_INCREMENT)
- post_id (INT FOREIGN KEY REFERENCES posts(id) ON DELETE CASCADE)
- user_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- content (TEXT)
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

TABLE: notifications
- id (INT PRIMARY KEY AUTO_INCREMENT)
- user_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE) - Người nhận thông báo
- actor_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE) - Người tạo action (like, comment)
- type (VARCHAR) - 'like' hoặc 'comment'
- post_id (INT FOREIGN KEY REFERENCES posts(id) ON DELETE CASCADE)
- comment_id (INT FOREIGN KEY REFERENCES comments(id) ON DELETE CASCADE NULL) - Khi là comment
- content (VARCHAR) - Nội dung tóm tắt (preview của comment)
- is_read (BOOLEAN DEFAULT 0)
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    actor_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    post_id INT NOT NULL,
    comment_id INT,
    content VARCHAR(255),
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

TABLE: messages (Nhắn tin giữa 2 user)
- id (INT PRIMARY KEY AUTO_INCREMENT)
- sender_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- receiver_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- message (LONGTEXT)
- image (VARCHAR) - NULL (đường dẫn ảnh trong tin nhắn)
- is_read (BOOLEAN DEFAULT 0)
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

TABLE: conversations (Lưu thông tin cuộc trò chuyện)
- id (INT PRIMARY KEY AUTO_INCREMENT)
- user_id_1 (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- user_id_2 (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- last_message_id (INT FOREIGN KEY REFERENCES messages(id) ON DELETE SET NULL)
- updated_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- UNIQUE(user_id_1, user_id_2)

CREATE TABLE messages (
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
);

CREATE TABLE conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    last_message_id INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL,
    UNIQUE KEY unique_conversation (user_id_1, user_id_2)
);

TABLE: friends (Bạn bè)
- id (INT PRIMARY KEY AUTO_INCREMENT)
- user_id_1 (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- user_id_2 (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE)
- status (VARCHAR) - 'pending', 'accepted', 'rejected'
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
- UNIQUE(user_id_1, user_id_2)

TABLE: blocks (Chặn người dùng)
- id (INT PRIMARY KEY AUTO_INCREMENT)
- blocker_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE) - Người chặn
- blocked_id (INT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE) - Người bị chặn
- created_at (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
- UNIQUE(blocker_id, blocked_id)

CREATE TABLE friends (
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
);

CREATE TABLE blocks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    INDEX(blocker_id),
    INDEX(blocked_id)
);

===================================
*/
?>
