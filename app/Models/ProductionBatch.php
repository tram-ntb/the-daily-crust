<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionBatch extends Model
{
    protected $table = 'production_batches';

    protected $primaryKey = 'batch_id';

    public $timestamps = false;

    protected $fillable = [
        'batch_date',
        'plan_id',
        'product_id',
        'produced_qty',
        'passed_qty',
        'failed_qty',
        'ai_image_url',
        'user_id',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'batch_date' => 'datetime',
    ];
}
