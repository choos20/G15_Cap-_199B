<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app never relies on the database enforcing this list (statuses are
     * assigned as plain strings in the controller), so a portable string
     * column is simpler and safer across engines than juggling native ENUMs.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('status')->default('valid')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('status')->default('valid')->change();
        });
    }
};
