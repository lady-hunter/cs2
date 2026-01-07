-- Video Call Tables for Random-Chat Application
-- Run this SQL to create the necessary tables for video call functionality

-- Table to store call history
CREATE TABLE IF NOT EXISTS call_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    caller_id INT NOT NULL,
    receiver_id INT NOT NULL,
    call_type ENUM('video', 'audio') DEFAULT 'video',
    status ENUM('pending', 'completed', 'missed', 'declined') DEFAULT 'pending',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    duration INT DEFAULT 0 COMMENT 'Duration in seconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_caller (caller_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_status (status),
    INDEX idx_started (started_at)
);

-- Table to store WebRTC signaling data
CREATE TABLE IF NOT EXISTS call_signals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    signal_type ENUM('offer', 'answer', 'ice', 'end', 'decline') NOT NULL,
    signal_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_to_user (to_user_id),
    INDEX idx_from_user (from_user_id),
    INDEX idx_created (created_at)
);

-- Optional: Auto-cleanup old signals (run as scheduled event)
-- DELETE FROM call_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Optional: Auto-update missed calls
-- UPDATE call_history SET status = 'missed' WHERE status = 'pending' AND started_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE);
