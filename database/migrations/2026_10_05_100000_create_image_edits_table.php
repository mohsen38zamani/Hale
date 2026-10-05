<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // generation | product — the source photo the tool operates on.
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            // remove_bg | upscale | expand | shadow
            $table->string('operation', 20);
            $table->json('options')->nullable();
            // queued | processing | completed | failed
            $table->string('status', 20)->default('queued');
            $table->text('error_message')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->double('cost_usd')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->foreignId('output_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            // Charged upfront (one-shot); refunded automatically when the
            // job fails permanently.
            $table->unsignedInteger('credits_spent')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('processing_lease_expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_edits');
    }
};
