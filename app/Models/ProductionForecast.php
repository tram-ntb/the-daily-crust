<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kết quả dự báo do script Python ghi vào bảng production_forecasts (Laravel chỉ đọc). */
class ProductionForecast extends Model
{
    protected $table = 'production_forecasts';

    protected $primaryKey = 'forecast_id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'datetime',
            'created_at' => 'datetime',
            'predicted_qty' => 'integer',
            'confidence' => 'float',
        ];
    }
}
