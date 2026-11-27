<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;

class ProjectDocumentAccess
{
    public function canReview(User $user, Project $project): bool
    {
        // CELP Head's existing "All projects" oversight remains available.
        if ($user->role === 'celp_head') {
            return true;
        }

        $stages = match ($user->role) {
            'celp_secretary' => array_keys(Project::STATUS_LABELS),
            'coordinator' => [
                'endorsed_by_secretary', 'endorsed_by_coordinator', 'approved', 'csa_cleared',
                'returned_by_coordinator', 'returned_by_celp_head', 'returned_by_csa',
            ],
            'csa_director' => ['approved', 'csa_cleared', 'returned_by_csa'],
            default => [],
        };

        return in_array($project->status, $stages, true);
    }
}
