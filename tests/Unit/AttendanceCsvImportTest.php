<?php

namespace Tests\Unit;

use App\Http\Controllers\AttendanceController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttendanceCsvImportTest extends TestCase
{
    #[DataProvider('attendanceStatusCases')]
    public function test_csv_attendance_status_uses_both_stages(?string $checkIn, ?string $checkOut, string $expected): void
    {
        self::assertSame($expected, $this->invoke('attendanceStatus', $checkIn, $checkOut));
    }

    public static function attendanceStatusCases(): array
    {
        return [
            'both stages completed' => ['Present', 'Present', 'completed'],
            'checked in but no check out' => ['Present', 'Absent', 'attended'],
            'absent at both stages' => ['Absent', 'Absent', 'absent'],
            'missing check in' => [null, 'Present', 'partial'],
            'missing check out' => ['Present', null, 'partial'],
        ];
    }

    public function test_participant_ids_preserve_leading_zeroes_and_ignore_case(): void
    {
        self::assertSame('00123', $this->invoke('normalizeParticipantId', '00123'));
        self::assertSame('ABC-42', $this->invoke('normalizeParticipantId', 'abc-42'));
    }

    public function test_excel_date_formats_match_activity_date(): void
    {
        self::assertTrue($this->invoke('activityDateMatches', '18/10/2026', '2026-10-18'));
        self::assertTrue($this->invoke('activityDateMatches', '10/18/2026', '2026-10-18'));
        self::assertFalse($this->invoke('activityDateMatches', '10/19/2026', '2026-10-18'));
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionMethod(AttendanceController::class, $method);

        return $reflection->invoke(new AttendanceController, ...$arguments);
    }
}
