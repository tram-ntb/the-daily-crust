<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillOfMaterial extends Model
{
    protected $table = 'bill_of_materials';

    protected $primaryKey = 'bom_id';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_name',
        'ingredient_id',
        'ingredient_name',
        'quantity_required',
        'unit',
    ];
}
