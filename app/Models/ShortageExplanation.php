<?php

namespace App\Models;

/** Biên bản giải trình chênh lệch kiểm kê (bảng `shortage_explanations`). */
class ShortageExplanation extends BakeryModel
{
    protected $table = 'shortage_explanations';

    protected $primaryKey = 'explanation_id';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
