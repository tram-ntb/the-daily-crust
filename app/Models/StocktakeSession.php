<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Phiên kiểm kê kho (bảng `stocktake_sessions`). */
class StocktakeSession extends BakeryModel
{
    public const IN_PROGRESS = 'In_Progress';

    public const PENDING_APPROVAL = 'Pending_Approval';

    public const COMPLETED = 'Completed';

    protected $table = 'stocktake_sessions';

    protected $primaryKey = 'stocktake_id';

    protected function casts(): array
    {
        return [
            'stocktake_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(StocktakeDetail::class, 'stocktake_id', 'stocktake_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }
}
