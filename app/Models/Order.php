<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/** Đơn hàng (bảng `orders`). Kênh bán: POS (tại quầy) hoặc App (khách đặt online). */
class Order extends BakeryModel
{
    public const CHANNEL_POS = 'POS';
    public const CHANNEL_APP = 'App';

    public const STATUS_NEW = 'New';
    public const STATUS_PREPARING = 'Preparing';
    public const STATUS_READY = 'Ready';
    public const STATUS_COMPLETED = 'Completed';

    /** Luồng trạng thái cho phép của đơn online: trạng thái hiện tại => trạng thái kế tiếp. */
    public const NEXT_STATUS = [
        self::STATUS_NEW => self::STATUS_PREPARING,
        self::STATUS_PREPARING => self::STATUS_READY,
        self::STATUS_READY => self::STATUS_COMPLETED,
    ];

    protected $table = 'orders';

    protected $primaryKey = 'order_id';

    protected function casts(): array
    {
        return [
            'order_datetime' => 'datetime',
            'prepared_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_amount' => 'integer',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'order_id', 'order_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'order_id', 'order_id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(DeliveryOrder::class, 'order_id', 'order_id');
    }

    /** Người tạo đơn: thu ngân (đơn POS) hoặc khách hàng (đơn online). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Gộp các dòng chi tiết (mỗi dòng = 1 sản phẩm) thành "tên × số lượng" để hiển thị.
     * Cần eager load `details.product`.
     *
     * @return Collection<int, array{product_id:int,name:string,qty:int,price:int,subtotal:int}>
     */
    public function lineSummary(): Collection
    {
        return $this->details->groupBy('product_id')->map(function (Collection $rows, $productId) {
            $qty = (int) $rows->sum(fn (OrderDetail $d) => $d->quantity ?: 1);
            $price = (int) $rows->first()->price;

            return [
                'product_id' => (int) $productId,
                'name' => $rows->first()->product->vi_name ?? ('Sản phẩm #'.$productId),
                'qty' => $qty,
                'price' => $price,
                'subtotal' => $price * $qty,
            ];
        })->values();
    }

    public function isOnline(): bool
    {
        return $this->order_channel === self::CHANNEL_APP;
    }
}
