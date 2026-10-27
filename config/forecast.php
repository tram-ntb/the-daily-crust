<?php

return [
    // Lệnh Python: Windows thường là "python" hoặc "py"; nếu dùng venv thì trỏ tới python.exe trong venv
    'python' => env('FORECAST_PYTHON', 'python'),

    // Đường dẫn tuyệt đối tới script (mặc định: <project>/python/forecast_production.py)
    'script' => env('FORECAST_SCRIPT', base_path('python/forecast_production.py')),

    // Thời gian chờ tối đa (giây) khi chạy từ web/artisan
    'timeout' => (int) env('FORECAST_TIMEOUT', 180),

    // NHÓM HÀNG NHẬP TỪ NCC (không sản xuất tại bếp): chỉ dự báo nhu cầu 7 ngày để gợi ý nhập hàng.
    // Mọi nhóm còn lại (kể cả bánh quy trẻ em) là hàng sản xuất tại bếp, dự báo sản lượng mỗi ngày.
    // Đây là NGUỒN DUY NHẤT: lệnh Artisan truyền danh sách này cho Python, giao diện cũng đọc từ đây.
    'commercial_categories' => ['Đồ uống', 'Hàng thương mại & Quà tặng'],

    // Gợi ý nhập hàng = nhu cầu 7 ngày x (1 + tỷ lệ dự phòng) - tồn hiện tại
    'restock_safety_ratio' => (float) env('FORECAST_RESTOCK_SAFETY', 0.2),
];
