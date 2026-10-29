<?php

return [
    /*
    | Gửi thêm email cho khách (xác nhận đơn, đổi trạng thái đơn) ngoài thông báo trong hệ thống.
    | Mặc định TẮT vì dữ liệu đồ án là giả lập. Muốn demo email: đặt CRUST_NOTIFY_MAIL=true và MAIL_MAILER=log
    | (email sẽ được ghi vào storage/logs/laravel.log, không gửi ra ngoài).
    */
    'notify_mail' => (bool) env('CRUST_NOTIFY_MAIL', false),

    /*
    | Thời gian dự kiến cho đơn ONLINE (phút), tính từ lúc khách đặt:
    |   dự kiến = prep_base + phần cộng thêm theo số món (tối đa prep_extra_cap) + travel (nếu giao tận nơi),
    |   rồi làm tròn LÊN theo round_to phút và không bao giờ nhỏ hơn min_lead.
    | Kết quả luôn nằm trong khoảng 15–30 phút sau lúc đặt (tối thiểu min_lead, tối đa khoảng 30 phút kể cả làm tròn):
    | Ví dụ khách đặt 13:00, nhận tại tiệm, 2 món  => 13:15
    |       khách đặt 13:00, giao tận nơi, 2 món   => 13:25
    |       khách đặt 13:00, giao tận nơi, 12 món  => 13:25 (phần cộng thêm theo số món tối đa prep_extra_cap phút)
    |       khách đặt 13:00:50, nhận tại tiệm      => 13:20 (luôn làm tròn LÊN, không bao giờ xuống)
    */
    'delivery' => [
        'prep_base' => 15,
        'free_items' => 3,
        'extra_per_item' => 1,
        'prep_extra_cap' => 2,
        'travel' => 8,
        'round_to' => 5,
        'min_lead' => 15,
    ],

    /*
    | Vị trí thuộc bộ phận bếp: nhận thông báo "kế hoạch sản xuất đã được duyệt".
    */
    'kitchen_positions' => ['Thợ làm bánh chính', 'Nhân viên phụ làm bánh', 'Thợ trang trí bánh'],

    /*
    | Danh sách chức vụ gợi ý khi Quản lý tạo / sửa tài khoản nhân viên.
    */
    'staff_positions' => [
        'Thợ làm bánh chính', 'Nhân viên phụ làm bánh', 'Thợ trang trí bánh',
        'Thu ngân', 'Nhân viên tư vấn', 'Nhân viên phục vụ',
    ],

    /*
    | Chuông thông báo: số thông báo mới nhất hiện trong dropdown và chu kỳ tự cập nhật (giây).
    */
    'bell' => ['limit' => 10, 'poll_seconds' => 30],
];
