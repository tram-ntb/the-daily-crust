<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chuẩn bị CSDL cho Phân hệ 1 (kho + sản xuất đầu ngày):
 *  1) Các khóa chính của bảng ghi nhận phát sinh trong dump là INT NOT NULL nhưng KHÔNG tự tăng
 *     => Eloquent không thể create(). Bật AUTO_INCREMENT cho chúng.
 *  2) Thêm cột "Staff xác nhận kế hoạch" và "ghi chú lỗi" của mẻ bánh.
 *
 * Nếu migration báo lỗi (ví dụ chưa cài gói/phiên bản không hỗ trợ ->change()), chạy tay trong phpMyAdmin:
 *   ALTER TABLE production_batches MODIFY batch_id INT NOT NULL AUTO_INCREMENT;  (lặp lại cho các bảng ở $autoIncrement)
 *   ALTER TABLE production_plans ADD acknowledged_by INT NULL, ADD acknowledged_at DATETIME NULL;
 *   ALTER TABLE production_batches ADD failure_note VARCHAR(255) NULL;
 */
return new class extends Migration
{
    /** bảng => cột khóa chính */
    private array $autoIncrement = [
        'production_batches' => 'batch_id',
        'product_lots' => 'lot_id',
        'product_stock_movements' => 'movement_id',
        'product_stock_transactions' => 'transaction_id',
        'ingredient_stock_transactions' => 'transaction_id',
        'inventory_waste' => 'ticket_id',
        'stocktake_sessions' => 'stocktake_id',
        'stocktake_details' => 'stocktake_detail_id',
        'shortage_explanations' => 'explanation_id',
    ];

    /** Bật AUTO_INCREMENT cho khóa chính; bỏ qua nếu cột đã tự tăng (chạy lại an toàn). */
    private function makeAutoIncrement(string $table, string $pk): void
    {
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
        // MySQL lỗi 1833 nếu sửa cột khóa chính đang được khóa ngoại tham chiếu khi kiểm tra khóa ngoại còn bật.
        // Việc thêm AUTO_INCREMENT KHÔNG đổi kiểu dữ liệu nên an toàn: tắt kiểm tra tạm thời rồi bật lại.
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($this->autoIncrement as $table => $pk) {
                $this->makeAutoIncrement($table, $pk);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Schema::table('production_plans', function (Blueprint $t) {
            if (! Schema::hasColumn('production_plans', 'acknowledged_by')) {
                $t->integer('acknowledged_by')->nullable();
            }
            if (! Schema::hasColumn('production_plans', 'acknowledged_at')) {
                $t->dateTime('acknowledged_at')->nullable();
            }
        });

        Schema::table('production_batches', function (Blueprint $t) {
            if (! Schema::hasColumn('production_batches', 'failure_note')) {
                $t->string('failure_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', fn (Blueprint $t) => $t->dropColumn('failure_note'));
        Schema::table('production_plans', fn (Blueprint $t) => $t->dropColumn(['acknowledged_by', 'acknowledged_at']));
        // Không hạ AUTO_INCREMENT: vô hại và đảo ngược không cần thiết.
    }
};
