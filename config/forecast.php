<?php

return [
    // Lệnh Python: Windows thường là "python" hoặc "py"; nếu dùng venv thì trỏ tới python.exe trong venv
    'python' => env('FORECAST_PYTHON', 'python'),

    // Đường dẫn tuyệt đối tới script (mặc định: <project>/python/forecast_production.py)
    'script' => env('FORECAST_SCRIPT', base_path('python/forecast_production.py')),

    // Thời gian chờ tối đa (giây) khi chạy từ web/artisan
    'timeout' => (int) env('FORECAST_TIMEOUT', 180),
];
