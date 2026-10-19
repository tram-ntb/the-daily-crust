<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 21:00 mỗi ngày: dự báo sản lượng cho ngày mai (xem hướng dẫn Task Scheduler / schedule:work)
Schedule::command('forecast:run')
    ->dailyAt('21:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/forecast.log'));
