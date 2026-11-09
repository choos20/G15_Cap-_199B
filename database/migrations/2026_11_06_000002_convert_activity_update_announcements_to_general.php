<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('announcements')
            ->where('category', 'Activity update')
            ->update([
                'category' => 'General',
                'slots_needed' => null,
                'application_deadline' => null,
                'event_date' => null,
                'event_location' => null,
                'latitude' => null,
                'longitude' => null,
            ]);
    }

    public function down(): void
    {
    }
};
