<?php
session_start();
require_once '../config/db.php';

$error = "";
$success = "";
$step = 1; // Step 1: Nhập email/username, Step 2: Nhập mật khẩu mới

// Bước 1: Kiểm tra email và username
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    
    if (!empty($email) && !empty($username)) {
        // Kiểm tra xem email và username có tồn tại không
        $sql = "SELECT id, email, username FROM users WHERE email = ? AND username = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $user['email'];
            $_SESSION['reset_username'] = $user['username'];
            $step = 2;
            $success = "Xác minh thành công! Vui lòng nhập mật khẩu mới.";
        } else {
            $error = "Email hoặc username không chính xác!";
        }
        $stmt->close();
    } else {
        $error = "Vui lòng nhập email và username!";
    }
}

// Bước 2: Cập nhật mật khẩu mới
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset']) && isset($_SESSION['reset_user_id'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $user_id = $_SESSION['reset_user_id'];
                
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success = "Mật khẩu được cập nhật thành công! Vui lòng đăng nhập.";
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_username']);
                    // Redirect to login after 2 seconds
                    header("refresh:2;url=login.php");
                } else {
                    $error = "Lỗi: " . $connection->error;
                }
                $stmt->close();
            } else {
                $error = "Mật khẩu phải có ít nhất 6 ký tự!";
            }
        } else {
            $error = "Mật khẩu xác nhận không khớp!";
        }
    } else {
        $error = "Vui lòng nhập đầy đủ mật khẩu!";
    }
}

// Nếu có session reset_user_id thì hiện step 2
if (isset($_SESSION['reset_user_id'])) {
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Random-Chat</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/login.css?v=<?php echo time(); ?>">
</head>
<body class="login-page">
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h1>Forgot Password</h1>
                <p>Reset your password</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="login-form">
                <!-- Step 1: Verify Email and Username -->
                <?php if ($step == 1): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #000;">Enter your email address</label>
                            <input type="email" name="email" placeholder="your@email.com" required>
                        </div>
                        
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #000;">Enter your username</label>
                            <input type="text" name="username" placeholder="your username" required>
                        </div>
                        
                        <button type="submit" name="verify" style="width: 100%; padding: 12px; background: #1877f2; color: white; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer;">Search</button>
                    </form>
                <?php endif; ?>
                
                <!-- Step 2: Reset Password -->
                <?php if ($step == 2): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <div style="margin-bottom: 16px; padding: 12px; background: #e7f3ff; border-radius: 6px; text-align: center;">
                            <p style="margin: 0; color: #0a66c2; font-size: 14px;">
                                Email: <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong><br>
                                Username: <strong><?php echo htmlspecialchars($_SESSION['reset_username']); ?></strong>
                            </p>
                        </div>
                        
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #000;">New Password</label>
                            <input type="password" name="new_password" placeholder="Enter new password" required>
                        </div>
                        
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #000;">Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                        </div>
                        
                        <button type="submit" name="reset" style="width: 100%; padding: 12px; background: #42b72a; color: white; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; margin-bottom: 8px;">Reset Password</button>
                        <a href="login.php" style="display: block; width: 100%; padding: 12px; background: #e4e6eb; color: #000; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none;">Back to Login</a>
                    </form>
                <?php endif; ?>
                
                <div class="divider"></div>
                
                <!-- Links -->
                <div style="text-align: center;">
                    <p style="margin: 8px 0; font-size: 14px;">
                        <a href="login.php" style="color: #1877f2; text-decoration: none;">← Back to Login</a>
                    </p>
                    <p style="margin: 8px 0; font-size: 14px;">
                        Don't have an account? <a href="register.php" style="color: #1877f2; text-decoration: none; font-weight: 600;">Sign up</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
