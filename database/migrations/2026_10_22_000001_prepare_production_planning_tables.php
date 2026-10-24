<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chuẩn bị CSDL cho Phân hệ 3 (BOM + lập kế hoạch sản xuất):
 * khóa chính của production_plans / bill_of_materials / production_forecasts trong dump là INT NOT NULL
 * nhưng KHÔNG tự tăng => Eloquent không thể create(). Bật AUTO_INCREMENT cho chúng.
 *
 * Migration này KHÔNG tạo bảng mới và KHÔNG thêm cột mới (Phân hệ 3 dùng lại các cột có sẵn).
 * Chạy lại nhiều lần an toàn (bỏ qua cột đã tự tăng).
 *
 * Nếu lỗi, chạy tay trong phpMyAdmin:
 *   SET FOREIGN_KEY_CHECKS = 0;
 *   ALTER TABLE production_plans   MODIFY plan_id     INT NOT NULL AUTO_INCREMENT;
 *   ALTER TABLE bill_of_materials  MODIFY bom_id      INT NOT NULL AUTO_INCREMENT;
 *   ALTER TABLE production_forecasts MODIFY forecast_id INT NOT NULL AUTO_INCREMENT;
 *   SET FOREIGN_KEY_CHECKS = 1;
 */
return new class extends Migration
{
    /** bảng => cột khóa chính */
    private array $autoIncrement = [
        'production_plans' => 'plan_id',
        'bill_of_materials' => 'bom_id',
        'production_forecasts' => 'forecast_id',
    ];

    /** Bật AUTO_INCREMENT cho khóa chính; bỏ qua nếu bảng/cột không tồn tại hoặc đã tự tăng. */
    private function makeAutoIncrement(string $table, string $pk): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $column = collect(Schema::getColumns($table))->firstWhere('name', $pk);

        if (! $column || ($column['auto_increment'] ?? false)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($pk) {
            $t->integer($pk, true)->change();
        });
    }

    public function up(): void
    {
        // Lỗi 1833 nếu sửa cột khóa chính đang được khóa ngoại tham chiếu (production_batches.plan_id -> plan_id)
        // khi kiểm tra khóa ngoại còn bật. Thêm AUTO_INCREMENT không đổi kiểu dữ liệu nên an toàn.
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($this->autoIncrement as $table => $pk) {
                $this->makeAutoIncrement($table, $pk);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Không hạ AUTO_INCREMENT: vô hại và đảo ngược không cần thiết.
    }
};
