<?php

namespace Database\Seeders;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProposalSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (! $user) {
            return;
        }

        Proposal::create([
            'title' => 'Community Clean-up in Barangay X',
            'description' => 'A volunteer-driven clean-up of public spaces.',
            'applicant_id' => $user->id,
            'status' => 'submitted',
        ]);

        Proposal::create([
            'title' => 'Literacy Program for Adults',
            'description' => 'Weekly literacy classes for adult learners.',
            'applicant_id' => $user->id,
            'status' => 'endorsed_by_secretary',
        ]);
    }
}
