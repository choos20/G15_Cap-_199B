<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentOrgController;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentOrgDashboardMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('role')->default('participant');
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('created_by');
            $table->string('status')->default('submitted');
            $table->timestamps();
        });
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('file_path');
            $table->foreignId('uploaded_by');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_both_organization_roles_receive_accurate_account_scoped_chart_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        $studentOrg = User::factory()->create(['role' => 'student_org']);
        $collegeDepartment = User::factory()->create(['role' => 'college_department']);

        $this->createProject($studentOrg, 'endorsed_by_secretary', '2026-09-10 10:00:00');
        $this->createProject($studentOrg, 'csa_cleared', '2026-09-12 10:00:00');
        $this->createProject($studentOrg, 'returned_by_csa', '2026-09-14 10:00:00');
        $this->createProject($studentOrg, 'submitted', '2026-03-10 10:00:00');
        $this->createProject($collegeDepartment, 'endorsed_by_coordinator', '2026-09-16 10:00:00');
        $this->createProject($collegeDepartment, 'returned_by_coordinator', '2026-09-18 10:00:00');

        $studentOrgData = $this->dashboardData($studentOrg);
        $studentOrgStages = collect($studentOrgData['workflowStages'])->keyBy('key');
        $studentOrgSeptember = collect($studentOrgData['projectTrend'])->firstWhere('label', 'Sep');

        $this->assertSame(4, $studentOrgData['totalProposals']);
        $this->assertSame(1, $studentOrgStages['secretary']['count']);
        $this->assertSame(1, $studentOrgStages['approved']['count']);
        $this->assertSame(1, $studentOrgStages['returned']['count']);
        $this->assertSame(1, $studentOrgSeptember['approved']);
        $this->assertSame(1, $studentOrgSeptember['pending']);
        $this->assertSame(1, $studentOrgSeptember['returned']);

        $collegeDepartmentData = $this->dashboardData($collegeDepartment);
        $collegeDepartmentSeptember = collect($collegeDepartmentData['projectTrend'])->firstWhere('label', 'Sep');

        $this->assertSame(2, $collegeDepartmentData['totalProposals']);
        $this->assertSame(1, $collegeDepartmentSeptember['pending']);
        $this->assertSame(1, $collegeDepartmentSeptember['returned']);
    }

    private function dashboardData(User $user): array
    {
        $request = Request::create('/student-org');
        $request->setUserResolver(fn () => $user);

        return app(StudentOrgController::class)->index($request)->getData();
    }

    private function createProject(User $owner, string $status, string $createdAt): void
    {
        $project = Project::create([
            'title' => 'Test proposal '.$status,
            'created_by' => $owner->id,
            'status' => $status,
        ]);
        $project->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->saveQuietly();
    }
}
