<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->string('respondent_type', 30)->default('beneficiary');
        });

        Schema::table('evaluation_responses', function (Blueprint $table) {
            $table->string('respondent_type', 30)->default('beneficiary');
            $table->string('volunteer_interest', 10)->nullable();
            $table->json('heard_about_sources')->nullable();
        });

        $legacyCriteria = [
            'Relevance to community needs',
            'Organization and management',
            'Competence of the facilitators',
            'Adequacy of materials and resources',
            'Overall benefit gained',
        ];
        DB::table('evaluation_criteria')
            ->whereIn('short_label', $legacyCriteria)
            ->update(['is_active' => false]);

        $criteria = [
            'beneficiary' => [
                ['Quality of services provided', 'Quality of services'],
                ['Relevance and impact of the activity', 'Relevance and impact'],
                ['Communication and coordination', 'Communication and coordination'],
                ['Interaction with organizers and volunteers', 'Organizer interaction'],
                ['Impact on your well-being', 'Impact on well-being'],
            ],
            'implementer' => [
                ['Overall planning and preparation', 'Planning and preparation'],
                ['Relevance and impact of the activity', 'Relevance and impact'],
                ['Communication and coordination', 'Communication and coordination'],
                ['Engagement and participation', 'Engagement and participation'],
                ["Effectiveness of the organization's contributions", 'Organization contribution'],
            ],
        ];

        $now = now();
        foreach ($criteria as $respondentType => $items) {
            foreach ($items as $index => [$label, $shortLabel]) {
                DB::table('evaluation_criteria')->insert([
                    'label' => $label,
                    'short_label' => $shortLabel,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'respondent_type' => $respondentType,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::create('evaluation_custom_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('respondent_type', 30);
            $table->string('question', 255);
            $table->string('question_type', 30);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['evaluation_id', 'respondent_type', 'sort_order']);
        });

        Schema::create('evaluation_custom_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('evaluation_responses')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('evaluation_custom_questions')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->timestamps();

            $table->unique(['response_id', 'question_id']);
        });
    }

    public function down(): void
    {
        $legacyCriteria = [
            'Relevance to community needs',
            'Organization and management',
            'Competence of the facilitators',
            'Adequacy of materials and resources',
            'Overall benefit gained',
        ];
        $newCriteria = [
            'Quality of services',
            'Relevance and impact',
            'Communication and coordination',
            'Organizer interaction',
            'Impact on well-being',
            'Planning and preparation',
            'Engagement and participation',
            'Organization contribution',
        ];
        $usedNewCriteria = DB::table('evaluation_response_ratings as ratings')
            ->join('evaluation_criteria as criteria', 'criteria.id', '=', 'ratings.criterion_id')
            ->whereIn('criteria.short_label', $newCriteria)
            ->exists();
        if ($usedNewCriteria) {
            throw new \RuntimeException('Cannot roll back respondent evaluation forms after responses have been recorded.');
        }

        Schema::dropIfExists('evaluation_custom_answers');
        Schema::dropIfExists('evaluation_custom_questions');

        Schema::table('evaluation_responses', function (Blueprint $table) {
            $table->dropColumn(['respondent_type', 'volunteer_interest', 'heard_about_sources']);
        });

        DB::table('evaluation_criteria')->whereIn('short_label', $newCriteria)->delete();

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropColumn('respondent_type');
        });

        DB::table('evaluation_criteria')->whereIn('short_label', $legacyCriteria)->update(['is_active' => true]);
    }
};
