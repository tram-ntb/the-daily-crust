<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    public function shortageExplanations(): HasManyThrough
    {
        return $this->hasManyThrough(
            ShortageExplanation::class, StocktakeDetail::class,
            'stocktake_id', 'stocktake_detail_id', 'stocktake_id', 'stocktake_detail_id'
        );
    }

    public function isEditable(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }
}
