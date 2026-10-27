<?php

use App\Console\Commands\RunProductionForecast;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 21:00 mỗi ngày: dự báo sản lượng cho ngày mai.
// Truyền CLASS (không gõ chuỗi tên lệnh) nên tên lệnh luôn lấy từ $signature của chính Command:
// đổi tên lệnh ở đó thì lịch chạy tự đổi theo, không thể bị lệch như trước.
Schedule::command(RunProductionForecast::class)
    ->dailyAt('21:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/forecast.log'));
