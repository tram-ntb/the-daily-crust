# Phân hệ 3 – BOM, Kế hoạch sản xuất, Mẻ nướng, Kiểm kê, Hàng cận date

Điều kiện: đã tích hợp Phân hệ 1 và 2 và `php artisan migrate` chạy thành công.
Không có ca làm việc / phân công nhân sự. Chỉ dùng Eloquent (không DB::select / DB::statement).

## 1. Chép file (ghi đè nếu trùng)
Chép nguyên cấu trúc thư mục trong zip vào `C:\laravel\the-daily-crust`:
- `app/Models/*` (ghi đè các model cùng tên của Phân hệ 1; thêm `ProductionForecast`)
- `app/Services/*` (ghi đè `InventoryService`, `ProductionService`; thêm `BomService`, `ProductionPlanningService`, `ExpiryService`)
- `app/Http/Controllers/Admin/{BomController,ProductionPlanController}.php`
- `app/Http/Controllers/Staff/{BatchController,ExpiryController,ProductionController,StocktakeController,WasteController}.php`
- `resources/views/admin/...`, `resources/views/staff/...`
- `resources/views/layouts/system_layout.blade.php` (đã thêm menu mới, bỏ mục "Báo cáo ca làm việc").
  Nếu bạn đã tự sửa layout, đừng ghi đè: chỉ thêm các dòng `$navLink(...)` cho `admin.plans.index`, `admin.batches.index`, `admin.expiry.index`.
- `routes/web.php` (đã gộp route Phân hệ 1, 2, 3 và dự báo). Nếu bạn đã sửa web.php, hãy gộp tay các route mới.
- `database/migrations/2026_10_22_000001_prepare_production_planning_tables.php`

KHÔNG ghi đè `dashboard.blade.php`, `customer/_cart.blade.php`, `staff/pos/index.blade.php` (giao diện bạn đã chỉnh).

## 2. Chạy
```
php artisan migrate
php artisan optimize:clear
```
Migration chỉ bật AUTO_INCREMENT cho `production_plans`, `bill_of_materials`, `production_forecasts`. Không tạo bảng, không thêm cột
(cột `acknowledged_*`, `failure_note` do migration kho của Phân hệ 1 đã thêm).

## 3. Chức năng và đường dẫn
| Chức năng | URL | Quyền |
|---|---|---|
| Danh sách BOM | /admin/bom | Staff, Manager xem; Manager sửa |
| Chi tiết BOM của sản phẩm (giống màn hình Odoo) | /admin/bom/{id} | như trên |
| Lập kế hoạch từ dự báo, duyệt | /admin/plans | Manager |
| Sản xuất hôm nay, xác nhận kế hoạch, ghi mẻ | /admin/production | Staff ghi, Manager xem |
| Theo dõi mẻ nướng, loại lỗi, đối chiếu Dự báo – Kế hoạch – Thực tế | /admin/batches | Staff, Manager |
| Kiểm kê, giải trình, biên bản hao hụt | /admin/stocktake | Staff nhập, Manager duyệt |
| Hàng cận date, lập phiếu hủy | /admin/expiry, /admin/waste | Staff lập, Manager duyệt |

## 4. Luồng liên kết với Phân hệ 4 (dự báo)
1. Python ghi `production_forecasts` lúc 21:00.
2. Manager mở /admin/plans: thấy dự báo, tồn thành phẩm còn hạn, số gợi ý = dự báo − tồn, và nguyên liệu có đủ theo BOM không.
   Sửa sản lượng khác dự báo thì bắt buộc nhập lý do, rồi Duyệt.
3. Staff xác nhận kế hoạch, ghi từng mẻ (đạt/lỗi + loại lỗi): hệ thống trừ nguyên liệu theo BOM, tạo lô thành phẩm, tạo phiếu hủy cho phần lỗi.
4. /admin/batches đối chiếu dự báo, kế hoạch và thực tế theo ngày.

## 5. Lưu ý trung thực
- Mã đã qua `php -l` và rà soát tên route, view, cột theo file SQL của bạn; CHƯA chạy được trong Laravel thật (môi trường của mình không cài được Composer). Hãy thử theo thứ tự ở mục 3 và gửi lại lỗi nếu có.
- Kế hoạch đã được Staff xác nhận hoặc đã có mẻ thì Manager không sửa được (có chủ đích).
- Thao tác ghi bị chặn ở route theo vai trò, không chỉ ẩn nút.
