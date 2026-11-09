<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rating criteria printed on the beneficiary evaluation form.
     *
     * Stored as rows rather than a constant so a criterion can be retired
     * without destroying the scores already recorded against it: retiring
     * flips is_active, which hides it from new forms while past responses
     * keep pointing at the row.
     */
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            // Full wording as it appears on the paper form.
            $table->string('label');
            // Condensed wording for the summary chart, where space is tight.
            $table->string('short_label', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        DB::table('evaluation_criteria')->insert([
            [
                'label' => 'Relevance of the activity to the needs of the community',
                'short_label' => 'Relevance to community needs',
                'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'label' => 'Organization and management of the activity',
                'short_label' => 'Organization and management',
                'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'label' => 'Competence and conduct of the facilitators',
                'short_label' => 'Competence of the facilitators',
                'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'label' => 'Adequacy of the materials and resources provided',
                'short_label' => 'Adequacy of materials and resources',
                'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'label' => 'Overall benefit gained from the activity',
                'short_label' => 'Overall benefit gained',
                'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
