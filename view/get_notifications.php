<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit();
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// Lấy danh sách thông báo
$sql = "SELECT n.id, n.type, n.post_id, n.comment_id, n.content, n.is_read, n.created_at,
               u.firstname, u.lastname, u.avatar
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT ?";

if (!($stmt = $connection->prepare($sql))) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $connection->error]);
    exit();
}

$stmt->bind_param("ii", $user_id, $limit);
$stmt->execute();
$result = $stmt->get_result();

$notifications = array();
while ($row = $result->fetch_assoc()) {
    $actor_avatar = "../assets/default_avatar.jpg";
    if (!empty($row['avatar']) && file_exists($row['avatar'])) {
        $actor_avatar = $row['avatar'];
    }
    
    // Tính thời gian
    $notif_time = strtotime($row['created_at']);
    $current_time = time();
    $diff = $current_time - $notif_time;
    
    if ($diff < 60) {
        $time_ago = "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        $time_ago = $mins . "m";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        $time_ago = $hours . "h";
    } else {
        $days = floor($diff / 86400);
        $time_ago = $days . "d";
    }
    
    $notifications[] = array(
        'id' => $row['id'],
        'type' => $row['type'],
        'post_id' => $row['post_id'],
        'actor_name' => $row['firstname'] . ' ' . $row['lastname'],
        'actor_avatar' => $actor_avatar,
        'message' => $row['type'] === 'like' ? 'liked your post' : 'commented on your post',
        'content' => $row['content'],
        'time' => $time_ago,
        'is_read' => (bool)$row['is_read']
    );
}
$stmt->close();

// Lấy số thông báo chưa đọc
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_stmt = $connection->prepare($unread_sql);
if (!$unread_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $connection->error]);
    exit();
}

$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result()->fetch_assoc();
$unread_count = $unread_result['count'];
$unread_stmt->close();

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);

$connection->close();
?>
