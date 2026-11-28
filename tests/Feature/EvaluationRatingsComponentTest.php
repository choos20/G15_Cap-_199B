<?php

namespace Tests\Feature;

use Tests\TestCase;

class EvaluationRatingsComponentTest extends TestCase
{
    public function test_fractional_ratings_empty_values_and_official_scale_are_presented_without_reclassification(): void
    {
        $html = view('components.evaluation-ratings', [
            'items' => collect([
                ['label' => 'Relevance', 'respondent_type' => 'beneficiary', 'average' => 2.71, 'count' => 7],
                ['label' => 'Delivery', 'respondent_type' => 'beneficiary', 'average' => 4.71, 'count' => 7],
                ['label' => 'Missing', 'respondent_type' => 'beneficiary', 'average' => null],
            ]),
            'overall' => ['Beneficiary average' => 3.29],
        ])->render();

        $this->assertStringContainsString('--rating-width:54.2%', $html);
        $this->assertStringContainsString('--rating-width:94.2%', $html);
        $this->assertStringContainsString('2.71', $html);
        $this->assertStringContainsString('4.71', $html);
        $this->assertStringContainsString('3.29 / 5.00', $html);
        $this->assertStringContainsString('7 ratings', $html);
        $this->assertStringContainsString('No ratings', $html);
        $this->assertStringContainsString('Highest', $html);
        $this->assertStringContainsString('Lowest', $html);
        $this->assertStringContainsString('1 Poor', $html);
        $this->assertStringContainsString('5 Excellent', $html);
    }
}
