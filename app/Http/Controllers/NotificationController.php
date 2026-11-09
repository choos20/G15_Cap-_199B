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

        $url = $notification->data['url'] ?? route('dashboard');

        return redirect($url);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
