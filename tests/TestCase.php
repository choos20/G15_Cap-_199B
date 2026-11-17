<?php

namespace Tests;

use App\Models\User;
use App\Services\MfaService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Existing authorization tests use actingAs() to represent an already
     * authenticated staff session. Mark privileged fixtures as MFA-complete
     * so those tests continue to exercise their intended authorization rule;
     * MfaFlowTest covers the real login/enrollment gate separately.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User && MfaService::isProtectedUser($user)) {
            $user->setAttribute('mfa_enabled_at', now());
            $this->withSession(['mfa_verified_user_id' => $user->getAuthIdentifier()]);
        }

        return $this;
    }
}
