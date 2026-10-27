# Sửa giao diện chọn ngày (lịch) và phân loại đồ uống pha chế

## File trong gói (ghi đè file cùng tên, giữ đường dẫn)
- `python/forecast_production.py`                              phân loại theo công thức BOM
- `app/Console/Commands/RunProductionForecast.php`             truyền thêm nhóm "do BOM quyết định" từ config
- `config/forecast.php`                                        commercial_categories chỉ còn hàng thương mại & quà tặng; thêm bom_decides_categories
- `app/Models/Product.php`                                     scope producible() theo BOM cho nhóm đồ uống
- `app/Http/Controllers/Admin/ForecastController.php`          trả đủ danh sách ngày đã có dự báo cho lịch
- `resources/views/admin/forecast/index.blade.php`             lịch chọn ngày (flatpickr), nút ‹ › và "Ngày mai"

Gói này dựa trên các bản trước (nhóm hàng, bước 3-5): các file trên là bản mới nhất, chép đè lên. Không có migration.

## Quy tắc phân loại mới
| Điều kiện | Nhóm | Dự báo |
|---|---|---|
| Nhóm "Hàng thương mại & Quà tặng" | Hàng nhập từ NCC | nhu cầu 7 ngày, gợi ý nhập hàng |
| Nhóm "Đồ uống" CÓ công thức BOM (cà phê, trà, sô-cô-la nóng, nước ép, sinh tố) | Pha chế tại quán (Production) | sản lượng mỗi ngày |
| Nhóm "Đồ uống" KHÔNG có BOM (nước suối đóng chai) | Hàng nhập từ NCC | nhu cầu 7 ngày |
| Còn lại (bánh mì, bánh ngọt, món mặn, bánh quy trẻ em...) | Sản xuất tại bếp (Production) | sản lượng mỗi ngày |

Kết quả trên dữ liệu hiện tại: 30 sản phẩm Production + 7 Commercial (nước suối + 6 hàng thương mại / quà tặng) = 37.
Muốn đổi, sửa `config/forecast.php` (nguồn duy nhất), không phải sửa code. Thêm BOM cho một sản phẩm là nó tự chuyển sang nhóm pha chế.

## Cách áp dụng
    (chép đè file)
    php artisan optimize:clear
    php artisan app:run-production-forecast --dry-run          # xem thử: phải thấy "SẢN XUẤT 30 sản phẩm", "NHẬP 7 sản phẩm"
    # sinh lại các ngày đã có dự báo để cập nhật nhóm (mỗi lần chạy thay thế dòng cũ của đúng ngày đó):
    foreach ($d in '2026-10-26','2026-10-27','2026-10-28') { php artisan app:run-production-forecast --as-of=$d }
Kiểm tra: SELECT item_group, COUNT(*) FROM production_forecasts WHERE forecast_date = '2026-10-27' GROUP BY item_group;   -> Production 30, Commercial 7.
Các dòng mẫu cũ có sẵn trong database (ví dụ model v1.2.0 của ngày 08/10/2026) không bị thay đổi. Xem trước khi xóa:
    SELECT forecast_date, model_version, COUNT(*) FROM production_forecasts GROUP BY 1, 2 ORDER BY 1;

Lưu ý: 5 loại đồ uống pha chế giờ xuất hiện trong kế hoạch sản xuất (Lập kế hoạch sản xuất) vì có BOM; đơn vị là ly. Kho nguyên liệu (hạt cà phê, sữa, trái cây...) cần đủ tồn để ghi nhận.

## Lịch chọn ngày
Bấm vào ô ngày để mở lịch; ngày đã có dự báo có chấm xanh; chọn ngày là tải lại trang. Thư viện flatpickr nạp từ CDN (cần Internet khi demo).
