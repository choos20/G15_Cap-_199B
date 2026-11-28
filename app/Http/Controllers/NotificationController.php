<?php

namespace App\Http\Controllers;

use App\Support\NotificationSourceResolver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'filter' => ['nullable', 'in:all,unread,read'],
            'search' => ['nullable', 'string', 'max:100'],
            'sender' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'in:all,today,7,30,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $filter = $filters['filter'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $advanced = in_array($request->user()->role, ['student_org', 'college_department'], true);
        $sender = $advanced ? ($filters['sender'] ?? 'all') : 'all';
        $category = $advanced ? ($filters['category'] ?? 'all') : 'all';
        $date = $advanced ? ($filters['date'] ?? 'all') : 'all';
        $query = $request->user()->notifications();
        $driver = $query->getModel()->getConnection()->getDriverName();
        $json = static fn (string $key) => match ($driver) {
            'pgsql' => "(data::jsonb)->>'{$key}'",
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$key}'))",
            'sqlsrv' => "JSON_VALUE(data, '$.{$key}')",
            default => "json_extract(data, '$.{$key}')",
        };
        $actorExpr = 'CAST('.$json('actor_id').' AS '.($driver === 'mysql' || $driver === 'mariadb' ? 'CHAR' : 'TEXT').')';
        $roleExpr = $json('actor_role');
        $categoryExpr = $json('category');
        $senderOptions = collect();
        $categoryOptions = collect();
        $actors = collect();
        if ($advanced) {
            $metadata = $request->user()->notifications()->reorder()->selectRaw("DISTINCT {$actorExpr} as actor_id, {$roleExpr} as actor_role, {$categoryExpr} as category")->get();
            $actors = User::query()->whereIn('id', $metadata->pluck('actor_id')->filter()->unique())->get()->keyBy('id');
            $senderOptions = $metadata->map(fn ($row) => $row->actor_role ?: $actors->get($row->actor_id)?->role ?: 'unknown')->unique()->sort()->values();
            $categoryOptions = $metadata->map(fn ($row) => $row->category ?: 'uncategorized')->unique()->sort()->values();
            if ($sender !== 'all') {
                $matchingActors = $actors->filter(fn ($actor) => $actor->role === $sender)->keys()->map(fn ($id) => (string) $id)->all();
                $knownActors = $actors->keys()->map(fn ($id) => (string) $id)->all();
                $query->where(function ($q) use ($sender, $roleExpr, $actorExpr, $matchingActors, $knownActors) {
                    if ($sender === 'unknown') {
                        $q->whereRaw("({$roleExpr} IS NULL OR {$roleExpr} = '')")
                            ->where(function ($q) use ($actorExpr, $knownActors) {
                                $q->whereRaw("{$actorExpr} IS NULL");
                                if ($knownActors) $q->orWhereNotIn(\DB::raw($actorExpr), $knownActors);
                                else $q->orWhereRaw('1 = 1');
                            });
                    } else {
                        $q->whereRaw("{$roleExpr} = ?", [$sender]);
                        if ($matchingActors) $q->orWhere(function ($q) use ($roleExpr, $actorExpr, $matchingActors) {
                            $q->whereRaw("({$roleExpr} IS NULL OR {$roleExpr} = '')")->whereIn(\DB::raw($actorExpr), $matchingActors);
                        });
                    }
                });
            }
            if ($category !== 'all') {
                $category === 'uncategorized'
                    ? $query->whereRaw("({$categoryExpr} IS NULL OR {$categoryExpr} = '')")
                    : $query->whereRaw("{$categoryExpr} = ?", [$category]);
            }
            if ($date === 'today') $query->where('created_at', '>=', now()->startOfDay());
            if ($date === '7' || $date === '30') $query->where('created_at', '>=', now()->subDays((int) $date - 1)->startOfDay());
            if ($date === 'custom') {
                if (! empty($filters['from'])) $query->where('created_at', '>=', \Carbon\Carbon::parse($filters['from'])->startOfDay());
                if (! empty($filters['to'])) $query->where('created_at', '<=', \Carbon\Carbon::parse($filters['to'])->endOfDay());
            }
        }

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($search !== '') {
            $driver = $query->getModel()->getConnection()->getDriverName();
            [$titleExpression, $bodyExpression] = match ($driver) {
                'pgsql' => [
                    "LOWER((data::jsonb)->>'title')",
                    "LOWER((data::jsonb)->>'body')",
                ],
                'mysql', 'mariadb' => [
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.title')))",
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.body')))",
                ],
                'sqlsrv' => [
                    "LOWER(JSON_VALUE(data, '$.title'))",
                    "LOWER(JSON_VALUE(data, '$.body'))",
                ],
                default => [
                    "LOWER(json_extract(data, '$.title'))",
                    "LOWER(json_extract(data, '$.body'))",
                ],
            };
            $searchTerm = '%'.mb_strtolower($search).'%';

            $matchingActors = $advanced ? $actors->filter(fn ($actor) => str_contains(mb_strtolower($actor->name.' '.($actor->organization_name ?? '')), mb_strtolower($search)))->keys()->map(fn ($id) => (string) $id)->all() : [];
            $projectIds = $advanced && Schema::hasTable('projects') ? \App\Models\Project::query()->whereRaw('LOWER(title) LIKE ?', [$searchTerm])->pluck('id')->map(fn ($id) => (string) $id)->all() : [];
            $query->where(function ($query) use ($titleExpression, $bodyExpression, $searchTerm, $matchingActors, $projectIds, $actorExpr, $json) {
                $query->whereRaw("{$titleExpression} LIKE ?", [$searchTerm])
                    ->orWhereRaw("{$bodyExpression} LIKE ?", [$searchTerm]);
                if ($matchingActors) $query->orWhereIn(\DB::raw($actorExpr), $matchingActors);
                if ($projectIds) $query->orWhereIn(\DB::raw('CAST('.$json('project_id').' AS '.(\DB::connection()->getDriverName() === 'mysql' ? 'CHAR' : 'TEXT').')'), $projectIds);
            });
        }

        $notifications = $query->latest()->paginate(20)->withQueryString();

        return view('notifications.index', [
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'hasNotifications' => $request->user()->notifications()->exists(),
            'notifications' => $notifications,
            'notificationSources' => NotificationSourceResolver::for($notifications->getCollection()),
            'filter' => $filter,
            'search' => $search,
            'advanced' => $advanced, 'sender' => $sender, 'category' => $category, 'date' => $date,
            'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? '',
            'senderOptions' => $senderOptions, 'categoryOptions' => $categoryOptions,
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;
        $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        $allowedHosts = array_filter([
            $request->getHost(),
            '127.0.0.1',
            'localhost',
            '::1',
        ]);

        // Older notification rows may contain absolute development URLs such as
        // http://127.0.0.1:8000/... . Redirect to their app-relative path so the
        // browser stays on the deployed host. Reject external or malformed URLs.
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')
            || ($host !== null && ! in_array($host, $allowedHosts, true))) {
            return redirect()->route('dashboard');
        }

        $target = $path;
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }
        if (is_string($fragment) && $fragment !== '') {
            $target .= '#'.$fragment;
        }

        return redirect()->to($target);
    }

    public function markAllRead(Request $request)
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($updated === 0) {
            return back()->with('status', $request->user()->notifications()->exists()
                ? 'All notifications are already read.'
                : 'No notifications to mark as read.');
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
