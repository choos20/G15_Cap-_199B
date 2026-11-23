<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_departments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('academic_departments')->restrictOnDelete();
            $table->string('code', 160)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Reuse saved college names without assigning any participant to an
        // organizer's college or inventing the college's program offerings.
        $departments = DB::table('users')->whereNotNull('affiliation')->pluck('affiliation')
            ->merge(DB::table('users')->whereNotNull('org_college')->pluck('org_college'))
            ->map(fn ($value) => trim($value))->filter()->unique(fn ($value) => mb_strtolower($value));

        foreach ($departments as $department) {
            DB::table('academic_departments')->insert([
                'code' => $department, 'name' => $department, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_programs');
        Schema::dropIfExists('academic_departments');
    }
};
