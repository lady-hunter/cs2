<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = $connection;
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'join_queue':
        joinQueue($conn, $user_id);
        break;
    case 'leave_queue':
        leaveQueue($conn, $user_id);
        break;
    case 'check_match':
        checkMatch($conn, $user_id);
        break;
    case 'send_message':
        sendMessage($conn, $user_id);
        break;
    case 'get_messages':
        getMessages($conn, $user_id);
        break;
    case 'leave_chat':
        leaveChat($conn, $user_id);
        break;
    case 'get_status':
        getStatus($conn, $user_id);
        break;
    case 'send_friend_request':
        sendFriendRequest($conn, $user_id);
        break;
    case 'skip_partner':
        skipPartner($conn, $user_id);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

// ============================================
// THAM GIA HÀNG ĐỢI
// ============================================
function joinQueue($conn, $user_id) {
    // Kiểm tra xem đã trong session active chưa
    $stmt = $conn->prepare("SELECT id FROM random_sessions WHERE (user_id_1 = ? OR user_id_2 = ?) AND status = 'active'");
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'already_in_session']);
        return;
    }
    
    // Xóa khỏi queue cũ nếu có
    $conn->query("DELETE FROM random_queue WHERE user_id = $user_id");
    
    // Tìm người khác trong queue
    $stmt = $conn->prepare("SELECT user_id FROM random_queue WHERE user_id != ? ORDER BY joined_at ASC LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Tìm thấy người khác -> tạo session
        $partner_id = $row['user_id'];
        
        // Xóa partner khỏi queue
        $conn->query("DELETE FROM random_queue WHERE user_id = $partner_id");
        
        // Tạo session mới
        $stmt = $conn->prepare("INSERT INTO random_sessions (user_id_1, user_id_2, status, created_at) VALUES (?, ?, 'active', NOW())");
        $stmt->bind_param('ii', $user_id, $partner_id);
        $stmt->execute();
        $session_id = $conn->insert_id;
        
        // Lấy thông tin partner
        $stmt = $conn->prepare("SELECT id, firstname, lastname, avatar FROM users WHERE id = ?");
        $stmt->bind_param('i', $partner_id);
        $stmt->execute();
        $partner = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'status' => 'matched',
            'session_id' => $session_id,
            'partner' => [
                'id' => $partner['id'],
                'name' => $partner['firstname'] . ' ' . $partner['lastname'],
                'avatar' => $partner['avatar'] ?: '../assets/default_avatar.jpg'
            ]
        ]);
    } else {
        // Chưa có ai -> thêm vào queue
        $stmt = $conn->prepare("INSERT INTO random_queue (user_id, joined_at) VALUES (?, NOW())");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        echo json_encode(['status' => 'waiting']);
    }
}

// ============================================
// RỜI HÀNG ĐỢI
// ============================================
function leaveQueue($conn, $user_id) {
    $conn->query("DELETE FROM random_queue WHERE user_id = $user_id");
    echo json_encode(['success' => true]);
}

// ============================================
// KIỂM TRA ĐÃ ĐƯỢC GHÉP CHƯA
// ============================================
function checkMatch($conn, $user_id) {
    // Kiểm tra trong queue
    $stmt = $conn->prepare("SELECT id FROM random_queue WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $in_queue = $stmt->get_result()->num_rows > 0;
    
    // Kiểm tra session active
    $stmt = $conn->prepare("
        SELECT rs.id as session_id, 
               CASE WHEN rs.user_id_1 = ? THEN rs.user_id_2 ELSE rs.user_id_1 END as partner_id,
               u.firstname, u.lastname, u.avatar
        FROM random_sessions rs
        JOIN users u ON u.id = CASE WHEN rs.user_id_1 = ? THEN rs.user_id_2 ELSE rs.user_id_1 END
        WHERE (rs.user_id_1 = ? OR rs.user_id_2 = ?) AND rs.status = 'active'
    ");
    $stmt->bind_param('iiii', $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'matched',
            'session_id' => $row['session_id'],
            'partner' => [
                'id' => $row['partner_id'],
                'name' => $row['firstname'] . ' ' . $row['lastname'],
                'avatar' => $row['avatar'] ?: '../assets/default_avatar.jpg'
            ]
        ]);
    } else if ($in_queue) {
        // Thử tìm lại người khác
        joinQueue($conn, $user_id);
    } else {
        echo json_encode(['status' => 'idle']);
    }
}

