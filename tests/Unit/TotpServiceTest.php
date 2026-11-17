<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    /**
     * RFC 6238 SHA-1 test vectors prove the implementation is compatible with
     * standard authenticator apps, including SafeAuth-style TOTP clients.
     */
    public function test_rfc_6238_sha1_vectors_are_accepted(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $vectors = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
            20000000000 => '353130',
        ];

        foreach ($vectors as $timestamp => $code) {
            $this->assertTrue(TotpService::verify($secret, $code, $timestamp, 0));
        }
    }

    public function test_invalid_totp_code_is_rejected(): void
    {
        $this->assertFalse(TotpService::verify(
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            '000000',
            59,
            0
        ));
    }
}
