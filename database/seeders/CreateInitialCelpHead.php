<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CreateInitialCelpHead extends Seeder
{
    /**
     * Run the database seed.
     */
    public function run(): void
    {
        if (User::where('role', 'celp_head')->exists()) {
            $this->command->info('A CELP Head account already exists. Skipping.');
            return;
        }

        $email = 'celphead@hnu.edu.ph';
        $password = config('services.initial_celp_head_password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set INITIAL_CELP_HEAD_PASSWORD in the server environment before running this seeder.');
        }

        User::create([
            'username' => 'celphead',
            'name' => 'CELP Head',
            'first_name' => 'CELP',
            'middle_name' => null,
            'last_name' => 'Head',
            'suffix' => null,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'celp_head',
            'created_by' => null,
        ]);

        $this->command->info("CELP Head account created: {$email}");
    }
}
