<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

// Kiểm tra user đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit();
}

// Kiểm tra method
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Post ID không hợp lệ']);
    exit();
}

// Kiểm tra post có tồn tại không
$check_sql = "SELECT id FROM posts WHERE id = ?";
$check_stmt = $connection->prepare($check_sql);
$check_stmt->bind_param("i", $post_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Bài viết không tồn tại']);
    exit();
}
$check_stmt->close();

// Kiểm tra user đã like chưa
$like_check_sql = "SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?";
$like_stmt = $connection->prepare($like_check_sql);
$like_stmt->bind_param("ii", $post_id, $user_id);
$like_stmt->execute();
$already_liked = $like_stmt->get_result()->num_rows > 0;
$like_stmt->close();

if ($already_liked) {
    // Unlike
    $delete_sql = "DELETE FROM post_likes WHERE post_id = ? AND user_id = ?";
    $delete_stmt = $connection->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $post_id, $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Xóa notification
    $delete_notif_sql = "DELETE FROM notifications WHERE post_id = ? AND actor_id = ? AND type = 'like'";
    $delete_notif_stmt = $connection->prepare($delete_notif_sql);
    $delete_notif_stmt->bind_param("ii", $post_id, $user_id);
    $delete_notif_stmt->execute();
    $delete_notif_stmt->close();
    
    // Cập nhật likes_count
    $update_sql = "UPDATE posts SET likes_count = likes_count - 1 WHERE id = ?";
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("i", $post_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    echo json_encode(['success' => true, 'action' => 'unliked']);
} else {
    // Like
    $insert_sql = "INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)";
    $insert_stmt = $connection->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $post_id, $user_id);
    
    if ($insert_stmt->execute()) {
        // Cập nhật likes_count
        $update_sql = "UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?";
        $update_stmt = $connection->prepare($update_sql);
        $update_stmt->bind_param("i", $post_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Lấy user_id và thông tin user
        $post_owner_sql = "SELECT p.user_id, u.firstname, u.lastname, u.avatar FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?";
        $post_owner_stmt = $connection->prepare($post_owner_sql);
        $post_owner_stmt->bind_param("i", $post_id);
        $post_owner_stmt->execute();
        $post_info = $post_owner_stmt->get_result()->fetch_assoc();
        $post_owner_stmt->close();
        
        // Lấy thông tin user hiện tại
        $current_user_sql = "SELECT firstname, lastname, avatar FROM users WHERE id = ?";
        $current_user_stmt = $connection->prepare($current_user_sql);
        $current_user_stmt->bind_param("i", $user_id);
        $current_user_stmt->execute();
        $current_user = $current_user_stmt->get_result()->fetch_assoc();
        $current_user_stmt->close();
        
        // Chỉ tạo notification nếu người like không phải là chủ bài viết
        if ($post_info['user_id'] != $user_id) {
            $notif_sql = "INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, 'like', ?)";
            $notif_stmt = $connection->prepare($notif_sql);
            $notif_stmt->bind_param("iii", $post_info['user_id'], $user_id, $post_id);
            $notif_stmt->execute();
            $notif_stmt->close();
        }
        
        // Prepare response with toast data
        $response = [
            'success' => true,
            'action' => 'liked',
            'toast' => [
                'actor_name' => $current_user['firstname'] . ' ' . $current_user['lastname'],
                'actor_avatar' => !empty($current_user['avatar']) && file_exists($current_user['avatar']) ? $current_user['avatar'] : '../assets/default_avatar.jpg',
                'action' => 'liked your post'
            ]
        ];
        
        // Only send toast if not the post owner
        if ($post_info['user_id'] == $user_id) {
            $response['toast'] = null;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $insert_stmt->error]);
    }
    $insert_stmt->close();
}

$connection->close();
?>
