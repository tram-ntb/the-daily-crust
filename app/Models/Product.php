<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $primaryKey = 'product_id';

    public $timestamps = false; // Hoặc true tùy cấu trúc bảng sản phẩm của bạn

    protected $fillable = [
        'item', 'vi_name', 'price', 'category', 'image_url',
        'expiry_duration_day', 'status', 'description',
    ];
}
