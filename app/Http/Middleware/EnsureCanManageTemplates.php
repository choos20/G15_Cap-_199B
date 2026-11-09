<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the template write endpoints (upload, replace, remove).
 *
 * Applied on the route rather than inside each handler, so a new endpoint added
 * to the group is protected by default instead of relying on someone
 * remembering to repeat the check. Hiding the buttons is not enforcement — this
 * is what stops a direct POST from a signed-in initiator or participant.
 */
class EnsureCanManageTemplates
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canManageTemplates()) {
            abort(403, 'Only CELP personnel and the CSA Director may upload templates.');
        }

        return $next($request);
    }
}
