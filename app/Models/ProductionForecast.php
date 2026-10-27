<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kết quả dự báo do script Python ghi vào bảng production_forecasts (Laravel chỉ đọc). */
class ProductionForecast extends Model
{
    public const GROUP_PRODUCTION = 'Production';
    public const GROUP_COMMERCIAL = 'Commercial';

    protected $table = 'production_forecasts';
    protected $primaryKey = 'forecast_id';
    public $timestamps = false;
    protected $guarded = [];

    /** Dự báo SẢN XUẤT tại bếp (sản lượng mỗi ngày). Dòng cũ chưa có nhóm cũng tính là sản xuất. */
    public function scopeProduction(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where(fn ($w) => $w->whereNull('item_group')->orWhere('item_group', self::GROUP_PRODUCTION));
    }

    /** Dự báo hàng NHẬP VỀ (nhu cầu horizon_days ngày tới, mặc định 7). */
    public function scopeCommercial(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('item_group', self::GROUP_COMMERCIAL);
    }

    protected function casts(): array
    {
        return [
            'forecast_date' => 'datetime',
            'created_at'    => 'datetime',
            'predicted_qty' => 'integer',
            'horizon_days'  => 'integer',
            'confidence'    => 'float',
        ];
    }
}
