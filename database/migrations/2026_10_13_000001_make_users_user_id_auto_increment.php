<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('users', function (Blueprint $table): void {
            $table->increments('user_id')->change();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('users', function (Blueprint $table): void {
            $table->integer('user_id')->change();
        });

        Schema::enableForeignKeyConstraints();
    }
};
