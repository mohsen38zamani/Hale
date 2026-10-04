<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            // Deterministic WebP variant (web/{id}.webp) built on demand for
            // screen-sized delivery; null until the first web request.
            $table->string('web_path')->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn('web_path');
        });
    }
};
