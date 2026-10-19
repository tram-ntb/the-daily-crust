<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - Database đã được import sẵn từ file SQL (bảng `users` đã có dữ liệu) nên mỗi bảng chỉ được tạo khi CHƯA tồn tại.
     * - Khóa chính/unique dạng chuỗi dùng độ dài 191 và engine InnoDB để không vượt giới hạn độ dài khóa
     *   của MySQL (lỗi 1071 "Specified key was too long; max key length is 1000 bytes").
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('user_id');
                $table->string('username', 191)->unique();
                $table->string('full_name');
                $table->string('email', 191)->unique();
                $table->string('phone', 20);
                $table->string('password');
                $table->string('role')->default('customer');
                $table->string('position')->nullable();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->string('email', 191)->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->string('id', 191)->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * KHÔNG xóa bảng `users` vì nó chứa dữ liệu import (tài khoản quản lý, nhân viên, khách hàng).
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
