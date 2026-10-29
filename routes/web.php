<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\BomController;
use App\Http\Controllers\Admin\CustomerAccountController;
use App\Http\Controllers\Admin\ForecastController;
use App\Http\Controllers\Admin\ProductionPlanController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\OnlineOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductDetailController;
use App\Http\Controllers\Staff\BatchController;
use App\Http\Controllers\Staff\CatalogController;
use App\Http\Controllers\Staff\ExpiryController;
use App\Http\Controllers\Staff\PosController;
use App\Http\Controllers\Staff\ProductionController;
use App\Http\Controllers\Staff\StocktakeController;
use App\Http\Controllers\Staff\WasteController;
use App\Http\Controllers\Staff\WorklistController;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Route;

// 1. Trang chủ mua sắm (Dành cho Khách vãng lai & Khách hàng thành viên)
Route::get('/', DashboardController::class)->name('home');

Route::get('/dashboard', DashboardController::class)->name('dashboard');

// 3. Xác thực (Đăng nhập, Đăng ký, Đăng xuất)
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/register', [LoginController::class, 'storeRegister'])->name('register.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Trang chi tiết sản phẩm: CÔNG KHAI (khách vãng lai cũng xem được), chỉ liệt kê tên nguyên liệu, không định lượng
Route::get('/products/{product}', [ProductDetailController::class, 'show'])->whereNumber('product')->name('products.show');

// TÀI KHOẢN + THÔNG BÁO: mọi người dùng đã đăng nhập (Customer, Staff, Manager), chỉ thao tác trên dữ liệu của chính mình
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

// 2. GIỎ HÀNG (lưu trong CSDL theo tài khoản) + ĐẶT HÀNG ONLINE (chỉ khách hàng đã đăng nhập)
Route::middleware(['auth', CheckRole::class.':customer'])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.add');
    Route::put('/cart/items/{productId}', [CartController::class, 'update'])->whereNumber('productId')->name('cart.update');
    Route::delete('/cart/items/{productId}', [CartController::class, 'destroy'])->whereNumber('productId')->name('cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/merge', [CartController::class, 'merge'])->name('cart.merge');

    Route::post('/orders', [OnlineOrderController::class, 'store'])->name('orders.store');
    Route::get('/my-orders', [OnlineOrderController::class, 'mine'])->name('orders.mine');
});

