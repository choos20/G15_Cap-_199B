<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the single, first CELP Head (System Administrator) account.
 *
 * This is the ONLY account this seeder creates. Every other role
 * (Coordinator, CELP Secretary, CSA Director, Org President, etc.)
 * is meant to be created afterward BY this CELP Head account through
 * the "Create a new account" flow already in your dashboard
 * (route: users.create) — not through seeding.
 *
 * Run with:
 *   php artisan db:seed --class=CelpHeadSeeder
 */
class CelpHeadSeeder extends Seeder
{
    public function run(): void
    {
        // Guard: never create a second CELP Head by accident.
        if (User::where('role_type', 'celp_head')->exists()) {
            $this->command->warn('A CELP Head account already exists. Skipping.');
            return;
        }

        User::create([
            // Human-readable prefixed PK, matches your U0001-style convention.
            'user_id'             => 'U0001',

            // --- Change these before running in production ---
            'username'            => 'celphead',
            'email'               => 'celphead@hnu.edu.ph', // use the real institutional email
            'password_hash'       => Hash::make('ChangeMe!2026Secure'), // temp password, force change on first login

            'role_type'           => 'celp_head', // must exist in your role_type ENUM
            'first_name'          => 'CELP',
            'middle_name'         => null,
            'last_name'           => 'Head',
            'suffix'              => null,
            'contact_number'      => null,

            'year_level'          => 'n/a',   // non-student account
            'department'          => 'Center for Community Extension, Linkages, and Partnerships',
            'organization_id'     => null,    // CELP Head is not tied to a student org

            'status'              => 'active',
            'login_attempts'      => 0,
            'last_login_at'       => null,
            'password_updated_at' => now(),
            'avatar_url'          => null,
        ]);

        $this->command->info('CELP Head account created (username: celphead). Change the temp password immediately.');
    }
}