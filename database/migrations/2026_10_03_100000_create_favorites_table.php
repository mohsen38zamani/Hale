<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('favoritable_type', 100);
            $table->unsignedBigInteger('favoritable_id');
            $table->timestamps();

            // One favorite per user per target; also serves user+type lookups.
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'favorites_user_type_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
