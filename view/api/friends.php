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
    case 'send_request':
        sendFriendRequest();
        break;
    case 'accept_request':
        acceptFriendRequest();
        break;
    case 'reject_request':
        rejectFriendRequest();
        break;
    case 'remove_friend':
        removeFriend();
        break;
    case 'block_user':
        blockUser();
        break;
    case 'unblock_user':
        unblockUser();
        break;
    case 'check_status':
        checkFriendStatus();
        break;
    case 'get_suggestions':
        getFriendSuggestions();
        break;
    case 'search_friends':
        searchFriends();
        break;
    case 'get_friends':
        getFriends();
        break;
    case 'get_friend_requests':
        getFriendRequests();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function sendFriendRequest() {
    global $connection, $user_id;
    
    $target_user_id = intval($_POST['user_id'] ?? 0);
    
    if (!$target_user_id || $target_user_id == $user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    // Kiểm tra user có bị block không
    $block_sql = "SELECT id FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)";
    $block_stmt = $connection->prepare($block_sql);
    $block_stmt->bind_param("iiii", $target_user_id, $user_id, $user_id, $target_user_id);
    $block_stmt->execute();
    if ($block_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot add this user']);
        $block_stmt->close();
        return;
    }
    $block_stmt->close();
    
    // Kiểm tra request đã tồn tại không
    $check_sql = "SELECT id, status FROM friends WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)";
    $check_stmt = $connection->prepare($check_sql);
    $check_stmt->bind_param("iiii", $user_id, $target_user_id, $target_user_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $row = $check_result->fetch_assoc();
        echo json_encode(['success' => false, 'message' => 'Already ' . $row['status'], 'status' => $row['status']]);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();
    
    // Lưu friend request
    $user_id_1 = min($user_id, $target_user_id);
    $user_id_2 = max($user_id, $target_user_id);
    
    $sql = "INSERT INTO friends (user_id_1, user_id_2, status) VALUES (?, ?, 'pending')";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id_1, $user_id_2);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend request sent', 'status' => 'pending']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send request']);
    }
    $stmt->close();
}

function acceptFriendRequest() {
    global $connection, $user_id;
    
    $requester_id = intval($_POST['user_id'] ?? 0);
    
    if (!$requester_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    $user_id_1 = min($user_id, $requester_id);
    $user_id_2 = max($user_id, $requester_id);
    
    $sql = "UPDATE friends SET status = 'accepted' WHERE user_id_1 = ? AND user_id_2 = ? AND status = 'pending'";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id_1, $user_id_2);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend request accepted', 'status' => 'accepted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to accept request']);
    }
    $stmt->close();
}

function rejectFriendRequest() {
    global $connection, $user_id;
    
    $requester_id = intval($_POST['user_id'] ?? 0);
    
    if (!$requester_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    $user_id_1 = min($user_id, $requester_id);
    $user_id_2 = max($user_id, $requester_id);
    
    $sql = "DELETE FROM friends WHERE user_id_1 = ? AND user_id_2 = ? AND status = 'pending'";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id_1, $user_id_2);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend request rejected', 'status' => 'none']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject request']);
    }
    $stmt->close();
}

function removeFriend() {
    global $connection, $user_id;
    
    $friend_id = intval($_POST['user_id'] ?? 0);
    
    if (!$friend_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    $user_id_1 = min($user_id, $friend_id);
    $user_id_2 = max($user_id, $friend_id);
    
    $sql = "DELETE FROM friends WHERE user_id_1 = ? AND user_id_2 = ? AND status = 'accepted'";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id_1, $user_id_2);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Friend removed', 'status' => 'none']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove friend']);
    }
    $stmt->close();
}

function blockUser() {
    global $connection, $user_id;
    
    $block_user_id = intval($_POST['user_id'] ?? 0);
    
    if (!$block_user_id || $block_user_id == $user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    // Kiểm tra đã block chưa
    $check_sql = "SELECT id FROM blocks WHERE blocker_id = ? AND blocked_id = ?";
    $check_stmt = $connection->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $block_user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already blocked']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();
    
    // Xóa friendship nếu có
    $user_id_1 = min($user_id, $block_user_id);
    $user_id_2 = max($user_id, $block_user_id);
    
    $delete_friend_sql = "DELETE FROM friends WHERE user_id_1 = ? AND user_id_2 = ?";
    $delete_stmt = $connection->prepare($delete_friend_sql);
    $delete_stmt->bind_param("ii", $user_id_1, $user_id_2);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Block user
    $sql = "INSERT INTO blocks (blocker_id, blocked_id) VALUES (?, ?)";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id, $block_user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User blocked', 'status' => 'blocked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to block user']);
    }
    $stmt->close();
}

