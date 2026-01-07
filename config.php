<?php
/**
 * Cấu hình toàn hệ thống
 * File này giúp hệ thống hoạt động trên cả localhost và hosting
 */

// Phát hiện môi trường (localhost hay hosting)
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? 'localhost', [
    'localhost', 
    'localhost:3000', 
    'localhost:8080',
    'localhost:80',
    '127.0.0.1'
]);

if ($isLocalhost) {
    // ============================================
    // CẤU HÌNH CHO LOCALHOST (XAMPP)
    // ============================================
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'random_chat');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    
    define('BASE_URL', 'http://localhost/CS2');
    define('BASE_PATH', '/CS2/');
    
    // Hiển thị lỗi khi dev
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
} else {
    // ============================================
    // CẤU HÌNH CHO HOSTING (cPanel)
    // ============================================
    // LƯU Ý: Thay đổi thông tin phù hợp với hosting của bạn
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'your_hosting_dbname');      // VD: slrnkpif_random_chat
    define('DB_USER', 'your_hosting_username');    // VD: slrnkpif_admin
    define('DB_PASS', 'your_hosting_password');    // Password từ cPanel
    
    define('BASE_URL', 'https://yourdomain.com');  // VD: https://lequocdinh.id.vn
    define('BASE_PATH', '/');
    
    // Tắt hiển thị lỗi trên production
    ini_set('display_errors', 0);
    error_reporting(0);
    
    // Log lỗi vào file
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
}

// Đường dẫn vật lý
define('ROOT_PATH', __DIR__);

// ============================================
// HELPER FUNCTIONS
// ============================================

if (!function_exists('url')) {
    /**
     * Tạo URL tuyệt đối
     * @param string $path Đường dẫn tương đối
     * @return string URL đầy đủ
     */
    function url($path = '') {
        $path = ltrim($path, '/');
        return BASE_URL . '/' . $path;
    }
}

if (!function_exists('asset')) {
    /**
     * Tạo URL cho assets (CSS, JS, Images)
     * @param string $path Đường dẫn asset
     * @return string URL đầy đủ
     */
    function asset($path) {
        $path = ltrim($path, '/');
        return BASE_URL . '/' . $path;
    }
}

if (!function_exists('redirect')) {
    /**
     * Chuyển hướng an toàn
     * @param string $path Đường dẫn đích
     */
    function redirect($path = '/') {
        if (strpos($path, 'http') === 0) {
            header("Location: $path");
        } else {
            header("Location: " . url($path));
        }
        exit();
    }
}

if (!function_exists('path')) {
    /**
     * Tạo đường dẫn vật lý
     * @param string $path Đường dẫn tương đối
     * @return string Đường dẫn đầy đủ
     */
    function path($path = '') {
        $path = ltrim($path, '/');
        return ROOT_PATH . '/' . $path;
    }
}
?>
