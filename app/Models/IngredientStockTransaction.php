<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientStockTransaction extends Model
{
    protected $table = 'ingredient_stock_transactions';

    protected $primaryKey = 'transaction_id';

    public $timestamps = false;

    protected $fillable = [
        'ingredient_id',
        'transaction_type',
        'quantity',
        'unit',
        'reference_type',
        'reference_id',
        'transaction_date',
        'user_id',
    ];
}
