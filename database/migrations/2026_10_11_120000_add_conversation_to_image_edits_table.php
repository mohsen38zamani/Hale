<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversational editing adds no thread table: an edit whose source
        // is another edit is a reply, and walking those links recovers the
        // conversation. What the user actually typed is the only new fact
        // that needs a column of its own - it is content the thread renders,
        // not an operation parameter like the ones already in `options`.
        Schema::table('image_edits', function (Blueprint $table) {
            $table->text('message')->nullable()->after('options');
            $table->index(['user_id', 'source_type', 'source_id', 'status'], 'image_edits_thread_index');
        });
    }

    public function down(): void
    {
        Schema::table('image_edits', function (Blueprint $table) {
            $table->dropIndex('image_edits_thread_index');
            $table->dropColumn('message');
        });
    }
};
