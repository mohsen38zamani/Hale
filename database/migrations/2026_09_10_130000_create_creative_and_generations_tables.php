<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('goal', 30);
            $table->string('style', 30);
            $table->string('format', 30);
            $table->string('environment', 30)->nullable();
            $table->unsignedTinyInteger('video_duration_seconds')->nullable();
            $table->json('settings')->nullable();
            $table->json('brief');
            $table->text('prompt');
            $table->timestamps();
        });
        Schema::create('generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creative_project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('status', 20)->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->char('prompt_hash', 64);
            $table->unsignedInteger('credits_reserved')->default(0);
            $table->unsignedInteger('credits_charged')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->foreignId('output_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->string('feedback', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
        Schema::dropIfExists('creative_projects');
    }
};
