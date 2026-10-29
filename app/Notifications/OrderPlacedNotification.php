<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;

/** Gửi KHÁCH khi đặt hàng online thành công và đã ghi nhận thanh toán: thông báo trong hệ thống + (tùy chọn) email chi tiết đơn. */
class OrderPlacedNotification extends CrustNotification
{
    private const METHODS = ['Cash' => 'Tiền mặt', 'QR_Code' => 'Chuyển khoản QR', 'Momo' => 'Ví Momo'];

    public function __construct(private readonly Order $order) {}

    protected function payload(): array
    {
        $o = $this->order;
        $eta = $o->etaLabel();

        return [
            'kind' => 'order_placed',
            'title' => "Đặt hàng thành công · Đơn #{$o->order_id}",
            'body' => 'Tổng '.self::money($o->total_amount).'. Tiệm đã nhận đơn của bạn.'.($eta ? " {$eta}." : ''),
            'icon' => 'fa-bag-shopping',
            'color' => 'emerald',
            'url' => route('orders.mine', [], false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;
        $payment = $o->payment;
        $method = self::METHODS[$o->payment_method] ?? $o->payment_method;
        $paid = $payment && $payment->status === 'Completed' ? 'đã thanh toán' : ($o->payment_method === 'Cash' ? 'thanh toán khi nhận hàng' : 'chờ xác nhận thanh toán');

        $mail = (new MailMessage)
            ->subject("The Daily Crust – Xác nhận đơn hàng #{$o->order_id}")
            ->greeting('Xin chào '.($notifiable->full_name ?: 'bạn').'!')
            ->line('Cảm ơn bạn đã đặt hàng tại The Daily Crust. Đơn hàng của bạn đã được ghi nhận.')
            ->line("**Mã đơn:** #{$o->order_id} · đặt lúc {$o->order_datetime?->format('H:i d/m/Y')}");

        foreach ($o->lineSummary() as $l) {
            $mail->line("• {$l['qty']} × {$l['name']} – ".self::money($l['subtotal']));
        }

        $mail->line('**Tổng cộng:** '.self::money($o->total_amount))
            ->line("**Thanh toán:** {$method} ({$paid})")
            ->line($o->isDelivery() ? "**Giao đến:** {$o->delivery_address}" : '**Nhận hàng:** tự đến lấy tại tiệm');

        if ($eta = $o->etaLabel()) {
            $mail->line("**{$eta}**");
        }

        return $mail
            ->action('Xem đơn hàng của tôi', url(route('orders.mine', [], false)))
            ->salutation('Trân trọng, The Daily Crust');
    }
}
