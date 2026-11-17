# HUIT Schedule Hub

Ứng dụng PHP quản lý thời khóa biểu giảng dạy đa cơ sở của HUIT. Quản trị viên có thể lọc và CRUD lịch, kiểm tra trùng tiết/phòng, gợi ý giảng viên theo chuyên môn và lịch trống. Giảng viên đăng nhập để xem lịch cá nhân theo tuần và các buổi học trong tháng.

## Yêu cầu

- PHP 8.1 trở lên với extension `pdo_mysql`
- MySQL 8.0 trở lên
- Composer 2
- Laragon (khuyến nghị) hoặc máy chủ web PHP tương đương

## Cài đặt với Laragon

1. Đặt project trong `C:\laragon\www\huit-schedule` (hoặc thư mục web Laragon đang dùng), khởi động Apache và MySQL.
2. Tạo `.env` từ file mẫu:

   ```powershell
   Copy-Item .env.example .env
   ```

3. Mở HeidiSQL hoặc phpMyAdmin và import `schema.sql`. File này tạo database `huit_db` cùng các bảng `branches`, `teachers`, `users`, `schedules`.
4. Trong thư mục dự án, cài dependencies:

   ```bash
   composer install
   ```

   `vlucas/phpdotenv` nạp cấu hình môi trường; `fakerphp/faker` tạo giảng viên mẫu.
5. Sửa thông tin `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` trong `.env` cho đúng máy của bạn. Mặc định Laragon thường dùng `root` và mật khẩu rỗng.
6. Sinh dữ liệu mẫu:

   ```bash
   php seed.php
   ```

7. Mở `http://huit-schedule.test`. Nếu dùng tên miền này, tạo thư mục `huit-schedule` dưới `C:\laragon\www`, sau đó chọn **Reload** trong Laragon để tạo virtual host tự động. Nếu giữ tên thư mục khác, dùng URL virtual host Laragon tương ứng.

## Tài khoản mẫu

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị viên | `admin@huit.edu.vn` | `password` |
| Giảng viên | `giangvien@huit.edu.vn` | `password` |

Các tài khoản giảng viên sinh tự động có email `giangvien01@huit.edu.vn` đến `giangvien12@huit.edu.vn`, cùng mật khẩu `password`. Đổi các mật khẩu mẫu trước khi đưa ứng dụng lên môi trường thật.

## Quy tắc phân lịch

- Tiết học hỗ trợ từ 1 đến 12, nhập một khoảng như `1-3` hoặc danh sách như `1-2,4`.
- Hệ thống chặn trùng tiết đối với cùng giảng viên hoặc cùng phòng tại một cơ sở.
- Khi đổi cơ sở, giảng viên cần có ít nhất một tiết trống để di chuyển.
- Gợi ý chỉ liệt kê giảng viên đang trống; ưu tiên chuyên môn phù hợp và lịch gần kề tại cùng cơ sở.
- Lịch được xem như lịch lặp hàng tuần; trang lịch cá nhân khai triển các buổi học theo tháng đã chọn.

## Kiểm tra cú pháp PHP

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

GitHub Actions chạy kiểm tra cú pháp này mỗi lần push hoặc tạo pull request.

## Cấu trúc chính

```text
assets/       CSS và JavaScript giao diện
bootstrap.php Kết nối PDO, dotenv và session
functions.php Hàm dùng chung, kiểm tra tiết và truy vấn lịch
index.php     Đăng nhập, dashboard, CRUD và lịch giảng dạy
schema.sql    Cấu trúc MySQL
seed.php      Dữ liệu mẫu Faker
```

## Đóng góp

Mở issue để báo lỗi hoặc đề xuất tính năng, sau đó gửi pull request kèm mô tả thay đổi và kết quả kiểm thử. Dự án được phát hành theo giấy phép MIT.
