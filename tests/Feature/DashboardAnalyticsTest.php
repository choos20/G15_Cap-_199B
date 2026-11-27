<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DashboardFixtures;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use DashboardFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDashboardSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function dashboardRoutes(): array
    {
        return ['celp_secretary' => 'celp.secretary.dashboard', 'coordinator' => 'celp.coordinator.dashboard',
            'celp_head' => 'celp.head.dashboard', 'csa_director' => 'csa.director.dashboard',
            'student_org' => 'student_org.dashboard', 'college_department' => 'student_org.dashboard', 'participant' => 'participant.dashboard'];
    }

    public function test_all_role_dashboards_render_with_their_existing_account_scoped_metrics(): void
    {
        $users = $this->seedDashboardFixtures();
        foreach ($this->dashboardRoutes() as $role => $name) {
            $response = $this->actingAs($users[$role])->get(route($name))->assertOk();
            preg_match('/<script[^>]*data-analytics-config[^>]*>(.*?)<\/script>/s', $response->getContent(), $match);
            $config = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
            $dataset = $response->viewData(match ($role) {
                'celp_secretary', 'coordinator', 'csa_director' => 'reviewTrend',
                'participant' => 'participationMonths',
                default => 'projectTrend',
            });
            foreach (collect($dataset)->values() as $index => $month) {
                foreach ($month as $key => $value) {
                    $this->assertSame($value, $config['months'][$index][$key], $role.' '.$key);
                }
                $this->assertSame(now()->startOfMonth()->subMonths(5 - $index)->format('Y-m'), $config['months'][$index]['period']);
            }
            $response->assertSee('data-analytics-state="loading"', false)->assertSee('View data table');
            if (in_array($role, ['student_org', 'college_department'])) {
                $this->assertSame($role === 'student_org' ? 9 : 4, $response->viewData('totalProposals'));
                $this->assertSame($response->viewData('totalProposals'), $response->viewData('workflowTotal'));
                $response->assertDontSee('Other Organization proposal')->assertSee('data-analytics-pipeline', false);
            }
            if ($role === 'participant') {
                $this->assertSame(4, $response->viewData('totalAttendedActivities'));
                $this->assertSame(5, $response->viewData('totalApplications'));
                $response->assertSee('4 activities attended over the last six months.');
            }
        }
    }

    public function test_dashboard_role_boundaries_and_guest_redirects_are_preserved(): void
    {
        foreach (array_unique($this->dashboardRoutes()) as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
        $users = $this->seedDashboardFixtures();
        foreach ($users as $role => $user) {
            foreach (array_unique($this->dashboardRoutes()) as $name) {
                $allowed = $name === $this->dashboardRoutes()[$role];
                $this->actingAs($user)->get(route($name))->assertStatus($allowed ? 200 : 403);
            }
            if ($role !== 'celp_head') {
                $this->actingAs($user)->get(route('projects.all', ['status_group' => 'approved', 'initiators' => 1]))->assertForbidden();
            }
            if (! in_array($role, ['student_org', 'college_department'])) {
                $this->actingAs($user)->get(route('student_org.proposals', ['from' => '2026-11']))->assertForbidden();
            }
        }
    }

    public function test_organization_drill_downs_only_load_the_signed_in_organizations_records(): void
    {
        $users = $this->seedDashboardFixtures();
        foreach (['student_org', 'college_department'] as $role) {
            $response = $this->actingAs($users[$role])->get(route('student_org.proposals', [
                'status_group' => 'returned', 'from' => '2026-10', 'to' => '2026-11',
                'created_by' => $users['celp_head']->id,
            ]))->assertOk()->assertDontSee('Other Organization proposal');
            $this->assertSame($role === 'student_org' ? 9 : 4, $response->viewData('projects')->count());
            $this->assertTrue($response->viewData('projects')->every(fn ($project) => $project->created_by === $users[$role]->id));
        }
    }

    public function test_head_status_drill_downs_match_the_original_dashboard_definitions(): void
    {
        $users = $this->seedDashboardFixtures();
        $dashboard = $this->actingAs($users['celp_head'])->get(route('celp.head.dashboard'))->assertOk();
        foreach (['approved' => 'approvedCount', 'pending' => 'pendingCount', 'returned' => 'returnedCount'] as $group => $metric) {
            $response = $this->get(route('projects.all', ['status_group' => $group]))->assertOk();
            $this->assertSame($dashboard->viewData($metric), $response->viewData('projects')->total());
        }
        $this->assertSame(Project::count(), $this->get(route('projects.all'))->assertOk()->viewData('projects')->total());
    }

    public function test_head_month_drill_downs_keep_the_existing_initiator_scope_and_date_boundaries(): void
    {
        $users = $this->seedDashboardFixtures();
        Project::create(['title' => 'Staff project excluded from initiator chart', 'created_by' => $users['celp_head']->id, 'status' => 'submitted']);
        $dashboard = $this->actingAs($users['celp_head'])->get(route('celp.head.dashboard'))->assertOk();
        foreach ($dashboard->viewData('projectTrend') as $index => $month) {
            $period = now()->startOfMonth()->subMonths(5 - $index)->format('Y-m');
            $response = $this->get(route('projects.all', ['initiators' => 1, 'from' => $period, 'to' => $period]))->assertOk();
            $this->assertSame($month['approved'] + $month['pending'] + $month['returned'], $response->viewData('projects')->total());
            $this->assertTrue($response->viewData('projects')->every(fn ($project) => in_array($project->creator->role, ['student_org', 'college_department'], true) && $project->created_at->format('Y-m') === $period));
        }
        $this->getJson(route('projects.all', ['from' => '2026-12', 'to' => '2026-11']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('projects.all', ['from' => '2026-13']))->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson(route('projects.all', ['status_group' => 'confidential']))->assertUnprocessable();
    }

    public function test_participant_counts_still_deduplicate_sessions_and_isolate_other_participants(): void
    {
        $users = $this->seedDashboardFixtures();
        $existing = AttendanceSession::first();
        $duplicate = AttendanceSession::create(['project_id' => $existing->project_id, 'qr_token' => Str::random(32), 'opens_at' => $existing->opens_at]);
        AttendanceRecord::create(['session_id' => $duplicate->id, 'user_id' => $users['participant']->id, 'check_in_at' => $existing->opens_at]);
        $other = User::factory()->create(['role' => 'participant']);
        AttendanceRecord::create(['session_id' => $duplicate->id, 'user_id' => $other->id, 'check_in_at' => $existing->opens_at]);
        foreach (['Student', 'Employee', 'Alumni'] as $type) {
            $users['participant']->update(['participant_type' => $type]);
            $response = $this->actingAs($users['participant'])->get(route('participant.dashboard'))->assertOk();
            $this->assertSame(4, $response->viewData('totalAttendedActivities'));
            $this->assertSame(5, $response->viewData('totalApplications'));
            $this->assertSame(1, $response->viewData('upcomingCount'));
            $response->assertDontSee('data-analytics-radial', false)->assertDontSee('data-analytics-pipeline', false);
        }
    }

    public function test_empty_dashboards_show_true_zero_totals_without_positive_radial_arcs(): void
    {
        $users = $this->seedDashboardFixtures();
        foreach (['audit_logs', 'attendance_records', 'attendance_sessions', 'volunteer_applications', 'announcements', 'projects'] as $table) {
            DB::table($table)->delete();
        }
        foreach ($this->dashboardRoutes() as $role => $name) {
            $response = $this->actingAs($users[$role])->get(route($name))->assertOk();
            preg_match('/<script[^>]*data-analytics-config[^>]*>(.*?)<\/script>/s', $response->getContent(), $match);
            $config = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
            foreach ($config['months'] as $month) {
                foreach ($config['series'] as $series) {
                    $this->assertSame(0, $month[$series['key']]);
                }
            }
            $response->assertDontSee('class="pl-ring-value"', false);
            if ($role !== 'participant') {
                $response->assertSee('y="124" data-count-up="0"', false)->assertSee('recorded yet.');
            }
        }
    }
}
