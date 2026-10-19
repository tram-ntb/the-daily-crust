<?php

namespace App\Models;

/** Nhà cung cấp (bảng `suppliers`). */
class Supplier extends BakeryModel
{
    protected $table = 'suppliers';

    protected $primaryKey = 'supplier_id';
}
