# Sửa lỗi bấm "Chạy mô hình dự báo" trên web bị lỗi sklearn

## Các file (chép đè, giữ nguyên đường dẫn)
| File | Thay đổi |
|---|---|
| `app/Support/PythonEnvironment.php` (MỚI) | Tìm python.exe tuyệt đối, dựng lại biến môi trường Windows còn thiếu, gỡ PYTHONHOME/PYTHONPATH lạ |
| `app/Console/Commands/RunProductionForecast.php` | Dùng PythonEnvironment khi tạo Process, in ra Python đang dùng |
| `app/Http/Controllers/Admin/ForecastController.php` | Lỗi hiển thị phần CUỐI traceback (nguyên nhân thật), ghi log đầy đủ, thêm `diagnose()` |
| `python/diagnose_env.py` (MỚI) | Kiểm tra từng thư viện import được không, in lỗi đầy đủ |

Lưu ý: `ForecastController.php` ở đây là bản của gói p8 cộng thêm phần sửa. Hãy chép gói p8 trước nếu chưa chép.

## Các bước
1. Chép đè các file trên vào `C:\laravel\the-daily-crust`.
2. Trong `.env` đặt đường dẫn Python tuyệt đối, dùng dấu `/` và đặt trong nháy kép:
   ```
   FORECAST_PYTHON="C:/Users/ThanhHV-E480/AppData/Local/Programs/Python/Python313/python.exe"
   ```
3. Thêm route chẩn đoán vào nhóm admin dành cho Manager trong `routes/web.php`, cạnh dòng `forecast.run`:
   ```php
   Route::get('forecast/diagnose', [ForecastController::class, 'diagnose'])->name('forecast.diagnose');
   ```
4. Chạy `php artisan optimize:clear`, rồi **tắt và bật lại** server web (`php artisan serve` hoặc Apache) vì biến môi trường chỉ nạp lúc khởi động.
5. Đăng nhập Quản lý, mở `/admin/forecast/diagnose`. Trang này chạy kiểm tra Python ngay trong môi trường của web server.
   - Cuối trang ghi "mọi thư viện import được": bấm lại "Chạy mô hình dự báo", sẽ chạy được.
   - Có dòng `LỖI <tên thư viện>`: dòng cuối của traceback ngay dưới là nguyên nhân thật. Gửi mình đoạn đó.
6. Nếu bấm nút vẫn lỗi, thông báo đỏ giờ hiện phần cuối của lỗi, và bản đầy đủ nằm ở `storage/logs/forecast_last_run.log`.

## Cách khác để thử nhanh (nếu bạn chạy web bằng `php artisan serve`)
Tắt server rồi bật bằng `php artisan serve --no-reload`. Chế độ mặc định của `serve` có thể làm mất một số biến môi trường Windows của process con.
