<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->decimal('scanned_lat', 10, 7)->nullable();
            $table->decimal('scanned_lng', 10, 7)->nullable();
            $table->decimal('distance_meters', 8, 2)->nullable();
            $table->enum('status', ['valid', 'out_of_range', 'expired', 'duplicate'])->default('valid');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_records');
    }
};
