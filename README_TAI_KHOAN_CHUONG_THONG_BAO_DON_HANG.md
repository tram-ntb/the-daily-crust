# Tài khoản, chuông thông báo, thông báo đơn hàng và giờ giao dự kiến

## 1. Cách áp dụng (làm đúng thứ tự)
1. Đã chép gói p8 (đồ uống pha theo đơn)? Gói này dùng bản `ProductionController.php` của p8, nên chép p8 trước.
2. Sao lưu rồi chép đè gói này vào `C:\laravel\the-daily-crust`, giữ nguyên đường dẫn. Có 3 file mới đáng chú ý ở `app/Notifications/`, `app/Services/NotifyService.php` và `config/crust.php`.
3. Chạy migration (bắt buộc, trước khi mở lại web):
   ```
   php artisan migrate
   php artisan optimize:clear
   ```
   Ba migration: tạo bảng `notifications`, thêm `orders.estimated_delivery_at`, thêm `users.is_active`. Cả ba chạy lại an toàn.
4. Trong `.env` đặt múi giờ để giờ dự kiến hiển thị đúng giờ Việt Nam:
   ```
   APP_TIMEZONE=Asia/Ho_Chi_Minh
   ```
   Chạy `php artisan config:clear` sau khi sửa.
5. Email (tùy chọn). Mặc định TẮT: mọi thông báo chỉ lưu trong CSDL và xem trên web. Muốn demo email xác nhận đơn mà không gửi ra ngoài, thêm vào `.env`:
   ```
   CRUST_NOTIFY_MAIL=true
   MAIL_MAILER=log
   ```
   Email sẽ được ghi vào `storage/logs/laravel.log`.

## 2. Danh sách file
| File | Nội dung |
|---|---|
| `routes/web.php` | Bản đầy đủ. Nếu bạn đã tự sửa routes, chỉ cần thêm các route mới (mục 5) |
| `resources/views/layouts/system_layout.blade.php` | Thay chuông và menu cũ bằng 2 dòng `@include`, thêm `x-cloak`, mục sidebar "Tài khoản khách hàng" cho Manager, ẩn thanh tìm kiếm mua sắm ở các trang tài khoản |
| `resources/views/partials/notification-bell.blade.php` | Chuông thông báo thật (số chưa đọc, dropdown, đánh dấu đã đọc, tự cập nhật 30 giây) |
| `resources/views/partials/user-menu.blade.php` | Menu góc phải theo vai trò |
| `resources/views/account/show.blade.php` | Trang tài khoản (sửa thông tin, đổi mật khẩu) |
| `resources/views/notifications/index.blade.php` | Lịch sử thông báo |
| `resources/views/admin/users/*.blade.php` | Quản lý nhân viên (danh sách, form) và tài khoản khách hàng (danh sách, chi tiết) |
| `resources/views/customer/orders/index.blade.php` | Đơn hàng của tôi: tiến trình, trạng thái đúng ngữ cảnh, giờ dự kiến |
| `app/Http/Controllers/AccountController.php`, `NotificationController.php` | Tài khoản của mình, thông báo của mình |
| `app/Http/Controllers/Admin/StaffAccountController.php`, `CustomerAccountController.php` | Chỉ Manager (chặn ở route) |
| `app/Http/Controllers/Customer/OnlineOrderController.php` | Gửi xác nhận sau khi đặt hàng |
| `app/Http/Controllers/Staff/WorklistController.php` | Gửi thông báo khi đổi trạng thái, cập nhật giờ tới nơi khi bắt đầu giao |
| `app/Http/Controllers/Staff/ProductionController.php`, `Admin/ProductionPlanController.php` | Báo Quản lý khi bếp ghi mẻ, báo bếp khi kế hoạch được duyệt |
| `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Middleware/CheckRole.php` | Chặn tài khoản bị khóa |
| `app/Models/User.php`, `Order.php` | Hàm vai trò, `phoneText()`, `etaLabel()`, `customerStatusLabel()` |
| `app/Services/OrderService.php`, `DeliveryEstimator.php`, `NotifyService.php` | Tính giờ dự kiến, gửi thông báo an toàn |
| `app/Notifications/*` | `OrderPlacedNotification`, `OrderStatusNotification`, `SystemNotification` |
| `config/crust.php` | Cấu hình thời gian giao, chức vụ bếp, email, chuông |

## 3. Quy tắc phân quyền menu góc phải
- **Customer:** Tài khoản, Đơn hàng của tôi, Đăng xuất.
- **Manager / Staff:** Tài khoản, Đăng xuất. Mục "Đơn hàng của tôi" không còn nên không còn lỗi 403.
- Trang tài khoản dùng chung cho cả 3 vai trò, ai cũng chỉ sửa được thông tin của chính mình. Vai trò và chức vụ không tự đổi được.
- Manager: tạo, sửa, đặt lại mật khẩu, khóa hoặc mở khóa tài khoản **Staff** (`/admin/staff`), và xem chi tiết khách hàng, kèm đơn online (`/admin/customers`, chỉ đọc). Không xóa tài khoản vì đã gắn với đơn hàng, mẻ nướng, phiếu kho. Manager không sửa hoặc khóa được Manager khác, kể cả chính mình.

