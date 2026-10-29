<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thêm giờ dự kiến giao / sẵn sàng cho đơn online (đơn cũ và đơn POS để trống). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'estimated_delivery_at')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->dateTime('estimated_delivery_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'estimated_delivery_at')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->dropColumn('estimated_delivery_at');
            });
        }
    }
};
