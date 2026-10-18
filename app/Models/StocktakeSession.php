<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StocktakeSession extends Model
{
    protected $table = 'stocktake_sessions';

    protected $primaryKey = 'stocktake_id';

    public $timestamps = false; // Hoặc true nếu có cột created_at/updated_at

    protected $fillable = [
        'stocktake_date', 'session_name', 'status',
        'created_by', 'approved_by', 'approved_at', 'note',
    ];

    public function details()
    {
        return $this->hasMany(StocktakeDetail::class, 'stocktake_id', 'stocktake_id');
    }
}