// 3. KHU VỰC QUẢN TRỊ (Dành riêng cho Nhân viên & Quản lý)
Route::middleware(['auth', CheckRole::class.':manager,staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard dùng chung, nội dung tự đổi theo vai trò (xem AdminDashboardController)
        Route::get('/', AdminDashboardController::class)->name('dashboard');   // => admin.dashboard

        // ===== PHÂN HỆ 1: KHO & SẢN XUẤT ĐẦU NGÀY =====
        // Các trang XEM: Staff và Manager đều vào được (sidebar của Manager cũng trỏ tới đây).
        Route::get('ingredients', [CatalogController::class, 'ingredients'])->name('ingredients.index');
        Route::get('products/stock', [CatalogController::class, 'products'])->name('products.stock');
        Route::get('suppliers', [CatalogController::class, 'suppliers'])->name('suppliers.index');

        Route::get('production', [ProductionController::class, 'index'])->name('production.index');
        Route::get('waste', [WasteController::class, 'index'])->name('waste.index');
        Route::get('stocktake', [StocktakeController::class, 'index'])->name('stocktake.index');
        Route::get('stocktake/{stocktake}', [StocktakeController::class, 'show'])->name('stocktake.show');

        // ===== PHÂN HỆ 3: BOM, KẾ HOẠCH SẢN XUẤT, MẺ NƯỚNG, KIỂM KÊ, HÀNG CẬN DATE =====
        // Các trang XEM: Staff và Manager đều vào được.
        Route::get('bom', [BomController::class, 'index'])->name('bom.index');
        Route::get('bom/{product}', [BomController::class, 'show'])->name('bom.show');
        Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
        Route::get('expiry', [ExpiryController::class, 'index'])->name('expiry.index');
        Route::get('stocktake/{stocktake}/report', [StocktakeController::class, 'report'])->name('stocktake.report');

        // ===== PHÂN HỆ 2: BÁN HÀNG (POS & WORKLIST) =====
        // Worklist đơn hàng: xem được bởi Staff và Manager
        Route::get('orders', [WorklistController::class, 'index'])->name('orders.index');

        // Các thao tác GHI (thay đổi kho): CHỈ STAFF, chặn ở route chứ không chỉ ẩn nút.
        Route::middleware(CheckRole::class.':staff')->group(function () {
            Route::post('production/{plan}/acknowledge', [ProductionController::class, 'acknowledge'])->name('production.acknowledge');
            Route::get('production/{plan}/batches/create', [ProductionController::class, 'create'])->name('production.batches.create');
            Route::post('production/{plan}/batches', [ProductionController::class, 'store'])->name('production.batches.store');

            Route::post('waste', [WasteController::class, 'store'])->name('waste.store');

            Route::post('stocktake', [StocktakeController::class, 'store'])->name('stocktake.store');
            Route::put('stocktake/{stocktake}', [StocktakeController::class, 'update'])->name('stocktake.update');
            Route::put('stocktake/{stocktake}/report', [StocktakeController::class, 'saveReport'])->name('stocktake.report.save');

            Route::post('expiry/lots/{lot}/destroy', [ExpiryController::class, 'destroy'])->name('expiry.destroy');
            Route::post('expiry/destroy-expired', [ExpiryController::class, 'destroyExpired'])->name('expiry.destroy-expired');

            // POS + cập nhật trạng thái đơn: CHỈ STAFF
            Route::get('pos', [PosController::class, 'index'])->name('pos');
            Route::post('pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
            Route::post('orders/{order}/advance', [WorklistController::class, 'advance'])->name('orders.advance');
        });

        // Các chức năng khác (làm dần, tên route khớp với sidebar/lối tắt):
        // Route::get('shift-report', ...)->name('shift-report');

        // CHỈ MANAGER: chặn ở route, không chỉ ẩn nút trên giao diện
        Route::middleware(CheckRole::class.':manager')->group(function () {
            Route::get('forecast', [ForecastController::class, 'index'])->name('forecast.index');
            Route::post('forecast/run', [ForecastController::class, 'run'])->name('forecast.run');
            Route::get('forecast/diagnose', [ForecastController::class, 'diagnose'])->name('forecast.diagnose');
            // Route::get('purchase', ...)->name('purchase.index');
            // Route::get('purchase/create', ...)->name('purchase.create');

            // Phân hệ 3: lập kế hoạch từ dự báo, sửa BOM, duyệt phiếu hủy / kiểm kê
            Route::get('plans', [ProductionPlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [ProductionPlanController::class, 'store'])->name('plans.store');
            Route::post('plans/{plan}/approve', [ProductionPlanController::class, 'approve'])->name('plans.approve');
            Route::post('plans/{plan}/unapprove', [ProductionPlanController::class, 'unapprove'])->name('plans.unapprove');

            Route::put('bom/{product}', [BomController::class, 'update'])->name('bom.update');

            Route::post('waste/approve-all', [WasteController::class, 'approveAll'])->name('waste.approve-all');
            Route::post('waste/{ticket}/approve', [WasteController::class, 'approve'])->name('waste.approve');

            Route::post('stocktake/{stocktake}/approve', [StocktakeController::class, 'approve'])->name('stocktake.approve');
            Route::post('stocktake/{stocktake}/reject', [StocktakeController::class, 'reject'])->name('stocktake.reject');
            // Route::get('reports/revenue', ...)->name('reports.revenue');

            // Quản lý tài khoản nhân viên (tạo / sửa / khóa) và xem tài khoản khách hàng (chỉ đọc)
            Route::get('staff', [StaffAccountController::class, 'index'])->name('staff.index');
            Route::get('staff/create', [StaffAccountController::class, 'create'])->name('staff.create');
            Route::post('staff', [StaffAccountController::class, 'store'])->name('staff.store');
            Route::get('staff/{user}/edit', [StaffAccountController::class, 'edit'])->whereNumber('user')->name('staff.edit');
            Route::put('staff/{user}', [StaffAccountController::class, 'update'])->whereNumber('user')->name('staff.update');
            Route::post('staff/{user}/toggle', [StaffAccountController::class, 'toggle'])->whereNumber('user')->name('staff.toggle');
            Route::get('customers', [CustomerAccountController::class, 'index'])->name('customers.index');
            Route::get('customers/{user}', [CustomerAccountController::class, 'show'])->whereNumber('user')->name('customers.show');
        });
    });
