-- Random Chat Tables
-- Chạy SQL này để tạo các bảng cần thiết

-- Bảng hàng đợi random (người đang chờ ghép)
CREATE TABLE IF NOT EXISTS random_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_joined (joined_at)
);

-- Bảng phiên chat random
CREATE TABLE IF NOT EXISTS random_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    status ENUM('active', 'ended', 'user1_left', 'user2_left') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user1 (user_id_1),
    INDEX idx_user2 (user_id_2),
    INDEX idx_status (status)
);

-- Bảng tin nhắn random (sẽ bị xóa sau khi kết thúc)
CREATE TABLE IF NOT EXISTS random_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES random_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id)
);
