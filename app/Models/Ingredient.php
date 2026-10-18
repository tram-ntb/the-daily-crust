<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $table = 'ingredients';

    protected $primaryKey = 'ingredient_id';

    public $timestamps = false;

    protected $fillable = [
        'ingredient_name', 'category', 'unit', 'stock_quantity',
        'min_stock_level', 'cost_price_vn', 'tolerance_rate',
    ];
}
