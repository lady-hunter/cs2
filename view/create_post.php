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
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$image = null;

// Validation
if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Nội dung bài viết không được để trống']);
    exit();
}

if (strlen($content) > 5000) {
    echo json_encode(['success' => false, 'message' => 'Nội dung bài viết quá dài (tối đa 5000 ký tự)']);
    exit();
}

// Xử lý upload ảnh
if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
    $file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    
    // Kiểm tra loại file
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Loại file không được hỗ trợ. Vui lòng chọn JPG, PNG hoặc GIF']);
        exit();
    }
    
    // Kiểm tra kích thước (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Kích thước ảnh không được vượt quá 5MB']);
        exit();
    }
    
    // Tạo thư mục nếu chưa có
    $upload_dir = '../assets/posts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Tạo tên file unique
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'post_' . $user_id . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $image = $file_path;
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi tải lên ảnh']);
        exit();
    }
}

// Insert vào database
$sql = "INSERT INTO posts (user_id, content, image, likes_count, comments_count) VALUES (?, ?, ?, 0, 0)";
$stmt = $connection->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $connection->error]);
    exit();
}

$stmt->bind_param("iss", $user_id, $content, $image);

if ($stmt->execute()) {
    $post_id = $stmt->insert_id;
    
    // Lấy thông tin user để trả về
    $user_sql = "SELECT firstname, lastname, username, avatar FROM users WHERE id = ?";
    $user_stmt = $connection->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    // Chuẩn bị dữ liệu trả về
    $user_avatar = "../assets/default_avatar.jpg";
    if (!empty($user_data['avatar']) && file_exists($user_data['avatar'])) {
        $user_avatar = $user_data['avatar'];
    }
    
    $post_data = [
        'id' => $post_id,
        'author' => $user_data['firstname'] . ' ' . $user_data['lastname'],
        'username' => $user_data['username'],
        'avatar' => $user_avatar,
        'time' => 'just now',
        'content' => $content,
        'image' => $image ? $image : '',
        'likes' => 0,
        'comments' => 0,
        'liked' => false
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Bài viết đã được đăng thành công',
        'post' => $post_data
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$connection->close();
?>
