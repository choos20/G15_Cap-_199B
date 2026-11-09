<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('attendance_source', 20)->default('qr')->after('status');
            $table->string('check_in_status', 20)->nullable()->after('attendance_source');
            $table->string('check_out_status', 20)->nullable()->after('check_in_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['attendance_source', 'check_in_status', 'check_out_status']);
        });
    }
};
