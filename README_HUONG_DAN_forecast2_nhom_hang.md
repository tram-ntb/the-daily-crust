# Dự báo 2 nhóm hàng: sản xuất tại bếp và nhập từ nhà cung cấp

## Cách phân loại (cả 37 sản phẩm đều được dự báo, mỗi sản phẩm 1 dòng/ngày)
| Nhóm | Gồm | Dự báo gì | `item_group` | `predicted_qty` |
|---|---|---|---|---|
| Sản xuất tại bếp | Bánh mì, bánh nướng, bánh ngọt, cookies, món mặn, **bánh quy trẻ em (#36)** | Sản lượng ngày mai, để lập kế hoạch mẻ nướng | `Production` | số cái cho 1 ngày (`horizon_days=1`) |
| Nhập từ NCC | "Đồ uống", "Hàng thương mại & Quà tặng" | Nhu cầu 7 ngày tới, để Manager quyết định nhập thêm | `Commercial` | tổng 7 ngày (`horizon_days=7`) |

- Nhóm nhập về chỉ khai báo ở MỘT chỗ: `config/forecast.php` -> `commercial_categories`. Lệnh Artisan tự truyền cho Python; màn hình và các trang kế hoạch đọc cùng danh sách đó.
- Mọi nhóm còn lại mặc định là hàng sản xuất. Hạn dùng KHÔNG còn được dùng để loại sản phẩm, nên bánh quy trẻ em (hạn 90 ngày) vẫn nằm trong dự báo sản xuất.
- Mô hình: hàng sản xuất dùng HistGradientBoosting (Poisson) so với 2 đường cơ sở theo thứ trong tuần và trung bình 28 ngày, tự chọn cái sai số nhỏ nhất. Hàng nhập về dùng nhu cầu cộng dồn 7 ngày, chọn giữa trung bình 28 ngày, 56 ngày và EWMA bằng kiểm định lùi theo thời gian (số liệu 7 ngày ổn định hơn nhiều so với từng ngày).

## File trong gói (ghi đè / thêm mới)
- `database/migrations/2026_10_24_000002_add_group_columns_to_production_forecasts_table.php`  (THÊM cột `item_group`, `horizon_days`)
- `python/forecast_production.py`  (phân loại 2 nhóm, 2 mô hình)
- `app/Console/Commands/RunProductionForecast.php`  (tên lệnh `app:run-production-forecast`, truyền nhóm từ config)
- `routes/console.php`, `config/forecast.php`, `python/run_forecast.bat`
- `app/Models/ProductionForecast.php` (scope `production()`, `commercial()`), `app/Models/Product.php` (scope `producible()`)
- `app/Services/ProductionPlanningService.php`, `app/Http/Controllers/Staff/BatchController.php`, `ExpiryController.php`  (chỉ lấy dòng Production, hàng nhập về không lọt vào kế hoạch sản xuất)
- `app/Http/Controllers/Admin/ForecastController.php`, `resources/views/admin/forecast/index.blade.php`  (thêm bảng "Hàng nhập từ nhà cung cấp, gợi ý nhập hàng")

## Thứ tự chạy
    php artisan migrate
    php artisan optimize:clear
    php artisan list | findstr forecast       # phải thấy app:run-production-forecast
Nếu trước đó bạn có file Command trùng chức năng với tên khác (ví dụ `RunForecast.php`), hãy xóa để khỏi trùng.

## Cài môi trường Python (một lần)
    pip install pandas numpy scikit-learn pymysql
Trong `.env` (nếu cần): `FORECAST_PYTHON=python` (hoặc `py`).

## Bước 1: sinh dự báo đầu tiên
    php artisan app:run-production-forecast --dry-run     # xem thử, chưa ghi DB
    php artisan app:run-production-forecast               # ghi thật dự báo cho ngày mai
Sinh dự báo cho nhiều ngày (để có dữ liệu cho Bước 2; --as-of là "ngày chạy", dự báo sẽ cho ngày hôm sau):
    foreach ($d in '2026-10-23','2026-10-24','2026-10-25','2026-10-26','2026-10-27') { php artisan app:run-production-forecast --as-of=$d }
Kiểm tra trong phpMyAdmin:
    SELECT forecast_date, COUNT(*) sp, SUM(predicted_qty) tong FROM production_forecasts GROUP BY forecast_date ORDER BY 1;
Mỗi ngày phải có 37 dòng (25 Production + 12 Commercial; số 25/12 phụ thuộc BOM và danh mục hiện có). Kiểm tra theo nhóm:
    SELECT forecast_date, item_group, COUNT(*) sp, SUM(predicted_qty) tong FROM production_forecasts GROUP BY forecast_date, item_group ORDER BY 1, 2;
Bánh quy trẻ em (#36) phải có dòng `Production`. Dự báo của nó có thể bằng 0 vì lịch sử bán rất ít (chỉ 12 cái trong 4 ngày), đó là kết quả đúng của dữ liệu chứ không phải lỗi lọc.

## Bước 1b: Manager xem gợi ý nhập hàng
Manager -> trang Dự báo (/admin/forecast): bảng "Hàng nhập từ nhà cung cấp" cho thấy nhu cầu 7 ngày, tồn hiện tại (lô còn hạn), số ngày đủ bán và số nên nhập thêm = nhu cầu 7 ngày x 1,2 - tồn. Hệ số dự phòng chỉnh ở `restock_safety_ratio` trong config/forecast.php. Muốn thấy số khác 0, cần có lô hàng nhập trong `product_lots` hoặc chấp nhận tồn đang thấp.

## Bước 2: Manager lập và duyệt kế hoạch
Đăng nhập Manager (username 35001) -> "Lập kế hoạch sản xuất" (/admin/plans) -> chọn ngày có dự báo -> bấm "Điền gợi ý" (hoặc nhập tay;
nếu khác dự báo phải ghi lý do) -> "Duyệt kế hoạch". Chỉ lập được cho hôm nay trở đi.

## Bước 3: Staff sản xuất
1. Staff 37001 (Thợ làm bánh chính) -> /admin/production?date=YYYY-MM-DD -> "Xác nhận kế hoạch" từng dòng.
2. "Ghi nhận mẻ": nhập số đạt, số lỗi + loại lỗi -> hệ thống trừ nguyên liệu theo BOM, tạo lô thành phẩm (POS bán được ngay), tạo phiếu hủy chờ duyệt cho phần lỗi.
   Staff 37003 (Thợ trang trí) và 37005 (NV phụ làm bánh) cũng ghi được mẻ, nhưng không xác nhận được kế hoạch.
3. Manager -> /admin/waste -> duyệt phiếu hủy. Staff -> /admin/stocktake -> mở phiên, nhập số đếm, gửi; Manager duyệt/giải trình.
4. Xem kết quả: /admin/batches (đối chiếu Dự báo - Kế hoạch - Thực tế), /admin/expiry, /admin/ingredients.

Nếu ghi mẻ báo "Không đủ nguyên liệu" (Bột mì đa dụng chỉ còn 2.000 g, Kem tươi 800 ml, Bơ lạt 2.500 g), đó là tình huống test hợp lệ. Muốn đi tiếp, nhập thêm kho demo:
    UPDATE ingredients SET stock_quantity = stock_quantity + 50000 WHERE ingredient_id = 1;
    UPDATE ingredients SET stock_quantity = stock_quantity + 10000 WHERE ingredient_id = 15;
    UPDATE ingredients SET stock_quantity = stock_quantity + 5000  WHERE ingredient_id = 22;
    UPDATE ingredients SET stock_quantity = stock_quantity + 500   WHERE ingredient_id = 23;