function unblockUser() {
    global $connection, $user_id;
    
    $block_user_id = intval($_POST['user_id'] ?? 0);
    
    if (!$block_user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    $sql = "DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id, $block_user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User unblocked', 'status' => 'none']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unblock user']);
    }
    $stmt->close();
}

function checkFriendStatus() {
    global $connection, $user_id;
    
    $other_user_id = intval($_GET['user_id'] ?? 0);
    
    if (!$other_user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        return;
    }
    
    // Kiểm tra block
    $block_sql = "SELECT id FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)";
    $block_stmt = $connection->prepare($block_sql);
    $block_stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $block_stmt->execute();
    
    if ($block_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => true, 'status' => 'blocked']);
        $block_stmt->close();
        return;
    }
    $block_stmt->close();
    
    // Kiểm tra friend status
    $user_id_1 = min($user_id, $other_user_id);
    $user_id_2 = max($user_id, $other_user_id);
    
    $sql = "SELECT status FROM friends WHERE user_id_1 = ? AND user_id_2 = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $user_id_1, $user_id_2);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'status' => $row['status']]);
    } else {
        echo json_encode(['success' => true, 'status' => 'none']);
    }
    $stmt->close();
}

function getFriendSuggestions() {
    global $connection, $user_id;
    
    $limit = intval($_GET['limit'] ?? 5);
    
    // Lấy danh sách user không phải bạn, không pending, không bị block
    $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.avatar
            FROM users u
            WHERE u.id != ?
            AND u.id NOT IN (
                SELECT user_id_1 FROM friends WHERE user_id_2 = ?
                UNION
                SELECT user_id_2 FROM friends WHERE user_id_1 = ?
            )
            AND u.id NOT IN (
                SELECT blocked_id FROM blocks WHERE blocker_id = ?
                UNION
                SELECT blocker_id FROM blocks WHERE blocked_id = ?
            )
            ORDER BY RAND()
            LIMIT ?";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['avatar']) || !file_exists($row['avatar'])) {
            $row['avatar'] = "../assets/default_avatar.jpg";
        }
        $suggestions[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ]);
}

function searchFriends() {
    global $connection, $user_id;
    
    $query = '%' . $_GET['q'] . '%';
    
    $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.avatar,
                   CASE
                       WHEN EXISTS(SELECT 1 FROM friends WHERE (user_id_1 = ? AND user_id_2 = u.id) OR (user_id_1 = u.id AND user_id_2 = ?)) THEN 'friend'
                       WHEN EXISTS(SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = u.id) THEN 'blocked'
                       ELSE 'none'
                   END as status
            FROM users u
            WHERE (u.firstname LIKE ? OR u.lastname LIKE ? OR u.username LIKE ?)
            AND u.id != ?
            LIMIT 20";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("iiisssi", $user_id, $user_id, $user_id, $query, $query, $query, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        // Xử lý đường dẫn avatar
        $avatar = $row['avatar'];
        
        // Nếu avatar là relative path từ view folder, chuyển sang relative path từ gốc
        if (!empty($avatar)) {
            if (strpos($avatar, '../') === 0) {
                // Nếu là ../assets/... thì chuyển thành assets/...
                $avatar = ltrim($avatar, './');
                $avatar = str_replace('../', '', $avatar);
            }
            // Check nếu file tồn tại
            if (!file_exists('../../' . $avatar)) {
                $avatar = 'assets/default_avatar.jpg';
            }
        } else {
            $avatar = 'assets/default_avatar.jpg';
        }
        
        $row['avatar'] = $avatar;
        $users[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}

function getFriends() {
    global $connection, $user_id;
    
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
    
    $friends = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['avatar']) || !file_exists($row['avatar'])) {
            $row['avatar'] = "../assets/default_avatar.jpg";
        }
        $friends[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'friends' => $friends
    ]);
}

function getFriendRequests() {
    global $connection, $user_id;
    
    $sql = "SELECT CASE
                WHEN user_id_1 = ? THEN user_id_2
                ELSE user_id_1
            END as requester_id,
            u.firstname, u.lastname, u.username, u.avatar, f.created_at
            FROM friends f
            JOIN users u ON (
                CASE
                    WHEN user_id_1 = ? THEN u.id = f.user_id_2
                    ELSE u.id = f.user_id_1
                END
            )
            WHERE (
                (user_id_1 = ? AND status = 'pending') OR 
                (user_id_2 = ? AND status = 'pending')
            )
            ORDER BY f.created_at DESC";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['avatar']) || !file_exists($row['avatar'])) {
            $row['avatar'] = "../assets/default_avatar.jpg";
        }
        $requests[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);
}
?>
