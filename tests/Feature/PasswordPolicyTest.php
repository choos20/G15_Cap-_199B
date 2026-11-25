<?php

namespace Tests\Feature;

use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    public function test_a_valid_password_meets_the_strict_policy(): void
    {
        $password = 'ProjectLink-2026!';

        $this->assertTrue($this->validator($password, $password)->passes());
    }

    public function test_passwords_must_be_at_least_sixteen_characters(): void
    {
        $password = 'Short1!';

        $this->assertTrue($this->validator($password, $password)->fails());
    }

    public function test_passwords_must_not_exceed_sixty_four_characters(): void
    {
        $password = 'A1!'.str_repeat('a', 62);

        $this->assertSame(65, strlen($password));
        $this->assertTrue($this->validator($password, $password)->fails());
    }

    public function test_passwords_require_all_character_categories(): void
    {
        $missingSymbol = 'ProjectLink2026A';
        $missingNumber = 'ProjectLink!Secure';
        $missingMixedCase = 'projectlinksecure-2026!';

        $this->assertTrue($this->validator($missingSymbol, $missingSymbol)->fails());
        $this->assertTrue($this->validator($missingNumber, $missingNumber)->fails());
        $this->assertTrue($this->validator($missingMixedCase, $missingMixedCase)->fails());
    }

    public function test_passwords_cannot_start_or_end_with_whitespace(): void
    {
        $leadingSpace = ' ProjectLink-2026!';
        $trailingSpace = 'ProjectLink-2026! ';

        $this->assertTrue($this->validator($leadingSpace, $leadingSpace)->fails());
        $this->assertTrue($this->validator($trailingSpace, $trailingSpace)->fails());
    }

    public function test_password_confirmation_is_required_to_match(): void
    {
        $this->assertTrue($this->validator('ProjectLink-2026!', 'ProjectLink-2026?')->fails());
    }

    private function validator(string $password, string $confirmation): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => PasswordPolicy::rules()],
            PasswordPolicy::messages()
        );
    }
}
