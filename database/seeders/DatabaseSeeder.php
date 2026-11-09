<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Ace User', 'email' => 'ace@hnu.edu.ph'],
            ['name' => 'Ritchie Napinas', 'email' => 'napinasritchiebob@hnu.edu.ph'],
            ['name' => 'Maria Santos', 'email' => 'maria.santos@hnu.edu.ph'],
            ['name' => 'Juan dela Cruz', 'email' => 'juan.delacruz@hnu.edu.ph'],
            ['name' => 'Anna Reyes', 'email' => 'anna.reyes@hnu.edu.ph'],
            ['name' => 'Carl Adonis', 'email' => 'carl.adonis@hnu.edu.ph'],
            ['name' => 'Leah Cruz', 'email' => 'leah.cruz@hnu.edu.ph'],
        ];

        foreach ($users as $user) {
            User::factory()->create($user);
        }
    }   
}
