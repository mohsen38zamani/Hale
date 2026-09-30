<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_projects', function (Blueprint $table): void {
            $table->string('surface', 40)->nullable()->after('environment');
            $table->string('props', 40)->nullable()->after('surface');
            $table->string('camera_angle', 40)->nullable()->after('props');
            $table->string('lighting_setup', 40)->nullable()->after('camera_angle');
        });
    }

    public function down(): void
    {
        Schema::table('creative_projects', function (Blueprint $table): void {
            $table->dropColumn(['surface', 'props', 'camera_angle', 'lighting_setup']);
        });
    }
};
