<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('group', 50)->default('general')->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed initial default USD to Toman rate
        DB::table('system_settings')->insert([
            'key' => 'usd_to_toman_rate',
            'value' => '100000',
            'type' => 'integer',
            'group' => 'financial',
            'description' => 'نرخ تبدیل دلار به تومان برای محاسبه بهای تمام‌شده و سود ناخالص',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
