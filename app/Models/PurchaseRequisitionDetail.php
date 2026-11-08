<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dòng chi tiết phiếu đề xuất mua (bảng `purchase_requisition_details`). */
class PurchaseRequisitionDetail extends BakeryModel
{
    protected $table = 'purchase_requisition_details';

    protected $primaryKey = 'detail_id';

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }
}
