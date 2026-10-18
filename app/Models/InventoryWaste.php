<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryWaste extends Model
{
    protected $table = 'inventory_waste';

    protected $primaryKey = 'ticket_id';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'lot_id', 'ingredient_id', 'ticket_date',
        'quantity', 'reason_type', 'user_id', 'item_type',
        'unit', 'unit_cost', 'total_cost', 'approved_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
