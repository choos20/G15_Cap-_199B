<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Academic year and term, derived from dates.
 *
 * The schema has no academic_year/term columns, so periods are computed from
 * existing timestamps rather than stored. The Philippine academic year runs
 * August through July, which means a single calendar year spans two AYs and
 * the cutoff month is what matters.
 *
 * If AY/term ever become real columns, only this class needs to change.
 */
class AcademicPeriod
{
    /** Month the academic year starts (August). */
    public const START_MONTH = 8;

    public const TERMS = [
        'first' => '1st Semester',
        'second' => '2nd Semester',
        'summer' => 'Summer',
    ];

    /**
     * Academic year label for a date, e.g. "2025-2026".
     * Anything before August belongs to the AY that began the previous year.
     */
    public static function labelFor(Carbon|CarbonImmutable|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $d = $date instanceof Carbon || $date instanceof CarbonImmutable
            ? $date
            : Carbon::parse($date);

        $startYear = $d->month >= self::START_MONTH ? $d->year : $d->year - 1;

        return $startYear . '-' . ($startYear + 1);
    }

    /**
     * Term key for a date: Aug-Dec first, Jan-May second, Jun-Jul summer.
     */
    public static function termFor(Carbon|CarbonImmutable|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $d = $date instanceof Carbon || $date instanceof CarbonImmutable
            ? $date
            : Carbon::parse($date);

        return match (true) {
            $d->month >= 8 && $d->month <= 12 => 'first',
            $d->month >= 1 && $d->month <= 5 => 'second',
            default => 'summer',
        };
    }

    public static function termLabel(?string $key): ?string
    {
        return $key ? (self::TERMS[$key] ?? null) : null;
    }

    /**
     * Inclusive start/end datetimes for an AY label, optionally narrowed to one
     * term. Returns null when the label isn't a well-formed "YYYY-YYYY".
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function range(?string $ayLabel, ?string $term = null): ?array
    {
        if (!$ayLabel || !preg_match('/^(\d{4})-(\d{4})$/', $ayLabel, $m)) {
            return null;
        }

        $startYear = (int) $m[1];

        // Whole academic year: 1 Aug -> 31 Jul the following year.
        $start = CarbonImmutable::create($startYear, self::START_MONTH, 1)->startOfDay();
        $end = $start->addYear()->subDay()->endOfDay();

        if ($term === 'first') {
            $start = CarbonImmutable::create($startYear, 8, 1)->startOfDay();
            $end = CarbonImmutable::create($startYear, 12, 31)->endOfDay();
        } elseif ($term === 'second') {
            $start = CarbonImmutable::create($startYear + 1, 1, 1)->startOfDay();
            $end = CarbonImmutable::create($startYear + 1, 5, 31)->endOfDay();
        } elseif ($term === 'summer') {
            $start = CarbonImmutable::create($startYear + 1, 6, 1)->startOfDay();
            $end = CarbonImmutable::create($startYear + 1, 7, 31)->endOfDay();
        }

        return [$start, $end];
    }

    /**
     * The AY label immediately before the given one, for period-over-period
     * comparison. Null when the input isn't parseable.
     */
    public static function previousLabel(?string $ayLabel): ?string
    {
        if (!$ayLabel || !preg_match('/^(\d{4})-(\d{4})$/', $ayLabel, $m)) {
            return null;
        }

        $startYear = (int) $m[1] - 1;

        return $startYear . '-' . ($startYear + 1);
    }

    /**
     * The AY containing today — the sensible default selection.
     */
    public static function current(): string
    {
        return self::labelFor(Carbon::now());
    }

    /**
     * Every AY label covered by the given dates, newest first, always including
     * the current one so the filter is never empty on a fresh install.
     *
     * @param iterable<Carbon|CarbonImmutable|string|null> $dates
     * @return array<int, string>
     */
    public static function labelsFrom(iterable $dates): array
    {
        $labels = [self::current()];

        foreach ($dates as $d) {
            if ($label = self::labelFor($d)) {
                $labels[] = $label;
            }
        }

        $labels = array_values(array_unique($labels));
        rsort($labels);

        return $labels;
    }
}
