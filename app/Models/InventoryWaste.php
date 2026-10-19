<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Phiếu tiêu hủy hàng hỏng (bảng `inventory_waste`). approved_by = NULL nghĩa là chờ quản lý duyệt. */
class InventoryWaste extends BakeryModel
{
    protected $table = 'inventory_waste';

    protected $primaryKey = 'ticket_id';

    protected function casts(): array
    {
        return ['ticket_date' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function itemName(): string
    {
        return $this->product?->vi_name ?? $this->ingredient?->ingredient_name ?? '—';
    }
}
