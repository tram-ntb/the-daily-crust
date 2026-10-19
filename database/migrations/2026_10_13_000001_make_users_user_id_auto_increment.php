<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bật AUTO_INCREMENT cho users.user_id NẾU cột chưa tự tăng (dump hiện tại thường đã tự tăng sẵn).
     *
     * Giữ kiểu INT có dấu (signed), KHÔNG đổi sang unsigned: các bảng khác (production_plans.approved_by,
     * orders.created_by, ...) tham chiếu user_id bằng INT có dấu, đổi kiểu sẽ làm lệch khóa ngoại.
     */
    public function up(): void
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'user_id');

        if (! $column || ($column['auto_increment'] ?? false)) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::table('users', function (Blueprint $table): void {
            $table->integer('user_id', true)->change();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Không hoàn tác: bỏ AUTO_INCREMENT của khóa chính không mang lại lợi ích và có thể gây lỗi.
    }
};
