# Sửa lệnh dự báo, phân loại 37 sản phẩm, hướng dẫn sinh dữ liệu test

## File trong gói (ghi đè file cùng tên)
- `app/Console/Commands/RunProductionForecast.php`  (tên lệnh: `app:run-production-forecast`, dùng Symfony Process, có import đầy đủ)
- `routes/console.php`                               (lập lịch 21:00 bằng class, không gõ chuỗi tên lệnh)
- `python/forecast_production.py`                    (phân loại sản phẩm, mỗi sản phẩm sản xuất có đúng 1 dòng dự báo)
- `python/run_forecast.bat`, `config/forecast.php`   (như cũ)

Sau khi chép: `php artisan optimize:clear` rồi `php artisan list | findstr forecast` phải thấy `app:run-production-forecast`.
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
Mỗi ngày phải có 24 dòng.

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
