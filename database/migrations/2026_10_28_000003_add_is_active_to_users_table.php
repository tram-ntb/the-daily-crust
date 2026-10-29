<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cờ khóa / mở khóa tài khoản (Quản lý khóa tài khoản nhân viên thay vì xóa, vì tài khoản đã gắn với đơn hàng, mẻ nướng...). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $t) {
                $t->boolean('is_active')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('is_active');
            });
        }
    }
};
