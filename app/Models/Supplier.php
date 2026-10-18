<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $primaryKey = 'supplier_id';

    public $timestamps = false;

    protected $fillable = [
        'supplier_code',
        'company_name',
        'contact_name',
        'phone',
        'email',
        'address',
        'tax_code',
        'payment_terms',
        'lead_time_days',
        'status',
        'notes',
    ];
}
