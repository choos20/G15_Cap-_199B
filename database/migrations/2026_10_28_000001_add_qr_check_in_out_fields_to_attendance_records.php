<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->decimal('check_in_distance_meters', 8, 2)->nullable();
            $table->decimal('check_out_distance_meters', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_at',
                'check_out_at',
                'check_in_lat',
                'check_in_lng',
                'check_out_lat',
                'check_out_lng',
                'check_in_distance_meters',
                'check_out_distance_meters',
            ]);
        });
    }
};
