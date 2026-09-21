<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('manual_retry_count')->default(0)->after('feedback');
            $table->timestamp('processing_lease_expires_at')->nullable()->after('manual_retry_count');
            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('ai_daily_budgets', function (Blueprint $table): void {
            $table->id();
            $table->date('budget_date')->unique();
            $table->decimal('reserved_usd', 12, 6)->default(0);
            $table->decimal('spent_usd', 12, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('ai_budget_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('ai_daily_budget_id')->constrained()->cascadeOnDelete();
            $table->decimal('estimated_usd', 12, 6);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_budget_reservations');
        Schema::dropIfExists('ai_daily_budgets');
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'type', 'created_at']);
            $table->dropColumn('manual_retry_count');
            $table->dropColumn('processing_lease_expires_at');
        });
    }
};
