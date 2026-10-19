<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Định mức nguyên liệu cho 1 đơn vị sản phẩm (bảng `bill_of_materials`). */
class BillOfMaterial extends BakeryModel
{
    protected $table = 'bill_of_materials';

    protected $primaryKey = 'bom_id';

    protected function casts(): array
    {
        return ['quantity_required' => 'float'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }
}
