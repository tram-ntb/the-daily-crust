<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lớp cha cho các bảng của CSDL The Daily Crust:
 * không có created_at/updated_at chuẩn Laravel và dữ liệu luôn được kiểm tra (validate) trước khi lưu.
 */
abstract class BakeryModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
