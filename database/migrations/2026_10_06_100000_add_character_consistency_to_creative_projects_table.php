<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_projects', function (Blueprint $table): void {
            $table->string('character_consistency', 40)->nullable()->after('lighting_setup');
        });
    }

    public function down(): void
    {
        Schema::table('creative_projects', function (Blueprint $table): void {
            $table->dropColumn('character_consistency');
        });
    }
};
