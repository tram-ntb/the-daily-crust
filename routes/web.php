<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Staff\InventoryController;
use App\Http\Controllers\Staff\ProductionController;
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

// 2. KHU VỰC QUẢN TRỊ (Dành riêng cho Nhân viên & Quản lý)
Route::middleware(['auth', CheckRole::class.':manager,staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard dùng chung, nội dung tự đổi theo vai trò (xem AdminDashboardController)
        Route::get('/', AdminDashboardController::class)->name('dashboard');   // => admin.dashboard

        // Các chức năng của cả hai vai trò (làm dần, tên route khớp với sidebar/lối tắt):
        // Route::get('pos', ...)->name('pos');
        // Route::get('orders', ...)->name('orders.index');
        // Route::get('ingredients', ...)->name('ingredients.index');
        // Route::get('production', ...)->name('production.index');
        // Route::get('stocktake', ...)->name('stocktake.index');
        // Route::get('shift-report', ...)->name('shift-report');

        // CHỈ MANAGER: chặn ở route, không chỉ ẩn nút trên giao diện
        Route::middleware(CheckRole::class.':manager')->group(function () {
            // Route::post('forecast/run', ...)->name('forecast.run');
            // Route::get('forecast', ...)->name('forecast.index');
            // Route::get('purchase', ...)->name('purchase.index');
            // Route::get('purchase/create', ...)->name('purchase.create');
            // Route::get('bom', ...)->name('bom.index');
            // Route::get('reports/revenue', ...)->name('reports.revenue');
            // Route::get('discounts', ...)->name('discounts.index');
            // Route::get('staff', ...)->name('staff.index');
        });
    });

// 4. Phân hệ 1: Quản lý Kho & Danh mục (Inventory & Master Data)
Route::middleware(['auth', 'role:manager,staff'])->prefix('admin')->name('admin.')->group(function (): void {
    // Quản lý Kho & Danh mục
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/waste', [InventoryController::class, 'wasteIndex'])->name('inventory.waste.index');
    Route::post('/inventory/waste', [InventoryController::class, 'storeWaste'])->name('inventory.waste.store');
    Route::get('/stocktake', [InventoryController::class, 'stocktakeIndex'])->name('stocktake.index');
    Route::post('/stocktake', [InventoryController::class, 'storeStocktake'])->name('stocktake.store');

    // Quản lý sản xuất đầu ngày tại bếp
    Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
    Route::post('/production/plan/accept', [ProductionController::class, 'acceptPlan'])->name('production.plan.accept');
    Route::post('/production/batch', [ProductionController::class, 'storeBatch'])->name('production.batch.store');
});
