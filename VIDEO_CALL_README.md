# Video Call Feature - User Guide

## Tổng quan

Tính năng Video Call cho phép người dùng thực hiện cuộc gọi video peer-to-peer với bạn bè trên nền tảng Random-Chat. Tính năng này sử dụng WebRTC để kết nối trực tiếp giữa hai người dùng.

## Cách sử dụng

### Thực hiện cuộc gọi video

1. Truy cập trang **Messages** (`view/messages.php`)
2. Chọn một cuộc trò chuyện với người bạn muốn gọi
3. Nhấn nút **Video Call** (biểu tượng 📹) ở góc trên bên phải của khung chat
4. Cho phép trình duyệt truy cập camera và microphone khi được yêu cầu
5. Chờ người nhận chấp nhận cuộc gọi

### Nhận cuộc gọi video

1. Khi có cuộc gọi đến, bạn sẽ thấy thông báo popup với:
   - Ảnh đại diện của người gọi
   - Tên người gọi
   - Hai nút: **Accept** (Chấp nhận) và **Decline** (Từ chối)
2. Nhấn **Accept** để chấp nhận cuộc gọi
3. Nhấn **Decline** để từ chối cuộc gọi

### Điều khiển trong cuộc gọi

| Nút | Chức năng |
|-----|-----------|
| 🎤 | Bật/Tắt microphone |
| 📹 | Bật/Tắt camera |
| 🖥️ | Chia sẻ màn hình |
| 📵 | Kết thúc cuộc gọi |

### Kết thúc cuộc gọi

- Nhấn nút **End Call** (📵) để kết thúc cuộc gọi
- Cuộc gọi cũng tự động kết thúc khi đối phương kết thúc

## Yêu cầu kỹ thuật

### Trình duyệt hỗ trợ
- Google Chrome (khuyến nghị)
- Mozilla Firefox
- Microsoft Edge
- Safari

### Quyền truy cập
- Camera (bắt buộc)
- Microphone (bắt buộc)
- Thông báo (khuyến nghị)

### Mạng
- Kết nối internet ổn định
- Cổng UDP được mở (cho ICE/STUN)

## Cấu trúc Database

### Bảng `call_history`
Lưu trữ lịch sử cuộc gọi:
- `id`: ID cuộc gọi
- `caller_id`: ID người gọi
- `receiver_id`: ID người nhận
- `call_type`: Loại cuộc gọi (video/audio)
- `status`: Trạng thái (pending/completed/missed/declined)
- `started_at`: Thời gian bắt đầu
- `ended_at`: Thời gian kết thúc
- `duration`: Thời lượng (giây)

### Bảng `call_signals`
Lưu trữ tín hiệu WebRTC:
- `id`: ID tín hiệu
- `from_user_id`: ID người gửi
- `to_user_id`: ID người nhận
- `signal_type`: Loại tín hiệu (offer/answer/ice/end/decline)
- `signal_data`: Dữ liệu tín hiệu (JSON)

## Cấu trúc Files

```
CS2/
├── assets/
│   └── video-call.js          # JavaScript xử lý video call
├── view/
│   ├── messages.php           # Trang tin nhắn (có tích hợp video call)
│   └── api/
│       └── video_call.php     # API xử lý video call
├── css/
│   └── messages.css           # CSS cho video call modal
└── database/
    └── video_call_tables.sql  # SQL tạo bảng
```

## Xử lý lỗi thường gặp

### "Cannot access camera/microphone"
- Kiểm tra quyền truy cập camera/microphone trong trình duyệt
- Đảm bảo camera không đang được sử dụng bởi ứng dụng khác
- Thử làm mới trang

### "No answer"
- Người nhận có thể không online
- Người nhận có thể đã từ chối cuộc gọi
- Kiểm tra kết nối mạng

### "Connection failed"
- Kiểm tra kết nối internet
- Thử gọi lại sau vài giây
- Có thể do firewall chặn kết nối WebRTC

## API Endpoints

### POST `/view/api/video_call.php?action=initiate_call`
Khởi tạo cuộc gọi
- Body: `receiver_id`
- Response: `{ success: true, call_id: number }`

### POST `/view/api/video_call.php?action=answer_call`
Trả lời cuộc gọi
- Body: `call_id`
- Response: `{ success: true }`

### POST `/view/api/video_call.php?action=decline_call`
Từ chối cuộc gọi
- Body: `call_id`
- Response: `{ success: true }`

### POST `/view/api/video_call.php?action=end_call`
Kết thúc cuộc gọi
- Body: `call_id`
- Response: `{ success: true }`

### POST `/view/api/video_call.php?action=send_signal`
Gửi tín hiệu WebRTC
- Body: `to_user_id`, `signal_type`, `signal_data`
- Response: `{ success: true }`

### GET `/view/api/video_call.php?action=get_signals`
Lấy tín hiệu WebRTC
- Response: `{ signals: [...] }`

### GET `/view/api/video_call.php?action=check_incoming_calls`
Kiểm tra cuộc gọi đến
- Response: `{ has_incoming_call: boolean, ... }`
