<?php

namespace Tests\Unit;

use App\Models\Template;
use PHPUnit\Framework\TestCase;

class TemplateSourceTest extends TestCase
{
    public function test_source_is_derived_from_the_publishing_office_role(): void
    {
        self::assertSame('csa', Template::sourceForRole('csa_director'));
        self::assertSame('celp', Template::sourceForRole('celp_head'));
        self::assertSame('celp', Template::sourceForRole('coordinator'));
        self::assertSame('celp', Template::sourceForRole('celp_secretary'));
        self::assertNull(Template::sourceForRole('participant'));
    }

    public function test_source_labels_are_human_readable_and_unknown_is_explicit(): void
    {
        self::assertSame('CSA', (new Template(['source' => 'csa']))->sourceLabel());
        self::assertSame('CELP', (new Template(['source' => 'celp']))->sourceLabel());
        self::assertSame('Source unavailable', (new Template())->sourceLabel());
    }
}
