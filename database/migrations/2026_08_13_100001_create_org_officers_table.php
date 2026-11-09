<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Officers listed on an organization's About Us profile. These are roster
     * entries, not system accounts — an officer usually has no login, so this
     * deliberately stores plain names rather than pointing at users.
     */
    public function up(): void
    {
        Schema::create('org_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('position');
            $table->string('email')->nullable();
            // Manual display order, so President comes before Secretary.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_officers');
    }
};
