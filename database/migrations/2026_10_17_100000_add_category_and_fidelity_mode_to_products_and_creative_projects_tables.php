<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('category', 30)->nullable()->after('status');
        });

        Schema::table('creative_projects', function (Blueprint $table): void {
            $table->string('category', 30)->nullable()->after('environment');
            $table->string('fidelity_mode', 40)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('category');
        });

        Schema::table('creative_projects', function (Blueprint $table): void {
            $table->dropColumn(['category', 'fidelity_mode']);
        });
    }
};
