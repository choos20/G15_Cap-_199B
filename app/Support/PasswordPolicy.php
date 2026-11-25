<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password as PasswordRule;

final class PasswordPolicy
{
    public const MIN_LENGTH = 16;
    public const MAX_LENGTH = 64;

    /**
     * The rules used whenever a user chooses a new password.
     *
     * The Password rule handles Unicode-aware length, mixed case, numbers,
     * and symbols. The additional regex keeps whitespace by itself from
     * satisfying the symbol requirement, while still allowing passphrases.
     * Leading and trailing whitespace is rejected to avoid invisible typos.
     */
    public static function rules(): array
    {
        return [
            'required',
            PasswordRule::min(self::MIN_LENGTH)
                ->max(self::MAX_LENGTH)
                ->mixedCase()
                ->numbers()
                ->symbols(),
            'regex:/[^\pL\pN\s]/u',
            'not_regex:/^\s|\s$/u',
            'confirmed',
        ];
    }

    public static function messages(): array
    {
        return [
            'password.min' => 'Password must be at least '.self::MIN_LENGTH.' characters.',
            'password.max' => 'Password must not exceed '.self::MAX_LENGTH.' characters.',
            'password.mixed' => 'Password must include at least one uppercase and one lowercase letter.',
            'password.numbers' => 'Password must include at least one number.',
            'password.symbols' => 'Password must include at least one special character.',
            'password.regex' => 'Password must include at least one special character other than whitespace.',
            'password.not_regex' => 'Password cannot begin or end with whitespace.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