// ============================================
// GỬI TIN NHẮN
// ============================================
function sendMessage($conn, $user_id) {
    $session_id = $_POST['session_id'] ?? null;
    $message = trim($_POST['message'] ?? '');
    
    if (!$session_id || !$message) {
        echo json_encode(['error' => 'Missing data']);
        return;
    }
    
    // Verify user is in session
    $stmt = $conn->prepare("SELECT id FROM random_sessions WHERE id = ? AND (user_id_1 = ? OR user_id_2 = ?) AND status = 'active'");
    $stmt->bind_param('iii', $session_id, $user_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows == 0) {
        echo json_encode(['error' => 'Invalid session']);
        return;
    }
    
    // Lưu tin nhắn
    $stmt = $conn->prepare("INSERT INTO random_messages (session_id, sender_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('iis', $session_id, $user_id, $message);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
    } else {
        echo json_encode(['error' => 'Failed to send']);
    }
}

// ============================================
// LẤY TIN NHẮN
// ============================================
function getMessages($conn, $user_id) {
    $session_id = $_GET['session_id'] ?? null;
    $last_id = $_GET['last_id'] ?? 0;
    
    if (!$session_id) {
        echo json_encode(['error' => 'Missing session_id']);
        return;
    }
    
    // Verify user is in session
    $stmt = $conn->prepare("SELECT id, status, user_id_1, user_id_2 FROM random_sessions WHERE id = ? AND (user_id_1 = ? OR user_id_2 = ?)");
    $stmt->bind_param('iii', $session_id, $user_id, $user_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    
    if (!$session) {
        echo json_encode(['error' => 'Invalid session']);
        return;
    }
    
    // Kiểm tra partner đã rời chưa
    $partner_left = false;
    if ($session['status'] == 'user1_left' && $session['user_id_1'] != $user_id) {
        $partner_left = true;
    }
    if ($session['status'] == 'user2_left' && $session['user_id_2'] != $user_id) {
        $partner_left = true;
    }
    if ($session['status'] == 'ended') {
        $partner_left = true;
    }
    
    // Lấy tin nhắn mới
    $stmt = $conn->prepare("SELECT id, sender_id, message, created_at FROM random_messages WHERE session_id = ? AND id > ? ORDER BY id ASC");
    $stmt->bind_param('ii', $session_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'message' => $row['message'],
            'is_mine' => $row['sender_id'] == $user_id,
            'time' => date('H:i', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode([
        'messages' => $messages,
        'partner_left' => $partner_left,
        'session_status' => $session['status']
    ]);
}

// ============================================
// RỜI CUỘC TRÒ CHUYỆN
// ============================================
function leaveChat($conn, $user_id) {
    $session_id = $_POST['session_id'] ?? null;
    
    if (!$session_id) {
        echo json_encode(['error' => 'Missing session_id']);
        return;
    }
    
    // Lấy thông tin session
    $stmt = $conn->prepare("SELECT user_id_1, user_id_2, status FROM random_sessions WHERE id = ? AND (user_id_1 = ? OR user_id_2 = ?)");
    $stmt->bind_param('iii', $session_id, $user_id, $user_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    
    if (!$session) {
        echo json_encode(['error' => 'Invalid session']);
        return;
    }
    
    $partner_id = ($session['user_id_1'] == $user_id) ? $session['user_id_2'] : $session['user_id_1'];
    
    // Cập nhật trạng thái
    if ($session['status'] == 'active') {
        // Người đầu tiên rời
        $new_status = ($session['user_id_1'] == $user_id) ? 'user1_left' : 'user2_left';
        $stmt = $conn->prepare("UPDATE random_sessions SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $new_status, $session_id);
        $stmt->execute();
    } else if ($session['status'] == 'user1_left' || $session['status'] == 'user2_left') {
        // Người thứ 2 rời -> kết thúc và xóa tin nhắn
        $stmt = $conn->prepare("UPDATE random_sessions SET status = 'ended', ended_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $session_id);
        $stmt->execute();
        
        // Xóa tin nhắn
        $conn->query("DELETE FROM random_messages WHERE session_id = $session_id");
    }
    
    echo json_encode([
        'success' => true,
        'partner_id' => $partner_id
    ]);
}

// ============================================
// LẤY TRẠNG THÁI HIỆN TẠI
// ============================================
function getStatus($conn, $user_id) {
    // Kiểm tra trong queue
    $stmt = $conn->prepare("SELECT id FROM random_queue WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'waiting']);
        return;
    }
    
    // Kiểm tra session active
    $stmt = $conn->prepare("
        SELECT rs.id as session_id,
               CASE WHEN rs.user_id_1 = ? THEN rs.user_id_2 ELSE rs.user_id_1 END as partner_id,
               u.firstname, u.lastname, u.avatar
        FROM random_sessions rs
        JOIN users u ON u.id = CASE WHEN rs.user_id_1 = ? THEN rs.user_id_2 ELSE rs.user_id_1 END
        WHERE (rs.user_id_1 = ? OR rs.user_id_2 = ?) AND rs.status = 'active'
    ");
    $stmt->bind_param('iiii', $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'matched',
            'session_id' => $row['session_id'],
            'partner' => [
                'id' => $row['partner_id'],
                'name' => $row['firstname'] . ' ' . $row['lastname'],
                'avatar' => $row['avatar'] ?: '../assets/default_avatar.jpg'
            ]
        ]);
    } else {
        echo json_encode(['status' => 'idle']);
    }
}

// ============================================
// GỬI LỜI MỜI KẾT BẠN
// ============================================
function sendFriendRequest($conn, $user_id) {
    $partner_id = $_POST['partner_id'] ?? null;
    
    if (!$partner_id) {
        echo json_encode(['error' => 'Missing partner_id']);
        return;
    }
    
    // Kiểm tra đã là bạn chưa
    $stmt = $conn->prepare("SELECT id, status FROM friends WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)");
    $stmt->bind_param('iiii', $user_id, $partner_id, $partner_id, $user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        if ($existing['status'] == 'accepted') {
            echo json_encode(['status' => 'already_friends']);
        } else {
            echo json_encode(['status' => 'request_pending']);
        }
        return;
    }
    
    // Tạo lời mời kết bạn
    $stmt = $conn->prepare("INSERT INTO friends (user_id_1, user_id_2, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->bind_param('ii', $user_id, $partner_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'status' => 'request_sent']);
    } else {
        echo json_encode(['error' => 'Failed to send request']);
    }
}

// ============================================
// BỎ QUA PARTNER HIỆN TẠI (TÌM NGƯỜI MỚI)
// ============================================
function skipPartner($conn, $user_id) {
    $session_id = $_POST['session_id'] ?? null;
    
    if ($session_id) {
        // Kết thúc session hiện tại
        $stmt = $conn->prepare("UPDATE random_sessions SET status = 'ended', ended_at = NOW() WHERE id = ? AND (user_id_1 = ? OR user_id_2 = ?)");
        $stmt->bind_param('iii', $session_id, $user_id, $user_id);
        $stmt->execute();
        
        // Xóa tin nhắn
        $conn->query("DELETE FROM random_messages WHERE session_id = $session_id");
    }
    
    // Tham gia lại queue
    joinQueue($conn, $user_id);
}
?>
