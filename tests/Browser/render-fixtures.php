<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\Feature\DashboardAnalyticsTest;

// Always use a disposable in-memory database, even when application config is cached.
foreach (['APP_CONFIG_CACHE' => sys_get_temp_dir().'/analytics-config-'.bin2hex(random_bytes(8)).'.php', 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1:8021', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$root = dirname(__DIR__, 2);
$outputDirectory = $argv[1] ?? sys_get_temp_dir().'/projectlink-analytics';
if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0700, true);
}
$version = $argv[2] ?? 'after';
if (! in_array($version, ['before', 'after'], true)) {
    throw new InvalidArgumentException('Choose before or after.');
}
require $root.'/vendor/autoload.php';
chdir($root);
class AnalyticsBrowserFixture extends DashboardAnalyticsTest
{
    public function generate(string $version, string $directory): void
    {
        $this->setUp();
        $users = $this->seedDashboardFixtures();
        URL::forceRootUrl('http://127.0.0.1:8021');
        $metrics = [];
        foreach ($this->dashboardRoutes() as $role => $routeName) {
            $response = $this->actingAs($users[$role])->get(route($routeName));
            if ($response->status() !== 200) {
                throw new RuntimeException($role.' dashboard failed: '.$response->status());
            }
            file_put_contents($directory.'/'.$version.'-'.$role.'.html', $response->getContent());
            foreach (['projectTrend', 'reviewTrend', 'workflowStages', 'workflowTotal', 'totalProposals', 'projectsInReview', 'projectsReturned', 'approvedActivities', 'approvedCount', 'pendingCount', 'returnedCount', 'totalProjects', 'totalUsers', 'totalVolunteers', 'projectSubmissionTrend', 'accountCreationTrend', 'participationMonths', 'totalAttendedActivities', 'organizationsSupported', 'totalApplications', 'upcomingCount'] as $key) {
                if (array_key_exists($key, $response->original->getData())) {
                    $metrics[$role][$key] = $response->viewData($key);
                }
            }
            if (in_array($role, ['student_org', 'college_department'])) {
                file_put_contents($directory.'/'.$version.'-'.$role.'-proposals.html', $this->get(route('student_org.proposals'))->getContent());
            }
            $queue = ['celp_secretary' => 'projects.secretary_review', 'coordinator' => 'projects.coordinator_review', 'celp_head' => 'projects.celp_head_review', 'csa_director' => 'projects.csa_review'][$role] ?? null;
            if ($queue) {
                file_put_contents($directory.'/'.$version.'-'.$role.'-queue.html', $this->get(route($queue))->getContent());
            }
            if ($role === 'celp_head') {
                file_put_contents($directory.'/'.$version.'-'.$role.'-all.html', $this->get(route('projects.all'))->getContent());
            }
        }
        file_put_contents($directory.'/'.$version.'-metrics.json', json_encode($metrics, JSON_PRETTY_PRINT));
        foreach (['audit_logs', 'attendance_records', 'attendance_sessions', 'volunteer_applications', 'announcements', 'projects'] as $table) {
            DB::table($table)->delete();
        }
        foreach ($this->dashboardRoutes() as $role => $routeName) {
            $response = $this->actingAs($users[$role])->get(route($routeName));
            if ($response->status() !== 200) {
                throw new RuntimeException($role.' empty dashboard failed.');
            }
            file_put_contents($directory.'/'.$version.'-empty-'.$role.'.html', $response->getContent());
        }
        $this->app['db']->disconnect();
        echo 'Generated '.$version.' fixtures for all seven dashboard roles using isolated SQLite data.'.PHP_EOL;
    }
}
(new AnalyticsBrowserFixture('test_all_role_dashboards_render_with_their_existing_account_scoped_metrics'))->generate($version, $outputDirectory);
