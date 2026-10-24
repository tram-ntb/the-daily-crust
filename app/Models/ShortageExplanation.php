<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Biên bản giải trình chênh lệch kiểm kê (bảng `shortage_explanations`). */
class ShortageExplanation extends BakeryModel
{
    /** Ghi chú đã được lập bằng mẫu biên bản bắt đầu bằng "[nguyên nhân]". */
    public const REPORT_PREFIX = '[';

    protected $table = 'shortage_explanations';

    protected $primaryKey = 'explanation_id';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(StocktakeDetail::class, 'stocktake_detail_id', 'stocktake_detail_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function isReportFilled(): bool
    {
        return str_starts_with((string) $this->explanation_note, self::REPORT_PREFIX);
    }
}
