# Phân hệ 2 – Bán hàng (POS & Worklist)

## Cài đặt
1. Chép các thư mục `app/`, `resources/views/`, `database/`, `routes/web.php` vào dự án (giữ cấu trúc).
   Gói này CẦN Phân hệ 1 (models `Product`, `ProductStock*`, view `staff/partials/flash`...) và đã kèm bản mới của
   `ProductLot` (thêm `scopeSellable`), `Product`, `dashboard.blade.php`, `system_layout.blade.php`, `routes/web.php` (gộp cả 3 gói).
2. `php artisan migrate`  (AUTO_INCREMENT cho 5 bảng bán hàng + cột `orders.status`).
3. Đã import file `the_daily_crust_completed.sql` (cột orders.order_channel... là varchar, không phải decimal) và chạy file SQL dọn giảm giá.
4. Chạy `database/sql/02_seed_demo_stock.sql` để có tồn kho thử bán.
5. Đăng nhập thu ngân (VD user 37002) -> /admin/pos ; đăng nhập khách -> trang chủ, bấm biểu tượng giỏ để đặt hàng; nhân viên xem /admin/orders.

## Cách hoạt động
- POS: Alpine.js giữ giỏ hàng; bấm "Thanh toán" gửi JSON tới `PosController@checkout` -> `OrderService::place()`.
- OrderService (1 transaction, khóa dòng): lấy giá từ DB, kiểm tồn các lô BÁN ĐƯỢC (còn hàng + chưa hết hạn), tạo `orders`,
  mỗi sản phẩm 1 dòng `order_details` (quantity = 1, giống dữ liệu lịch sử), mỗi dòng 1 `order_detail_lots` theo FEFO
  (lô hết hạn sớm xuất trước), trừ `product_lots.remaining_qty`, ghi `product_stock_movements/transactions`, `payments` (tượng trưng),
  `delivery_orders` (tượng trưng, chỉ đơn online giao tận nơi).
- Đơn POS: status = Completed ngay. Đơn online: status = New -> Preparing -> Ready -> Completed qua nút trên Worklist.
- Đơn online TRỪ KHO NGAY khi khách đặt (giữ hàng). Chưa có chức năng hủy đơn/hoàn kho.
