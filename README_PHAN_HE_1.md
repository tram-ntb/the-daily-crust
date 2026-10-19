# Phân hệ 1 – Kho & Sản xuất đầu ngày (Staff)

## Cài đặt
1. Chép các thư mục `app/`, `resources/views/staff/`, `database/migrations/`, `routes/web.php` vào dự án (giữ nguyên cấu trúc).
   `routes/web.php` đã gộp cả route dự báo (ForecastController) của gói trước.
2. `php artisan migrate` (bật AUTO_INCREMENT cho các bảng phát sinh + thêm cột `acknowledged_by/at`, `failure_note`).
   Nếu lỗi, chạy tay các lệnh ALTER ghi trong đầu file migration bằng phpMyAdmin.
3. `.env`: `APP_TIMEZONE=Asia/Ho_Chi_Minh`.
4. Đăng nhập thợ chính (user 37001) để xác nhận kế hoạch; mọi staff thuộc bộ phận bếp được ghi nhận mẻ.

## Lưu ý dữ liệu mẫu
- Kho Bột mì đa dụng (id 1) chỉ có 2.000 g trong khi mọi kế hoạch cần nhiều hơn => hệ thống sẽ CHẶN ghi mẻ cho tới khi nhập thêm tồn.
  Tăng tồn bằng phpMyAdmin để thử: `UPDATE ingredients SET stock_quantity = 50000 WHERE ingredient_id = 1;`
- Kế hoạch mẫu ở ngày 08/10/2026: chọn ngày đó trên trang Sản xuất. Kế hoạch #1 đã đủ chỉ tiêu (mẻ mẫu đạt 95/80); kế hoạch #2 dùng để thử ghi mẻ (đã duyệt, chưa xác nhận).
- Bảng thực tế vs tên gọi trong đề: Material = `ingredients`, BOM = `bill_of_materials`, ProductionLog = `production_batches`.
  Tồn kho thành phẩm nằm trong `product_lots.remaining_qty` (không có cột tồn trong `products`).
