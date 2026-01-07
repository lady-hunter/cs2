# 📱 Hệ Thống Nhắn Tin - Messaging System (Facebook Messenger Style)

## ✨ Tính Năng

- **💬 Nhắn tin một-một** giữa các người dùng
- **📱 Giao diện hiện đại** giống Facebook Messenger
- **🖼️ Hỗ trợ gửi ảnh** trong tin nhắn
- **✅ Đánh dấu đã đọc** tự động
- **⏱️ Thời gian tin nhắn** được hiển thị chính xác
- **🔍 Tìm kiếm cuộc trò chuyện** nhanh chóng
- **👥 Khởi tạo cuộc trò chuyện mới** với người dùng bất kỳ
- **🔄 Cập nhật real-time** mỗi 2 giây
- **📦 Lưu trữ conversation** để truy cập nhanh

## 🗂️ Cấu Trúc Thư Mục

```
CS2/
├── view/
│   ├── messages.php           # Trang giao diện nhắn tin chính
│   ├── home.php               # Cập nhật link Messages
│   └── api/
│       └── messages.php       # API endpoints cho tin nhắn
├── css/
│   └── messages.css           # Styling cho giao diện messages
├── assets/
│   └── messages/              # Thư mục lưu ảnh từ tin nhắn
├── config/
│   └── db.php                 # Cập nhật schema database
└── setup.php                  # Setup database tables
```

## 🗄️ Database Schema

### Bảng: messages
```sql
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message LONGTEXT NOT NULL,
    image VARCHAR(255),
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX(sender_id, receiver_id),
    INDEX(receiver_id)
);
```

### Bảng: conversations
```sql
CREATE TABLE conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    last_message_id INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL,
    UNIQUE KEY unique_conversation (user_id_1, user_id_2)
);
```

## 🚀 Cách Sử Dụng

### 1️⃣ Setup Database
Mở trình duyệt và truy cập:
```
http://localhost/CS2/setup.php
```

Điều này sẽ tự động tạo các bảng `messages` và `conversations` cần thiết.

### 2️⃣ Truy cập trang Messages
- Nhấn vào link **"Messages"** ở header hoặc sidebar
- Hoặc truy cập trực tiếp: `http://localhost/CS2/view/messages.php`

### 3️⃣ Tính Năng Chính

#### 📤 Gửi Tin Nhắn
- Nhập tin nhắn vào ô input ở dưới cùng
- Nhấn icon ❤️ để gửi hoặc nhấn Enter
- Hỗ trợ gửi ảnh bằng nút **+**

#### 🔍 Tìm Cuộc Trò Chuyện
- Sử dụng ô tìm kiếm ở sidebar để tìm conversation
- Hỗ trợ tìm theo tên hoặc username

#### 💬 Tạo Cuộc Trò Chuyện Mới
- Nhấn nút **✏️** (biểu tượng viết) ở header sidebar
- Tìm kiếm người dùng muốn nhắn tin
- Nhấn vào để mở cuộc trò chuyện

#### ✅ Đánh Dấu Đã Đọc
- Tin nhắn tự động được đánh dấu là đã đọc khi mở conversation
- Hiển thị số lượng tin nhắn chưa đọc trên avatar người gửi

## 🎨 Giao Diện

### Bố Cục Chính
```
┌─────────────────────────────────────────┐
│  Messages          🔍  ✏️              │
├─────────────────┬───────────────────────┤
│ Conversations   │  Chat Area            │
│  • User 1       │  ────────────────     │
│  • User 2       │  💬 Messages          │
│  • User 3       │  ────────────────     │
│                 │  📝 Input: [   ] ❤️   │
└─────────────────┴───────────────────────┘
```

