<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 191)->nullable()->change();
            $table->string('phone', 20)->nullable()->change();
            $table->string('email', 191)->nullable()->change();
        });

        $indexes = collect(Schema::getIndexes('users'))->pluck('name');

        Schema::table('users', function (Blueprint $table) use ($indexes): void {
            if (! $indexes->contains('users_username_unique')) {
                $table->unique('username', 'users_username_unique');
            }

            if (! $indexes->contains('users_email_unique')) {
                $table->unique('email', 'users_email_unique');
            }
        });
    }

    public function down(): void
    {
        // Do not convert credential and phone values back to integers.
    }
};
