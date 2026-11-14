<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->binary('profile_photo_data')->nullable();
            $table->string('profile_photo_mime', 100)->nullable();
            $table->binary('org_logo_data')->nullable();
            $table->string('org_logo_mime', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_data',
                'profile_photo_mime',
                'org_logo_data',
                'org_logo_mime',
            ]);
        });
    }
};
