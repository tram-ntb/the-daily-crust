<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Lớp cha của mọi thông báo trong hệ thống.
 *  - Luôn lưu vào bảng `notifications` (Database Notifications) để hiện ở chuông và trang "Thông báo".
 *  - Chỉ gửi thêm email khi lớp con có toMail() VÀ config('crust.notify_mail') bật VÀ người nhận có email.
 *    (Mặc định tắt vì dữ liệu đồ án là giả lập; bật bằng CRUST_NOTIFY_MAIL=true + MAIL_MAILER=log để demo.)
 */
abstract class CrustNotification extends Notification
{
    /** @return array{kind:string,title:string,body:string,icon:string,color:string,url:string} */
    abstract protected function payload(): array;

    public function via(object $notifiable): array
    {
        $channels = ['database'];   // database đứng đầu: email lỗi cũng không làm mất thông báo trong hệ thống

        if (method_exists($this, 'toMail') && config('crust.notify_mail') && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** Dữ liệu lưu vào cột `data` (JSON). `url` là đường dẫn TƯƠNG ĐỐI để không phụ thuộc APP_URL. */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    protected static function money(int|float|null $v): string
    {
        return number_format((float) $v, 0, ',', '.').' đ';
    }
}
