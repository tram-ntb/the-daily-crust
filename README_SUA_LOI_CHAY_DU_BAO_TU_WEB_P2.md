# Sửa lỗi bấm "Chạy mô hình dự báo" trên web (WinError 10106 / sklearn)

## Nguyên nhân (đã xác định từ trang chẩn đoán)
Process Python do web server tạo ra **không nhận được** SystemRoot, USERPROFILE, APPDATA, TEMP dù PHP đọc được chúng
(dưới `php artisan serve` / cli-server, Symfony Process không chuyển tiếp các biến này). Thiếu SystemRoot thì Python trên Windows
không khởi tạo được Winsock: `import asyncio` lỗi `WinError 10106`, kéo theo `joblib` và `sklearn` lỗi. Terminal VS Code có đủ biến nên chạy tay được.

## Cách sửa
1. `PythonEnvironment::environment()` truyền **tường minh toàn bộ** môi trường của PHP sang Python, và dựng lại các biến còn thiếu.
2. `forecast_production.py` tự đặt lại SYSTEMROOT/WINDIR ở đầu file trước khi import thư viện (lớp phòng vệ thứ hai).

## Các file (chép đè, giữ nguyên đường dẫn)
| File | Thay đổi |
|---|---|
| `app/Support/PythonEnvironment.php` | Truyền toàn bộ môi trường, không ghi đè biến sẵn có, không trùng khóa PATH/Path |
| `python/forecast_production.py` | Thêm 3 dòng đặt SYSTEMROOT ở đầu file (phần còn lại giữ nguyên bản mới nhất) |
| `app/Console/Commands/RunProductionForecast.php`, `app/Http/Controllers/Admin/ForecastController.php`, `python/diagnose_env.py` | Như bản trước (nếu đã chép thì không cần chép lại) |

## Các bước
1. Chép đè các file trên (đặc biệt `PythonEnvironment.php` và `forecast_production.py`).
2. `.env`: cứ giữ `FORECAST_PYTHON=python` (trang chẩn đoán cho thấy đã tìm đúng python.exe). Nếu muốn ghi đường dẫn đầy đủ thì viết đúng tên người dùng trên máy bạn:
   ```
   FORECAST_PYTHON="C:/Users/ThanHV-E480/AppData/Local/Programs/Python/Python313/python.exe"
   ```
3. `php artisan optimize:clear`, tắt rồi bật lại server web.
4. Mở lại `/admin/forecast/diagnose`: dòng `env:` phải là `SystemRoot=có, USERPROFILE=có, ...` và cuối trang ghi "mọi thư viện import được".
5. Bấm "Chạy mô hình dự báo".
