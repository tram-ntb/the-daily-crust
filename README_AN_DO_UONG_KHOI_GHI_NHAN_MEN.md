# Đồ uống pha theo đơn: giữ trong dự báo và kế hoạch nguyên liệu, bỏ khỏi ghi nhận mẻ

## Các file thay đổi (chép đè, giữ nguyên đường dẫn)
| File | Thay đổi |
|---|---|
| config/forecast.php | Thêm `made_to_order_categories => ['Đồ uống']` |
| app/Models/Product.php | Thêm `isMadeToOrder()` và scope `batchable()` |
| app/Services/ProductionService.php | Thêm `assertBatchable()`, gọi trong `acknowledge()` và `recordBatch()` |
| app/Services/ProductionPlanningService.php | `rows()` trả thêm cờ `made_to_order` |
| app/Http/Controllers/Staff/ProductionController.php | `index()` lọc `batchable()`, `create()` chặn đồ uống |
| app/Http/Controllers/Staff/BatchController.php | Danh sách mẻ, thống kê lỗi, so sánh theo ngày, ô lọc sản phẩm chỉ gồm hàng làm theo mẻ |
| app/Http/Controllers/Admin/ForecastController.php | Trả cờ `made_to_order` cho từng dòng |
| resources/views/admin/forecast/index.blade.php | Nhãn "Pha theo đơn" |
| resources/views/admin/plans/index.blade.php | Nhãn "Pha theo đơn · chỉ để chuẩn bị nguyên liệu" và "Không ghi mẻ" |

Lưu ý: file `Product.php`, `config/forecast.php`, `ForecastController.php` và view dự báo là bản mới nhất của gói trước (p7). Hãy chép gói p7 trước nếu chưa chép.

## Các bước áp dụng
1. Sao lưu các file trên, rồi chép đè vào `C:\laravel\the-daily-crust`.
2. Chạy `php artisan optimize:clear`.
3. Không cần migration và không cần chạy lại dự báo.

## Kiểm tra
- Staff, Tiến độ sản xuất: không còn đồ uống trong danh sách kế hoạch và mẻ gần đây.
- Staff, Theo dõi mẻ: ô lọc sản phẩm không có đồ uống.
- Quản lý, Lập kế hoạch sản xuất: đồ uống vẫn có dòng, có nhãn "Pha theo đơn", và nguyên liệu vẫn được cộng vào bảng nhu cầu nguyên liệu.
- Quản lý, Dự báo: đồ uống vẫn có dự báo, kèm nhãn.
- Thử vào thẳng URL ghi mẻ của một kế hoạch đồ uống: bị chuyển về trang tiến độ kèm thông báo.

## Giới hạn cần biết
- `OrderService::place` không trừ nguyên liệu khi bán. Vì vậy nguyên liệu đồ uống chỉ dùng cho lập kế hoạch, tồn kho nguyên liệu của đồ uống sẽ không tự giảm theo đơn bán.
- Muốn thêm nhóm khác vào diện pha theo đơn, chỉ cần sửa `made_to_order_categories`.
- View `staff/production/index.blade.php` không đổi. Việc lọc nằm ở controller.
