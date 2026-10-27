<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * production_forecasts lưu cả hai nhóm dự báo:
 *  - item_group = 'Production': sản xuất tại bếp, predicted_qty = sản lượng MỘT ngày (horizon_days = 1)
 *  - item_group = 'Commercial': hàng nhập từ NCC, predicted_qty = tổng nhu cầu horizon_days (7) ngày tới
 * Dòng cũ (trước migration) mặc định là 'Production'. Chạy lại nhiều lần an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_forecasts')) {
            return;
        }

        Schema::table('production_forecasts', function (Blueprint $t) {
            if (! Schema::hasColumn('production_forecasts', 'item_group')) {
                $t->string('item_group', 20)->default('Production');
            }
            if (! Schema::hasColumn('production_forecasts', 'horizon_days')) {
                $t->unsignedSmallInteger('horizon_days')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_forecasts', function (Blueprint $t) {
            if (Schema::hasColumn('production_forecasts', 'horizon_days')) {
                $t->dropColumn('horizon_days');
            }
            if (Schema::hasColumn('production_forecasts', 'item_group')) {
                $t->dropColumn('item_group');
            }
        });
    }
};
