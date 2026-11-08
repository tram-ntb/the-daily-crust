<?php

use App\Console\Commands\RunProductionForecast;
use App\Models\ProductionForecast;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// DỰ BÁO TỰ ĐỘNG. Lưu ý: file này CHỈ KHAI BÁO lịch; muốn lịch chạy thật, máy chủ phải gọi
// `php artisan schedule:run` MỖI PHÚT (Windows: Task Scheduler, xem scripts/windows/cai_dat_task_scheduler.bat).
// Truyền CLASS (không gõ chuỗi tên lệnh) nên tên lệnh luôn lấy từ $signature của chính Command.
// ---------------------------------------------------------------------------

// 1) Lịch chính: 21:00 mỗi ngày, dự báo sản lượng cho ngày mai.
Schedule::command(RunProductionForecast::class)
    ->dailyAt('21:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping(30)   // khóa tự hết sau 30 phút (mặc định 24 giờ: một lần treo sẽ chặn cả ngày hôm sau)
    ->appendOutputTo(storage_path('logs/forecast.log'));

// 2) Lịch bù: nếu máy bị tắt/ngủ đúng 21:00, mỗi giờ từ 21:00 đến 23:59 thử lại
//    cho đến khi ngày mai đã có dự báo sản xuất. Khi đã có rồi thì không chạy nữa.
Schedule::command(RunProductionForecast::class)
    ->hourly()
    ->between('21:00', '23:59')
    ->timezone('Asia/Ho_Chi_Minh')
    ->when(fn () => ! ProductionForecast::production()
        ->whereDate('forecast_date', Carbon::tomorrow('Asia/Ho_Chi_Minh')->toDateString())
        ->exists())
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/forecast.log'));
