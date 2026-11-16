<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'filter' => ['nullable', 'in:all,unread,read'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $filter = $filters['filter'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $query = $request->user()->notifications();

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

            $query->where(function ($query) use ($titleExpression, $bodyExpression, $searchTerm) {
                $query->whereRaw("{$titleExpression} LIKE ?", [$searchTerm])
                    ->orWhereRaw("{$bodyExpression} LIKE ?", [$searchTerm]);
            });
        }

        $notifications = $query->latest()->paginate(20)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'search' => $search,
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
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
