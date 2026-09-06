# Website đặt sân cầu lông

Đồ án môn **Thiết kế và phát triển ứng dụng Web**. Website hỗ trợ người dùng tìm sân, chọn khung giờ và quản lý lịch đặt sân. Quản trị viên có thể quản lý sân, theo dõi lịch đặt và xem số liệu tổng quan.

## 1. Công nghệ sử dụng

- HTML, CSS và JavaScript.
- PHP và PDO để xử lý phía máy chủ và truy cập cơ sở dữ liệu.
- MySQL/MariaDB để lưu trữ dữ liệu.
- phpMyAdmin để quản lý và nhập cơ sở dữ liệu.
- XAMPP để chạy website trên máy tính.

## 2. Chức năng chính

### Người dùng

- Đăng ký, đăng nhập và đăng xuất.
- Khôi phục mật khẩu.
- Xem danh sách sân, tìm theo ngày và khu vực.
- Xem thông tin sân và các khung giờ.
- Đặt một hoặc nhiều khung giờ.
- Chọn phương thức thanh toán.
- Xem lịch sử, chi tiết đặt sân và hóa đơn/phiếu đặt sân.
- Hủy đặt sân khi đáp ứng điều kiện của hệ thống.
- Cập nhật thông tin cá nhân.

### Quản trị viên

- Xem số liệu tổng quan.
- Lọc danh sách và cập nhật trạng thái đặt sân.
- Thêm sân và sửa thông tin, hình ảnh sân.
- Thay đổi trạng thái hoạt động của sân.

**Lưu ý:** Thanh toán chuyển khoản và mã QR hiện được mô phỏng phục vụ đồ án, chưa tích hợp cổng thanh toán thực tế. Chọn thanh toán tiền mặt không đồng nghĩa với việc hệ thống đã nhận tiền.

## 3. Cấu trúc thư mục

```text
badminton_booking/
├── account/          # Thông tin tài khoản
├── admin/            # Chức năng quản trị
├── assets/
│   ├── css/          # Giao diện
│   ├── images/       # Hình ảnh sân
│   └── js/           # Xử lý tương tác
├── auth/             # Đăng nhập, đăng ký, khôi phục mật khẩu
├── booking/          # Đặt sân và quản lý lịch đặt
├── config/           # Cấu hình ứng dụng, cơ sở dữ liệu, session
├── courts/           # Danh sách và chi tiết sân
├── database/
│   ├── badminton_booking.sql
│   └── migrations/   # Các bản cập nhật cơ sở dữ liệu
├── includes/         # Hàm dùng chung, header và footer
├── payment/          # Thanh toán và hóa đơn/phiếu đặt sân
└── index.php         # Trang chủ
```

## 4. Hướng dẫn cài đặt

### Bước 1: Chuẩn bị mã nguồn

Cài đặt XAMPP có PHP và MySQL/MariaDB.

Tải mã nguồn từ GitHub, giải nén và đổi tên thư mục thành `badminton_booking`. Đặt thư mục tại:

```text
C:\xampp\htdocs\badminton_booking
```

Đảm bảo tệp trang chủ nằm trực tiếp tại:

```text
C:\xampp\htdocs\badminton_booking\index.php
```

### Bước 2: Khởi động dịch vụ

Mở XAMPP Control Panel và khởi động:

- Apache
- MySQL

### Bước 3: Nhập cơ sở dữ liệu

1. Truy cập [phpMyAdmin](http://localhost/phpmyadmin/).
2. Chọn **Import**.
3. Chọn tệp `database/badminton_booking.sql`.
4. Nhấn **Go** để thực hiện.

Tệp SQL tạo cơ sở dữ liệu `badminton_booking`, các bảng và dữ liệu mẫu về sân, khung giờ.

**Đối với cài đặt mới, chỉ nhập `badminton_booking.sql`. Tệp này đã bao gồm các thay đổi của 001 và 002, không cần nhập lại hai tệp migration.**

Nếu cơ sở dữ liệu đã có dữ liệu, hãy sao lưu trước khi thay đổi. Không nhập lại toàn bộ tệp cài đặt vào cơ sở dữ liệu đang sử dụng.

### Bước 4: Kiểm tra cấu hình

Mở tệp `config/app.php` và kiểm tra thông tin kết nối:

```php
const BASE_URL = '/badminton_booking/';
const DB_HOST = 'localhost';
const DB_NAME = 'badminton_booking';
const DB_USER = 'root';
const DB_PASS = '';
```

Đây là cấu hình cho môi trường XAMPP cục bộ thông thường. Nếu đã đặt mật khẩu cho tài khoản cơ sở dữ liệu, hãy cập nhật `DB_PASS` tương ứng. Nếu đổi tên thư mục dự án, cần cập nhật `BASE_URL`.

Không đưa mật khẩu thật của môi trường triển khai lên repository công khai.

### Bước 5: Mở website

Truy cập:

[http://localhost/badminton_booking/](http://localhost/badminton_booking/)

Đăng ký tài khoản mới để sử dụng chức năng đặt sân.

## 5. Tạo tài khoản quản trị viên

1. Đăng ký tài khoản trên website.
2. Mở phpMyAdmin và chọn cơ sở dữ liệu `badminton_booking`.
3. Mở thẻ **SQL** và chạy lệnh sau, thay địa chỉ email bằng email của tài khoản vừa đăng ký:

```sql
UPDATE users
SET role = 'admin'
WHERE email = 'email_cua_ban@example.com';
```

4. Đăng xuất và đăng nhập lại để sử dụng quyền quản trị.

Không cần đổi `user_id`; quyền quản trị được xác định bằng cột `role`.

## 6. Kiểm tra sau khi cài đặt

- Trang chủ hiển thị sân và hình ảnh.
- Có thể đăng ký, đăng nhập và đăng xuất.
- Có thể tìm sân, chọn giờ và xác nhận đặt sân.
- Đơn vừa tạo xuất hiện trong lịch sử đặt sân.
- Có thể thử thanh toán mô phỏng và xem hóa đơn/phiếu đặt sân.
- Tài khoản quản trị truy cập được khu vực quản lý.

## 7. Một số lỗi thường gặp

### Không tìm thấy bảng `courts`

Kiểm tra đã nhập `database/badminton_booking.sql` thành công và tên cơ sở dữ liệu trong cấu hình có chính xác hay không.

### Không tìm thấy cột `group_id`

Cơ sở dữ liệu có thể đang dùng phiên bản cũ. Sao lưu dữ liệu và kiểm tra các migration đã áp dụng trước khi cập nhật.

### Trang không có CSS hoặc không hiển thị hình ảnh

Kiểm tra thư mục `assets`, cấu trúc mã nguồn và giá trị `BASE_URL`. Không gom các tệp trong thư mục con vào thư mục gốc.

### Không mở được địa chỉ localhost

Kiểm tra Apache đã chạy trong XAMPP và địa chỉ truy cập có đúng cổng đang cấu hình hay không.

## 8. Phạm vi sử dụng

Dự án phục vụ học tập và chạy thử trên môi trường cục bộ. Cần kiểm tra thêm về bảo mật, cấu hình máy chủ và tích hợp thanh toán trước khi sử dụng thực tế.

GitHub lưu trữ mã nguồn của dự án. Để chạy các chức năng PHP và cơ sở dữ liệu, cần môi trường máy chủ phù hợp; GitHub Pages không chạy trực tiếp ứng dụng PHP này.
