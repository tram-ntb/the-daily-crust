<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chuẩn hóa kiểu dữ liệu (MySQL):
 *  - purchase_requisitions.approved_at : DECIMAL(15,2) -> DATETIME
 *  - production_batches.start_time/end_time : VARCHAR(255) -> TIME
 *
 * Vì sao TIME (không phải DATETIME) cho start_time/end_time: dữ liệu chỉ là giờ trong ngày ('HH:MM:SS'),
 * ngày đã nằm ở production_batches.batch_date. TIME giữ nguyên định dạng chuỗi 'HH:MM:SS' khi Laravel đọc ra,
 * nên code hiện có (ProductionController ghi 'H:i:00', view hiển thị chuỗi giờ) không cần sửa.
 *
 * Migration kiểm tra kiểu cột hiện tại nên chạy lại nhiều lần không làm mất dữ liệu.
 */
return new class extends Migration
{
    private function columnType(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT DATA_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $row ? strtolower((string) $row->t) : null;
    }

    public function up(): void
    {
        // 1) approved_at: giá trị số cũ không mang ý nghĩa thời gian -> xóa rồi đổi kiểu
        if ($this->columnType('purchase_requisitions', 'approved_at') === 'decimal') {
            DB::statement('UPDATE purchase_requisitions SET approved_at = NULL');
            DB::statement('ALTER TABLE purchase_requisitions MODIFY approved_at DATETIME NULL');
        }

        // 2) start_time / end_time: chuỗi hợp lệ 'HH:MM' hoặc 'HH:MM:SS' được giữ, chuỗi lạ chuyển NULL
        foreach (['start_time', 'end_time'] as $col) {
            if ($this->columnType('production_batches', $col) === 'varchar') {
                DB::statement("UPDATE production_batches SET {$col} = NULL WHERE {$col} IS NOT NULL AND {$col} NOT REGEXP '^[0-9]{2}:[0-9]{2}(:[0-9]{2})?$'");
                DB::statement("ALTER TABLE production_batches MODIFY {$col} TIME NULL");
            }
        }
    }

    public function down(): void
    {
        if ($this->columnType('purchase_requisitions', 'approved_at') === 'datetime') {
            DB::statement('UPDATE purchase_requisitions SET approved_at = NULL');
            DB::statement('ALTER TABLE purchase_requisitions MODIFY approved_at DECIMAL(15,2) NULL');
        }
        foreach (['start_time', 'end_time'] as $col) {
            if ($this->columnType('production_batches', $col) === 'time') {
                DB::statement("ALTER TABLE production_batches MODIFY {$col} VARCHAR(255) NULL");
            }
        }
    }
};
