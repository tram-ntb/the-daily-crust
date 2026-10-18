<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockMovement extends Model
{
    protected $table = 'product_stock_movements';

    protected $primaryKey = 'movement_id';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'lot_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'movement_date',
        'user_id',
        'note',
    ];
}
