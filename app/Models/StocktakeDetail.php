<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocktakeDetail extends Model
{
    protected $table = 'stocktake_details';

    protected $primaryKey = 'stocktake_detail_id';

    public $timestamps = false;

    protected $fillable = [
        'stocktake_id', 'item_type', 'ingredient_id', 'product_id',
        'lot_id', 'system_qty', 'actual_qty', 'unit', 'variance_qty',
        'variance_percentage', 'reason_code', 'explanation', 'created_by',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }
}
