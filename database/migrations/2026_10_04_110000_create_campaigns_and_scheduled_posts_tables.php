<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('starts_at');
            $table->unsignedTinyInteger('interval_days')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->date('scheduled_at');
            $table->string('status', 20)->default('scheduled');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
        Schema::dropIfExists('campaigns');
    }
};
