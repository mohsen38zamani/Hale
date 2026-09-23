<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts created before mandatory email verification existed are
     * grandfathered in so existing users are not locked out of generation.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNotNull('email')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Data backfill is not reversible.
    }
};
