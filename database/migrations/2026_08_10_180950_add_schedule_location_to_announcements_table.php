<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dateTime('event_date')->nullable()->after('application_deadline');
            $table->string('event_location')->nullable()->after('event_date');
            $table->decimal('latitude', 10, 7)->nullable()->after('event_location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['event_date', 'event_location', 'latitude', 'longitude']);
        });
    }
};
