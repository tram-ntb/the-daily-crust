<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockTransaction extends Model
{
    protected $table = 'product_stock_transactions';

    protected $primaryKey = 'transaction_id';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'lot_id',
        'transaction_type',
        'quantity',
        'reference_type',
        'reference_id',
        'transaction_date',
    ];
}
