<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = $_POST['post_id'] ?? null;
$content = $_POST['content'] ?? null;

if (!$post_id || !$content) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Check if user owns the post
$sql = "SELECT user_id FROM posts WHERE id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result || $result['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Update post
$sql = "UPDATE posts SET content = ? WHERE id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("si", $content, $post_id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Post updated successfully']);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to update post']);
}
?>