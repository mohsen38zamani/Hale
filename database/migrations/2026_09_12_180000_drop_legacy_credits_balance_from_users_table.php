<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'credits_balance')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('credits_balance');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'credits_balance')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedInteger('credits_balance')->default(0)->after('phone_verified_at');
            });
        }
    }
};
