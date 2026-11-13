<?php

namespace App\Providers;

use App\Models\Project;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', function (Request $request): array {
            $username = Str::transliterate(Str::lower((string) $request->input('user', '')));
            $usernameKey = hash('sha256', $username);

            return [
                Limit::perMinute(5)->by('login:'.$usernameKey.'|'.$request->ip()),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });

        View::composer('layouts.admin', function ($view) {
            $returnedNotificationCount = 0;
            $navUnreadCount = 0;
            $navRecentNotifications = collect();

            if (Auth::check()) {
                if (in_array(Auth::user()->role, ['student_org', 'college_department'])) {
                    $query = Project::where('created_by', Auth::id())
                        ->whereIn('status', ['returned_by_secretary', 'returned_by_coordinator', 'returned_by_celp_head', 'returned_by_csa']);

                    if (Schema::hasColumn('projects', 'notification_seen_at')) {
                        $query->whereNull('notification_seen_at');
                    }

                    $returnedNotificationCount = $query->count();
                }

                $navUnreadCount = Auth::user()->unreadNotifications()->count();
                $navRecentNotifications = Auth::user()->notifications()->latest()->take(8)->get();
            }

            $view->with([
                'returnedNotificationCount' => $returnedNotificationCount,
                'navUnreadCount' => $navUnreadCount,
                'navRecentNotifications' => $navRecentNotifications,
            ]);
        });
    }
}