### Màu Sắc
- **Tin nhắn gửi**: Xanh lá (#31a24c)
- **Tin nhắn nhận**: Xám nhẹ (#e4e6eb)
- **Badge chưa đọc**: Xanh lá (#31a24c)
- **Background**: Trắng (#fff)

## 📡 API Endpoints

Tất cả requests gửi tới: `view/api/messages.php`

### 1. Gửi Tin Nhắn
```
Method: POST
Parameters:
  - action: send_message
  - receiver_id: ID người nhận
  - message: Nội dung tin nhắn
  - image: File ảnh (optional)

Response:
{
  "success": true/false,
  "message": "Message sent",
  "message_id": 123
}
```

### 2. Lấy Tin Nhắn
```
Method: GET
Parameters:
  - action: get_messages
  - user_id: ID người dùng khác

Response:
{
  "success": true/false,
  "messages": [
    {
      "id": 1,
      "sender_id": 2,
      "message": "Hello",
      "image": null,
      "time": "2m",
      "is_read": 1
    }
  ]
}
```

### 3. Tìm Kiếm Người Dùng
```
Method: GET
Parameters:
  - action: search_users
  - q: Keyword tìm kiếm

Response:
{
  "success": true/false,
  "users": [
    {
      "id": 2,
      "firstname": "John",
      "lastname": "Doe",
      "username": "johndoe",
      "avatar": "path/to/avatar.jpg"
    }
  ]
}
```

### 4. Lấy Danh Sách Conversations
```
Method: GET
Parameters:
  - action: get_conversations

Response:
{
  "success": true/false,
  "conversations": [...]
}
```

### 5. Xóa Tin Nhắn
```
Method: POST
Parameters:
  - action: delete_message
  - message_id: ID tin nhắn

Response:
{
  "success": true/false,
  "message": "Message deleted"
}
```

## 🔒 Bảo Mật

- ✅ Xác thực session trước khi xử lý
- ✅ Kiểm tra quyền sở hữu tin nhắn trước khi xóa
- ✅ Sanitize input để tránh XSS
- ✅ Prepared statements để tránh SQL injection
- ✅ Giới hạn truy cập API endpoints

## ⚡ Performance

- 📊 Index trên `sender_id`, `receiver_id` và `(sender_id, receiver_id)`
- 🔄 Real-time updates mỗi 2 giây
- 💾 Lưu trữ `last_message_id` để tránh JOIN phức tạp
- 📦 Pagination có thể thêm sau

## 🔧 Tùy Chỉnh

### Thay Đổi Tần Suất Cập Nhật
Mở `view/messages.php` và tìm dòng:
```javascript
setInterval(loadMessages, 2000); // Thay 2000 (ms) thành giá trị khác
```

### Thay Đổi Màu Sắc
Mở `css/messages.css` và tìm:
```css
.message-group.sent .message-text {
    background: #31a24c; /* Thay đổi màu xanh */
    color: #fff;
}
```

### Thêm Tính Năng Typing Indicator
Có thể thêm trạng thái "đang gõ" bằng cách:
1. Tạo bảng `typing_status`
2. Update status khi user gõ
3. Hiển thị "User is typing..." ở chat header

## 📝 Hỗ Trợ Các Định Dạng Ảnh

- ✅ .jpg, .jpeg
- ✅ .png
- ✅ .gif
- ✅ .webp

Giới hạn kích thước mặc định: 5MB (có thể tùy chỉnh trong PHP)

## 🐛 Troubleshooting

### Tin nhắn không gửi được
- Kiểm tra kết nối database
- Kiểm tra folder `assets/messages/` có quyền ghi không
- Kiểm tra console browser (F12) xem có lỗi gì

### Ảnh không upload được
- Kiểm tra folder `assets/messages/` có tồn tại không
- Kiểm tra quyền folder: `chmod 755 assets/messages/`
- Kiểm tra kích thước ảnh có vượt quá 5MB không

### Real-time không hoạt động
- Kiểm tra JavaScript console có lỗi không
- Kiểm tra API endpoint có trả về JSON không
- Tăng tần suất polling nếu cần (setInterval 1000 ms)

## 📚 Files Tạo/Sửa Đổi

1. **Tạo**: `view/messages.php` - Trang giao diện chính
2. **Tạo**: `view/api/messages.php` - API endpoints
3. **Tạo**: `css/messages.css` - Styling
4. **Tạo**: `setup.php` - Database setup
5. **Sửa**: `config/db.php` - Thêm schema documentation
6. **Sửa**: `view/home.php` - Update links

## 🎯 Lộ Trình Phát Triển Tiếp Theo

- [ ] Typing indicator (đang gõ)
- [ ] Voice call / Video call
- [ ] Group messaging
- [ ] Message reactions (emoji reactions)
- [ ] Message search
- [ ] Message pinning
- [ ] File sharing (không chỉ ảnh)
- [ ] End-to-end encryption
- [ ] Mobile app

## 📄 License

MIT License - Feel free to use for personal or commercial projects

---

**Tạo bởi**: Your Name  
**Ngày**: December 2025  
**Version**: 1.0.0
