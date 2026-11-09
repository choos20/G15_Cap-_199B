<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('attendance_records')) {
            return;
        }

        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_status_check');
    }

    public function down(): void
    {
        // Restoring the old constraint would prevent valid completed check-outs.
    }
};
