<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusNotification;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Điểm gửi thông báo duy nhất của hệ thống. Mọi lệnh gửi đều được bọc try/catch:
 * thông báo (hoặc email) lỗi chỉ ghi log chứ KHÔNG làm hỏng đơn hàng / mẻ nướng đang xử lý.
 */
class NotifyService
{
    /** Khách đặt online thành công: xác nhận cho khách + báo nhân viên/quản lý có đơn mới. */
    public function orderPlaced(Order $order): void
    {
        $order->loadMissing(['details.product', 'payment', 'creator']);

        $this->safely(function () use ($order) {
            if ($order->creator) {
                $order->creator->notify(new OrderPlacedNotification($order));
            }
        });

        $this->toInternal(
            ['manager', 'staff'],
            new SystemNotification(
                "Đơn online mới #{$order->order_id}",
                ($order->creator->full_name ?? 'Khách hàng').' · '.number_format((int) $order->total_amount, 0, ',', '.').' đ · '
                    .($order->isDelivery() ? 'giao tận nơi' : 'nhận tại tiệm').($order->etaLabel() ? ' · '.$order->etaLabel() : ''),
                route('admin.orders.index', [], false),
                'fa-bag-shopping', 'sky', 'order_new'
            )
        );
    }

    /** Đơn online đổi trạng thái: báo cho khách (đóng gói / chuẩn bị xong / đang giao / hoàn tất). */
    public function orderStatusChanged(Order $order): void
    {
        if (! $order->isOnline() || ! in_array($order->status, [Order::STATUS_PREPARING, Order::STATUS_READY, Order::STATUS_COMPLETED], true)) {
            return;
        }

        $order->loadMissing('creator');

        $this->safely(function () use ($order) {
            if ($order->creator && $order->creator->isCustomer()) {
                $order->creator->notify(new OrderStatusNotification($order));
            }
        });
    }

    /** Gửi cho Quản lý. */
    public function toManagers(string $title, string $body, string $url, string $icon = 'fa-bell', string $color = 'stone', string $kind = 'system'): void
    {
        $this->toInternal(['manager'], new SystemNotification($title, $body, $url, $icon, $color, $kind));
    }

    /** Gửi cho bộ phận bếp (các chức vụ trong config crust.kitchen_positions). */
    public function toKitchen(string $title, string $body, string $url, string $icon = 'fa-bell', string $color = 'stone', string $kind = 'system'): void
    {
        $this->safely(function () use ($title, $body, $url, $icon, $color, $kind) {
            $users = User::query()->where('role', 'staff')
                ->whereIn('position', (array) config('crust.kitchen_positions', []))->get();
            Notification::send($users, new SystemNotification($title, $body, $url, $icon, $color, $kind));
        });
    }

    /** @param  string[]  $roles */
    private function toInternal(array $roles, SystemNotification $notification): void
    {
        $this->safely(function () use ($roles, $notification) {
            Notification::send(User::query()->whereIn('role', $roles)->get(), $notification);
        });
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
