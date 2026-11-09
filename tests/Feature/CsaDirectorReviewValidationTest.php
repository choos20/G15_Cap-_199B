<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CsaDirectorReviewValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by');
            $table->string('status');
            $table->text('review_comments')->nullable();
            $table->timestamps();
        });
    }

    public function test_returning_a_proposal_requires_a_reason(): void
    {
        $directorId = DB::table('users')->insertGetId([
            'name' => 'CSA Director',
            'email' => 'director@example.test',
            'password' => bcrypt('password'),
            'role' => 'csa_director',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $organizationId = DB::table('users')->insertGetId([
            'name' => 'Student Organization',
            'email' => 'organization@example.test',
            'password' => bcrypt('password'),
            'role' => 'student_org',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'title' => 'Community Activity',
            'created_by' => $organizationId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::findOrFail($directorId))
            ->post(route('projects.csa_review.update', $projectId), ['action' => 'return'])
            ->assertSessionHasErrors('comments');

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'status' => 'approved',
            'review_comments' => null,
        ]);
    }
}
