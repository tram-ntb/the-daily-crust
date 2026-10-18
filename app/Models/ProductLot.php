<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLot extends Model
{
    protected $table = 'product_lots';

    protected $primaryKey = 'lot_id';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'batch_id',
        'production_date',
        'expiry_date',
        'initial_qty',
        'remaining_qty',
        'status',
    ];

    protected $casts = [
        'production_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];
}
