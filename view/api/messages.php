<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

// Kiểm tra user đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
    case 'send_message':
        sendMessage();
        break;
    case 'get_messages':
        getMessages();
        break;
    case 'search_users':
        searchUsers();
        break;
    case 'get_conversations':
        getConversations();
        break;
    case 'delete_message':
        deleteMessage();
        break;
    case 'mark_as_read':
        markAsRead();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function sendMessage() {
    global $connection, $user_id;
    
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    $image = null;
    
    if (!$receiver_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
        return;
    }
    
    // Xử lý upload ảnh nếu có
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../../assets/messages/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
            $image = $file_path;
        }
    }
    
    if (empty($message) && !$image) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        return;
    }
    
    // Lưu tin nhắn vào database
    $sql = "INSERT INTO messages (sender_id, receiver_id, message, image) VALUES (?, ?, ?, ?)";
    $stmt = $connection->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $connection->error]);
        return;
    }
    
    $stmt->bind_param("iiss", $user_id, $receiver_id, $message, $image);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to save message']);
        $stmt->close();
        return;
    }
    
    $message_id = $stmt->insert_id;
    $stmt->close();
    
    // Cập nhật hoặc tạo conversation
    $sql = "INSERT INTO conversations (user_id_1, user_id_2, last_message_id) 
            VALUES (
                LEAST(?, ?),
                GREATEST(?, ?),
                ?
            )
            ON DUPLICATE KEY UPDATE last_message_id = VALUES(last_message_id), updated_at = CURRENT_TIMESTAMP";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("iiiii", $user_id, $receiver_id, $user_id, $receiver_id, $message_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent',
        'message_id' => $message_id
    ]);
}

function getMessages() {
    global $connection, $user_id;
    
    $other_user_id = intval($_GET['user_id'] ?? 0);
    
    if (!$other_user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    $sql = "SELECT id, sender_id, message, image, is_read, created_at 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    
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
        
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'message' => $row['message'],
            'image' => $row['image'],
            'time' => $time_text,
            'is_read' => $row['is_read']
        ];
    }
    
    $stmt->close();
    
    // Đánh dấu tin nhắn là đã đọc
    $mark_sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
    $mark_stmt = $connection->prepare($mark_sql);
    $mark_stmt->bind_param("ii", $user_id, $other_user_id);
    $mark_stmt->execute();
    $mark_stmt->close();
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}

function searchUsers() {
    global $connection, $user_id;
    
    $query = '%' . $_GET['q'] . '%';
    
    $sql = "SELECT id, firstname, lastname, username, avatar 
            FROM users 
            WHERE (firstname LIKE ? OR lastname LIKE ? OR username LIKE ?) 
            AND id != ?
            LIMIT 10";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("sssi", $query, $query, $query, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['avatar']) || !file_exists($row['avatar'])) {
            $row['avatar'] = "../assets/default_avatar.jpg";
        }
        $users[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}

function getConversations() {
    global $connection, $user_id;
    
    $sql = "SELECT 
                CASE 
                    WHEN c.user_id_1 = ? THEN c.user_id_2 
                    ELSE c.user_id_1 
                END as other_user_id,
                u.id, u.firstname, u.lastname, u.username, u.avatar,
                m.message, m.image, m.created_at,
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
    
    $conversations = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['avatar']) || !file_exists($row['avatar'])) {
            $row['avatar'] = "../assets/default_avatar.jpg";
        }
        
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
        
        $conversations[] = [
            'user_id' => $row['id'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'username' => $row['username'],
            'avatar' => $row['avatar'],
            'last_message' => $row['message'],
            'message_time' => $time_text,
            'unread_count' => $row['unread_count']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
}

function deleteMessage() {
    global $connection, $user_id;
    
    $message_id = intval($_POST['message_id'] ?? 0);
    
    if (!$message_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid message']);
        return;
    }
    
    // Kiểm tra xem message có phải của user hay không
    $sql = "SELECT image FROM messages WHERE id = ? AND sender_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        $stmt->close();
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Xóa ảnh nếu có
    if (!empty($row['image']) && file_exists($row['image'])) {
        unlink($row['image']);
    }
    
    // Xóa message
    $sql = "DELETE FROM messages WHERE id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Message deleted']);
}

function markAsRead() {
    global $connection, $user_id;
    
    $conversation_user_id = intval($_POST['user_id'] ?? 0);
    
    if (!$conversation_user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    $sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id, $conversation_user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Messages marked as read']);
}
?>
