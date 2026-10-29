<?php

namespace App\Notifications;

/** Thông báo nội bộ tổng quát (cho Manager / Staff): đơn online mới, mẻ nướng đã ghi nhận, kế hoạch đã duyệt... Chỉ lưu trong hệ thống. */
class SystemNotification extends CrustNotification
{
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
        private readonly string $icon = 'fa-bell',
        private readonly string $color = 'stone',
        private readonly string $kind = 'system',
    ) {}

    protected function payload(): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'icon' => $this->icon,
            'color' => $this->color,
            'url' => $this->url,
        ];
    }
}
