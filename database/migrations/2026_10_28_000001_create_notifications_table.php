<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng `notifications` chuẩn của Laravel (Database Notifications).
 * - Ép engine InnoDB và rút ngắn `notifiable_type` để khóa chỉ mục không vượt giới hạn 1000 byte
 *   (lỗi 1071 khi MySQL/MariaDB tạo bảng bằng engine mặc định không phải InnoDB).
 * - Chạy lại an toàn: nếu có bảng dở dang từ lần migrate lỗi trước (chưa có thông báo nào) thì tạo lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            if (DB::table('notifications')->exists()) {
                return;                                   // đã có dữ liệu thật: giữ nguyên
            }
            Schema::drop('notifications');                // bảng rỗng (có thể thiếu chỉ mục do lần lỗi trước)
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->string('type', 191);
            $table->string('notifiable_type', 100);       // 'App\Models\User'
            $table->unsignedBigInteger('notifiable_id');  // users.user_id
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
