<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Lấy thông tin user hiện tại
$sql = "SELECT username, firstname, lastname, email, avatar FROM users WHERE id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Đường dẫn ảnh mặc định
$default_avatar = "../assets/default_avatar.jpg";

// Xử lý hiển thị avatar
$avatar_display = $default_avatar;
if (!empty($user['avatar'])) {
    if (file_exists($user['avatar'])) {
        $avatar_display = $user['avatar'];
    } else {
        $avatar_display = $default_avatar;
    }
}
$user['avatar'] = $avatar_display;

// Xử lý cập nhật thông tin
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $new_password = $_POST['new_password'] ?? '';
    $avatar_path = $user['avatar'];

    // Xử lý upload avatar
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../assets/avatars/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $file_name = $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;
        
        // Kiểm tra loại file
        $allowed = array("jpg", "jpeg", "gif", "png");
        if (in_array(strtolower($file_extension), $allowed)) {
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                $avatar_path = $target_file;
                $success = "Avatar được cập nhật!";
            } else {
                $error = "Upload ảnh thất bại.";
            }
        } else {
            $error = "Chỉ chấp nhận file ảnh (jpg, jpeg, gif, png).";
        }
    }

    // Cập nhật thông tin
    if (empty($error)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $sql_update = "UPDATE users SET username=?, firstname=?, lastname=?, password=?, avatar=? WHERE id=?";
            $stmt = $connection->prepare($sql_update);
            $stmt->bind_param("sssssi", $username, $firstname, $lastname, $hashed_password, $avatar_path, $user_id);
        } else {
            $sql_update = "UPDATE users SET username=?, firstname=?, lastname=?, avatar=? WHERE id=?";
            $stmt = $connection->prepare($sql_update);
            $stmt->bind_param("ssssi", $username, $firstname, $lastname, $avatar_path, $user_id);
        }
        
        if ($stmt->execute()) {
            if (empty($success)) {
                $success = "Thông tin được cập nhật thành công!";
            }
            // Reload lại thông tin user
            $sql = "SELECT username, firstname, lastname, email, avatar FROM users WHERE id = ?";
            $stmt2 = $connection->prepare($sql);
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            
            // Cập nhật avatar_display
            if (!empty($user['avatar']) && file_exists($user['avatar'])) {
                $user['avatar'] = $user['avatar'];
            } else {
                $user['avatar'] = $default_avatar;
            }
        } else {
            $error = "Cập nhật thất bại: " . $connection->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Random-Chat</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/settings.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/home.css?v=<?php echo time(); ?>">
</head>
<body class="home-page">
    <!-- HEADER -->
    <div class="header">
        <div class="header-container">
            <div class="logo">Gacha-Chat</div>
            <div class="header-actions">
                <a href="home.php" class="header-link">Profile</a>
                <a href="home.php" class="header-link">Messages</a>
                <a href="login.php" class="header-link logout">Logout</a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-container">
        <!-- LEFT SIDEBAR -->
        <aside class="sidebar">
            <div class="user-card">
                <img src="<?php echo $user['avatar']; ?>" alt="Profile" class="user-avatar">
                <h3><?php echo htmlspecialchars($user['firstname'] . " " . $user['lastname']); ?></h3>
                <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                <a href="settings.php" class="edit-profile-btn">Edit Profile</a>
            </div>
            <div class="sidebar-menu">
                <a href="home.php" class="menu-item">Feed</a>
                <a href="home.php" class="menu-item">Messenger</a>
                <a href="#" class="menu-item">Friends</a>
                <a href="#" class="menu-item">Photos</a>
                <a href="settings.php" class="menu-item active">Settings</a>
            </div>
        </aside>

        <!-- CENTER CONTENT -->
        <main class="feed">
            <div class="settings-container">
                <h2>Account Settings</h2>
                <p class="settings-subtitle">Update your profile information</p>

                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" class="settings-form">
                    <!-- Avatar Section -->
                    <div class="settings-section">
                        <h3>Avatar</h3>
                        <div class="avatar-preview">
                            <img src="<?php echo $user['avatar']; ?>" alt="Avatar" id="avatarPreview">
                        </div>
                        <div class="avatar-upload">
                            <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="previewAvatar(event)">
                            <label for="avatarInput" class="upload-label">Choose Image</label>
                            <p class="upload-hint">Supported: JPG, PNG, GIF (Max 5MB)</p>
                        </div>
                    </div>

                    <!-- Personal Info Section -->
                    <div class="settings-section">
                        <h3>Personal Information</h3>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstname">First Name</label>
                                <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="lastname">Last Name</label>
                                <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email (Cannot change)</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="settings-section">
                        <h3>Security</h3>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="settings-actions">
                        <button type="submit" class="save-btn">Save Changes</button>
                        <a href="home.php" class="cancel-btn">Cancel</a>
                    </div>
                </form>

                <!-- Additional Action -->
                <div style="margin-top: 24px; text-align: center;">
                    <a href="home.php" style="display: inline-block; padding: 12px 24px; background: #e4e6eb; color: #000; text-decoration: none; border-radius: 6px; font-weight: 600;">← Back to Home</a>
                </div>
            </div>
        </main>

        <!-- RIGHT SIDEBAR -->
        <aside class="right-sidebar">
            <div class="suggestions-box">
                <h3>Quick Links</h3>
                <a href="home.php" style="display: block; padding: 8px 0; color: #1877f2; text-decoration: none;">← Back to Home</a>
                <a href="login.php" style="display: block; padding: 8px 0; color: #dc3545; text-decoration: none;">Logout</a>
            </div>
        </aside>
    </div>

    <script>
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>