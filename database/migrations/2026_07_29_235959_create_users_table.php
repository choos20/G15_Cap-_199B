<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This table was originally created outside the migrations folder (its
     * creation migration was lost at some point in the project's history —
     * every other migration only ever ALTERs `users`). Recreated here with
     * just the base Laravel-default columns; every other column (username,
     * role, organization fields, etc.) is added by the later migrations
     * that already exist for them, so this must not duplicate those.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
