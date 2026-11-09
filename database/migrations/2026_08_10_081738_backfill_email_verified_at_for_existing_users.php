<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts created before email verification existed never went through
     * an OTP challenge. Treat them as already trusted so this feature doesn't
     * lock out every existing user (self-registration and admin-created
     * accounts alike) the moment it ships.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Not reversible: we can't tell which rows were genuinely
        // verified vs backfilled after the fact.
    }
};
