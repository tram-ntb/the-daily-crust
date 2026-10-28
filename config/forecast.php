<?php

return [
    // Lệnh Python: Windows thường là "python" hoặc "py"; nếu dùng venv thì trỏ tới python.exe trong venv
    'python' => env('FORECAST_PYTHON', 'python'),

    // Đường dẫn tuyệt đối tới script (mặc định: <project>/python/forecast_production.py)
    'script' => env('FORECAST_SCRIPT', base_path('python/forecast_production.py')),

    // Thời gian chờ tối đa (giây) khi chạy từ web/artisan
    'timeout' => (int) env('FORECAST_TIMEOUT', 180),

    // PHÂN LOẠI SẢN PHẨM (nguồn duy nhất: lệnh Artisan truyền sang Python, giao diện và kế hoạch sản xuất cũng đọc từ đây)
    //  - commercial_categories: luôn là hàng NHẬP từ nhà cung cấp (hàng thương mại, quà tặng): dự báo nhu cầu 7 ngày để gợi ý nhập hàng.
    //  - bom_decides_categories: nhóm do công thức (BOM) quyết định. Có BOM = pha chế tại quán (cà phê, trà, sinh tố...) -> dự báo
    //    sản lượng mỗi ngày như hàng sản xuất; KHÔNG có BOM = đóng chai nhập nguyên kiện (nước suối...) -> hàng nhập.
    //  - Mọi nhóm còn lại (bánh mì, bánh ngọt, món mặn, bánh quy trẻ em...) là hàng sản xuất tại bếp, bất kể hạn dùng.
    'commercial_categories' => ['Hàng thương mại & Quà tặng'],
    'bom_decides_categories' => ['Đồ uống'],

    //  - made_to_order_categories: nhóm PHA THEO ĐƠN (cà phê, trà, sinh tố...). Vẫn được dự báo và đưa vào kế hoạch nguyên liệu theo BOM,
    //    nhưng KHÔNG ghi nhận theo mẻ: không xuất hiện ở màn hình Tiến độ sản xuất / Ghi nhận mẻ / Theo dõi mẻ nướng và không tạo lô thành phẩm.
    'made_to_order_categories' => ['Đồ uống'],

    // Gợi ý nhập hàng = nhu cầu 7 ngày x (1 + tỷ lệ dự phòng) - tồn hiện tại
    'restock_safety_ratio' => (float) env('FORECAST_RESTOCK_SAFETY', 0.2),
];
