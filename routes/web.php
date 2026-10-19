<?php

use App\Http\Controllers\Admin\ForecastController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\OnlineOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Staff\CatalogController;
use App\Http\Controllers\Staff\PosController;
use App\Http\Controllers\Staff\ProductionController;
use App\Http\Controllers\Staff\StocktakeController;
use App\Http\Controllers\Staff\WorklistController;
use App\Http\Controllers\Staff\WasteController;
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

// 2. ĐẶT HÀNG ONLINE (chỉ khách hàng đã đăng nhập)
Route::middleware(['auth', CheckRole::class.':customer'])->group(function () {
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
            // Route::get('purchase', ...)->name('purchase.index');
            // Route::get('purchase/create', ...)->name('purchase.create');
            // Route::get('bom', ...)->name('bom.index');
            // Route::get('reports/revenue', ...)->name('reports.revenue');
            // Route::get('staff', ...)->name('staff.index');
        });
    });