## 4. Thông báo được gửi khi nào
| Sự kiện | Người nhận | Nội dung |
|---|---|---|
| Khách đặt online thành công (đã có bản ghi thanh toán) | Khách (+ email nếu bật) | "Đặt hàng thành công · Đơn #id" kèm tổng tiền và giờ dự kiến |
| Như trên | Manager + Staff | "Đơn online mới #id" |
| Đơn chuyển sang Đang chuẩn bị | Khách | "Đơn #id đang được đóng gói" |
| Sang Sẵn sàng, nhận tại tiệm | Khách | "Đơn hàng của bạn đã chuẩn bị xong" |
| Sang Sẵn sàng, giao tận nơi | Khách | "Đơn hàng đang trên đường giao" kèm giờ tới nơi dự kiến |
| Hoàn tất | Khách | "Đơn #id đã hoàn tất" |
| Bếp ghi nhận một mẻ | Manager | Sản phẩm, số đạt, số lỗi, người ghi |
| Manager duyệt kế hoạch sản xuất | Bếp (Thợ làm bánh chính, Nhân viên phụ làm bánh, Thợ trang trí bánh) | Ngày kế hoạch, số kế hoạch |

Việc gửi thông báo nằm trong try/catch (`NotifyService`): lỗi thông báo hoặc email chỉ ghi log, không làm hỏng đơn hàng hay mẻ nướng.

## 5. Giờ giao dự kiến (cấu hình ở `config/crust.php`)
- Đơn online có `estimated_delivery_at` = lúc đặt + chuẩn bị 15 phút + thêm tối đa 2 phút theo số món + 8 phút di chuyển (giao tận nơi), rồi **làm tròn lên** mốc 5 phút.
- Luôn trong khoảng **15–30 phút** sau lúc đặt, không bao giờ 0 phút. Ví dụ đặt 13:00: nhận tại tiệm → 13:15; giao tận nơi → 13:25; đặt 13:00:50, nhận tại tiệm → 13:20.
- Khi nhân viên chuyển đơn giao tận nơi sang "Sẵn sàng" (bắt đầu đi giao), giờ tới nơi được cập nhật = giờ xuất phát + 8 phút, làm tròn lên.
- Đơn POS và đơn cũ không có giờ dự kiến (không hiển thị).

## 6. Routes mới (nếu bạn tự ghép vào routes cũ)
```php
use App\Http\Controllers\AccountController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\Admin\CustomerAccountController;

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

// Trong nhóm admin, middleware CheckRole::class.':manager':
Route::get('staff', [StaffAccountController::class, 'index'])->name('staff.index');
Route::get('staff/create', [StaffAccountController::class, 'create'])->name('staff.create');
Route::post('staff', [StaffAccountController::class, 'store'])->name('staff.store');
Route::get('staff/{user}/edit', [StaffAccountController::class, 'edit'])->whereNumber('user')->name('staff.edit');
Route::put('staff/{user}', [StaffAccountController::class, 'update'])->whereNumber('user')->name('staff.update');
Route::post('staff/{user}/toggle', [StaffAccountController::class, 'toggle'])->whereNumber('user')->name('staff.toggle');
Route::get('customers', [CustomerAccountController::class, 'index'])->name('customers.index');
Route::get('customers/{user}', [CustomerAccountController::class, 'show'])->whereNumber('user')->name('customers.show');
```

## 7. Nếu bạn đã sửa layout riêng
Không cần chép đè layout. Chỉ cần: (a) trong `<head>` thêm `[x-cloak]{display:none !important}`; (b) trong header thay khối chuông và khối menu người dùng cũ bằng
```blade
@include('partials.notification-bell')
@include('partials.user-menu')
```
(c) tùy chọn: thêm `{!! $navLink('admin.customers.index', 'fa-address-book', 'Tài khoản khách hàng') !!}` vào menu Manager.

## 8. Kiểm tra sau khi áp dụng
1. Đăng nhập khách 36004, thêm món vào giỏ, đặt hàng. Trang "Đơn hàng của tôi" hiện giờ dự kiến sau lúc đặt 15–30 phút, chuông có thông báo mới.
2. Đăng nhập Staff 37002, vào Worklist, thấy chuông "Đơn online mới". Bấm "Bắt đầu chuẩn bị" rồi "Đã xong - sẵn sàng".
3. Quay lại khách: chuông tăng số, bấm vào chuông thấy "đang được đóng gói" rồi "đã chuẩn bị xong" (hoặc "đang trên đường giao" nếu chọn giao tận nơi). Bấm một thông báo là đánh dấu đã đọc và chuyển tới đơn.
4. Menu góc phải của Staff và Manager không còn "Đơn hàng của tôi". Menu của khách vẫn còn.
5. Manager: vào "Quản lý tài khoản nhân viên", tạo một Staff, khóa tài khoản đó rồi thử đăng nhập: bị chặn với thông báo tài khoản bị khóa.

## 9. Giới hạn cần biết
- Chuông cập nhật bằng cách hỏi lại server mỗi 30 giây (và mỗi khi quay lại tab), không phải WebSocket. Số đổi trong tối đa 30 giây.
- Thanh toán online trong đồ án là tượng trưng: bản ghi `payments` ở trạng thái Pending tới khi hoàn tất đơn, nên email ghi "chờ xác nhận thanh toán" hoặc "thanh toán khi nhận hàng".
- Cột `users.phone` trong CSDL gốc là kiểu số nên mất số 0 đầu. Giao diện tự bù khi hiển thị (`phoneText()`). Muốn lưu đúng nguyên số thì đổi cột sang `varchar(20)`.
- Tài khoản bị khóa đang đăng nhập sẽ bị đăng xuất ở lần truy cập trang có phân quyền kế tiếp (route dùng `CheckRole`).
- Giờ dự kiến chưa tính giờ đóng cửa tiệm.
