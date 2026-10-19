<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chuẩn bị CSDL cho Phân hệ 2 (POS & Bán hàng):
 *  1) Khóa chính của orders / order_details / order_detail_lots / payments / delivery_orders
 *     trong dump là INT NOT NULL nhưng KHÔNG tự tăng => bật AUTO_INCREMENT để Eloquent create() được.
 *  2) Thêm cột orders.status (New / Preparing / Ready / Completed) cho Worklist đơn online.
 *     Đơn lịch sử tự nhận giá trị mặc định 'Completed'.
 *
 * Nếu migration lỗi, chạy tay trong phpMyAdmin:
 *   ALTER TABLE orders MODIFY order_id INT NOT NULL AUTO_INCREMENT;            (tương tự các bảng còn lại)
 *   ALTER TABLE orders ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Completed';
 */
return new class extends Migration
{
    private array $autoIncrement = [
        'orders' => 'order_id',
        'order_details' => 'detail_id',
        'order_detail_lots' => 'id',
        'payments' => 'payment_id',
        'delivery_orders' => 'delivery_id',
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

        if (! Schema::hasColumn('orders', 'status')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->string('status', 20)->default('Completed');
                $t->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['status']);
            $t->dropColumn('status');
        });
    }
};
