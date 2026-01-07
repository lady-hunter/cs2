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
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Post ID không hợp lệ']);
    exit();
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Bình luận không được để trống']);
    exit();
}

if (strlen($content) > 500) {
    echo json_encode(['success' => false, 'message' => 'Bình luận quá dài (tối đa 500 ký tự)']);
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

// Insert comment
$insert_sql = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
$insert_stmt = $connection->prepare($insert_sql);

if (!$insert_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $connection->error]);
    exit();
}

$insert_stmt->bind_param("iis", $post_id, $user_id, $content);

if ($insert_stmt->execute()) {
    // Cập nhật comments_count
    $update_sql = "UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?";
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("i", $post_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Lấy thông tin user hiện tại
    $user_sql = "SELECT firstname, lastname, avatar FROM users WHERE id = ?";
    $user_stmt = $connection->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    $user_avatar = "../assets/default_avatar.jpg";
    if (!empty($user_data['avatar']) && file_exists($user_data['avatar'])) {
        $user_avatar = $user_data['avatar'];
    }
    
    // Lấy user_id của post owner
    $post_owner_sql = "SELECT user_id FROM posts WHERE id = ?";
    $post_owner_stmt = $connection->prepare($post_owner_sql);
    $post_owner_stmt->bind_param("i", $post_id);
    $post_owner_stmt->execute();
    $post_owner = $post_owner_stmt->get_result()->fetch_assoc();
    $post_owner_stmt->close();
    
    // Tạo notification cho post owner
    if ($post_owner['user_id'] != $user_id) {
        $comment_id = $insert_stmt->insert_id;
        $notif_sql = "INSERT INTO notifications (user_id, actor_id, type, post_id, comment_id, content) VALUES (?, ?, 'comment', ?, ?, ?)";
        $notif_stmt = $connection->prepare($notif_sql);
        
        // Giới hạn content preview 100 ký tự
        $content_preview = substr($content, 0, 100);
        $notif_stmt->bind_param("iiiis", $post_owner['user_id'], $user_id, $post_id, $comment_id, $content_preview);
        $notif_stmt->execute();
        $notif_stmt->close();
    }
    
    $comment_data = [
        'id' => $insert_stmt->insert_id,
        'user_name' => $user_data['firstname'] . ' ' . $user_data['lastname'],
        'avatar' => $user_avatar,
        'content' => $content,
        'time' => 'just now'
    ];
    
    // Prepare response with toast data
    $response = [
        'success' => true,
        'message' => 'Bình luận đã được đăng',
        'comment' => $comment_data,
        'toast' => null
    ];
    
    // Only send toast to post owner
    if ($post_owner['user_id'] != $user_id) {
        $response['toast'] = [
            'actor_name' => $user_data['firstname'] . ' ' . $user_data['lastname'],
            'actor_avatar' => $user_avatar,
            'action' => 'commented on your post'
        ];
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $insert_stmt->error]);
}

$insert_stmt->close();
$connection->close();
?>
