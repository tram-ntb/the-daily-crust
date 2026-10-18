<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionPlan extends Model
{
    protected $table = 'production_plans';

    protected $primaryKey = 'plan_id';

    public $timestamps = false;

    protected $fillable = [
        'plan_date',
        'product_id',
        'planned_qty',
        'approved_by',
        'approved_at',
        'created_by',
        'forecasted_qty',
        'adjusted_qty',
        'plan_status',
        'adjustment_reason',
    ];

    protected $casts = [
        'plan_date' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
