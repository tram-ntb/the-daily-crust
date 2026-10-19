<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dòng kiểm kê: số hệ thống (system_qty) đối soát với số thực tế (actual_qty). */
class StocktakeDetail extends BakeryModel
{
    protected $table = 'stocktake_details';

    protected $primaryKey = 'stocktake_detail_id';

    protected function casts(): array
    {
        return [
            // Hai cột này trong CSDL là decimal(15,2) nên ép về số nguyên để so khớp khóa
            'product_id' => 'integer',
            'lot_id' => 'integer',
            'variance_percentage' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id', 'lot_id');
    }

    public function label(): string
    {
        if ($this->item_type === 'Ingredient') {
            return $this->ingredient?->ingredient_name ?? 'Nguyên liệu #'.$this->ingredient_id;
        }

        return ($this->lot?->product?->vi_name ?? 'Thành phẩm').' · Lô #'.$this->lot_id;
    }
}
