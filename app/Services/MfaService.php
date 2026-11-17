<?php

namespace App\Services;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class MfaService
{
    public const PROTECTED_ROLES = [
        'celp_head',
        'csa_director',
    ];

    public static function isProtectedUser(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::PROTECTED_ROLES, true);
    }

    /**
     * Persist the encrypted TOTP secret and replace any previous recovery
     * codes. Plain recovery codes are returned once for the enrollment screen;
     * only their hashes are stored in the database.
     *
     * @return array<int, string>
     */
    public static function enable(User $user, string $secret): array
    {
        return DB::transaction(function () use ($user, $secret): array {
            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_enabled_at' => now(),
            ])->save();

            MfaRecoveryCode::where('user_id', $user->id)->delete();

            $codes = [];
            for ($index = 0; $index < 10; $index++) {
                $raw = strtoupper(bin2hex(random_bytes(6)));
                $display = implode('-', str_split($raw, 4));

                MfaRecoveryCode::create([
                    'user_id' => $user->id,
                    'code_hash' => Hash::make($raw),
                ]);

                $codes[] = $display;
            }

            return $codes;
        });
    }

    public static function consumeRecoveryCode(User $user, string $submittedCode): bool
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $submittedCode));
        if ($normalized === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $normalized): bool {
            $codes = MfaRecoveryCode::where('user_id', $user->id)
                ->whereNull('used_at')
                ->lockForUpdate()
                ->get();

            foreach ($codes as $code) {
                if (! Hash::check($normalized, $code->code_hash)) {
                    continue;
                }

                $code->forceFill(['used_at' => now()])->save();

                return true;
            }

            return false;
        });
    }
}
