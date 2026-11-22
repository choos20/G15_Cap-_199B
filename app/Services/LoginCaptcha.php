<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LoginCaptcha
{
    public function configured(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public function verify(Request $request): bool
    {
        $token = $request->input('cf-turnstile-response');
        if (! $this->configured() || ! is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->connectTimeout(3)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            return $response->successful()
                && $response->json('success') === true
                && $response->json('action') === 'login'
                && $response->json('hostname') === $request->getHost();
        } catch (ConnectionException $exception) {
            return false;
        }
    }
}
