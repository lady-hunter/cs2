<?php
session_start();
require_once '../config/db.php';

$error = "";
$success = "";

// Xử lý đăng ký
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $day = $_POST['day'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $gender = $_POST['gender'];
    
    // Kiểm tra dữ liệu
    if (!empty($username) && !empty($firstname) && !empty($lastname) && !empty($email) && !empty($password)) {
        // Kiểm tra email đã tồn tại
        $check_email = "SELECT id FROM users WHERE email = ? OR username = ?";
        $stmt = $connection->prepare($check_email);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email hoặc username đã được sử dụng!";
        } else {
            // Mã hóa mật khẩu
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            // Đặt avatar mặc định
            $default_avatar = '../assets/default_avatar.jpg';
            // Chèn user mới
            $insert_sql = "INSERT INTO users (username, firstname, lastname, email, password, avatar) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $connection->prepare($insert_sql);
            $insert_stmt->bind_param("ssssss", $username, $firstname, $lastname, $email, $hashed_password, $default_avatar);
            
            if ($insert_stmt->execute()) {
                $success = "Đăng ký thành công! Vui lòng đăng nhập.";
                // Redirect sau 2 giây
                header("refresh:2;url=login.php");
            } else {
                $error = "Lỗi: " . $connection->error;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    } else {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Random-Chat</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/register.css">
</head>
<body class="register-page">
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <h1>Random-Chat</h1>
                <p>It's quick and easy.</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="register-form">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <div class="form-row">
                        <input type="text" name="firstname" placeholder="First name" required>
                        <input type="text" name="lastname" placeholder="Last name" required>
                    </div>
                    
                    <input type="email" name="email" placeholder="Email address" required>
                    
                    <input type="password" name="password" placeholder="New password" required>
                    
                    <div class="form-group">
                        <label>Birthday</label>
                        <div class="birthday-inputs">
                            <select name="day" required>
                                <option value="">Day</option>
                                <?php for($i = 1; $i <= 31; $i++) echo "<option value='$i'>$i</option>"; ?>
                            </select>
                            <select name="month" required>
                                <option value="">Month</option>
                                <?php 
                                $months = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
                                foreach($months as $key => $month) echo "<option value='" . ($key + 1) . "'>$month</option>";
                                ?>
                            </select>
                            <select name="year" required>
                                <option value="">Year</option>
                                <?php for($i = date('Y'); $i >= 1905; $i--) echo "<option value='$i'>$i</option>"; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Gender</label>
                        <div class="gender-inputs">
                            <label class="radio-label">
                                <input type="radio" name="gender" value="female" required>
                                <span>Female</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="gender" value="male" required>
                                <span>Male</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="gender" value="custom" required>
                                <span>Custom</span>
                            </label>
                        </div>
                    </div>
                    
                    <p class="terms">By clicking Sign Up, you agree to our Terms, Data Policy and Cookies Policy.</p>
                    
                    <button type="submit" name="register">Sign Up</button>
                    
                    <a href="login.php" class="login-link">Already have an account?</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>