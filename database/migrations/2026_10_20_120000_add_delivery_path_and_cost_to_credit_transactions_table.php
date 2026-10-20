<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->string('delivery_path', 32)->default('in_app_generation')->index()->after('type');
            $table->decimal('internal_cost_usd', 8, 4)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->dropIndex(['delivery_path']);
            $table->dropColumn(['delivery_path', 'internal_cost_usd']);
        });
    }
};
