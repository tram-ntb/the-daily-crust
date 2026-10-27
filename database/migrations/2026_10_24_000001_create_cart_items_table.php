<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giỏ hàng lưu trong CSDL, gắn với tài khoản (user_id) để đồng bộ giữa mọi trình duyệt và thiết bị.
 *
 * Mỗi dòng = (user_id, product_id) duy nhất, kèm số lượng.
 * user_id / product_id dùng INT có dấu để khớp kiểu với users.user_id và products.product_id trong dump.
 * Khóa ngoại được thêm ở bước riêng và bỏ qua nếu CSDL từ chối (không làm hỏng migration).
 * Chạy lại nhiều lần an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->id('cart_item_id');
                $t->integer('user_id');
                $t->integer('product_id');
                $t->unsignedSmallInteger('quantity')->default(1);
                $t->timestamps();

                $t->unique(['user_id', 'product_id'], 'cart_items_user_product_unique');
            });
        }

        try {
            Schema::table('cart_items', function (Blueprint $t) {
                $t->foreign('user_id', 'fk_cart_user')->references('user_id')->on('users')->cascadeOnDelete();
                $t->foreign('product_id', 'fk_cart_product')->references('product_id')->on('products')->cascadeOnDelete();
            });
        } catch (\Throwable $e) {
            // Đã có khóa ngoại từ lần chạy trước, hoặc kiểu cột khác nhau: bỏ qua, giỏ hàng vẫn hoạt động đầy đủ.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
