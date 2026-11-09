<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff-created accounts now receive a generated temporary password by
     * email instead of one typed by whoever created them. This flag forces the
     * holder to replace it at first login, so the creator never keeps working
     * knowledge of someone else's credentials.
     *
     * Existing accounts are left alone — their passwords were chosen by their
     * owners (self-registration) or are already in use, and forcing a reset on
     * everyone would lock people out for no gain.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
