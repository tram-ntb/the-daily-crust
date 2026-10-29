<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;

/** Gửi KHÁCH khi đơn online đổi trạng thái: đang đóng gói / đã chuẩn bị xong / đang trên đường giao / hoàn tất. */
class OrderStatusNotification extends CrustNotification
{
    public function __construct(private readonly Order $order) {}

    /** @return array{title:string,body:string,icon:string,color:string} */
    private function content(): array
    {
        $o = $this->order;
        $id = $o->order_id;
        $eta = $o->estimated_delivery_at;

        return match ($o->status) {
            Order::STATUS_PREPARING => [
                'title' => "Đơn #{$id} đang được đóng gói",
                'body' => 'Tiệm đã nhận đơn và đang chuẩn bị cho bạn.'.($o->etaLabel() ? ' '.$o->etaLabel().'.' : ''),
                'icon' => 'fa-fire-burner', 'color' => 'amber',
            ],
            Order::STATUS_READY => $o->isDelivery()
                ? [
                    'title' => "Đơn hàng đang trên đường giao · #{$id}",
                    'body' => 'Đơn hàng đang trên đường giao đến '.($o->delivery_address ?: 'địa chỉ của bạn').'.'
                        .($eta && $eta->isFuture() ? ' Dự kiến tới lúc '.$eta->format('H:i').'.' : ' Đơn sắp tới nơi.'),
                    'icon' => 'fa-motorcycle', 'color' => 'sky',
                ]
                : [
                    'title' => "Đơn hàng của bạn đã chuẩn bị xong · #{$id}",
                    'body' => 'Đơn hàng của bạn đã chuẩn bị xong, mời bạn đến tiệm nhận hàng.',
                    'icon' => 'fa-box-open', 'color' => 'emerald',
                ],
            Order::STATUS_COMPLETED => [
                'title' => "Đơn #{$id} đã hoàn tất",
                'body' => ($o->isDelivery() ? 'Đơn hàng đã được giao thành công. ' : 'Bạn đã nhận hàng. ').'Cảm ơn bạn đã mua hàng tại The Daily Crust!',
                'icon' => 'fa-circle-check', 'color' => 'stone',
            ],
            default => [
                'title' => "Đơn #{$id} cập nhật trạng thái",
                'body' => $o->customerStatusLabel().'.',
                'icon' => 'fa-bell', 'color' => 'stone',
            ],
        };
    }

    protected function payload(): array
    {
        return $this->content() + [
            'kind' => 'order_status',
            'url' => route('orders.mine', [], false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->content();

        return (new MailMessage)
            ->subject('The Daily Crust – '.$c['title'])
            ->greeting('Xin chào '.($notifiable->full_name ?: 'bạn').'!')
            ->line($c['body'])
            ->action('Xem đơn hàng của tôi', url(route('orders.mine', [], false)))
            ->salutation('Trân trọng, The Daily Crust');
    }
}
