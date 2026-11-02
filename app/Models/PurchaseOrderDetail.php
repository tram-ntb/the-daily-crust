<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dòng chi tiết đơn mua (bảng `purchase_order_details`). */
class PurchaseOrderDetail extends BakeryModel
{
    protected $table = 'purchase_order_details';

    protected $primaryKey = 'detail_id';

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }
}
