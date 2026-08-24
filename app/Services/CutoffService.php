<?php

namespace App\Services;

use App\Models\Branch;
use Carbon\CarbonImmutable;

/**
 * The daily cut-off.
 *
 * It never blocks anything. A branch can ask for stock at any hour, as many
 * times a day as it needs - running out mid-service is the problem this app
 * exists to solve, and a form that refuses at 6:01pm would just push people
 * back to WhatsApp.
 *
 * All the cut-off does is mark a request Late, which pins it to the top of the
 * admin's list so it still gets packed.
 */
class CutoffService
{
    /** Today's cut-off moment, in the branch's own timezone. */
    public function todaysCutoff(Branch $branch, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now($branch->timezone);
        $now = $now->setTimezone($branch->timezone);

        [$hour, $minute] = array_map('intval', explode(':', substr($branch->cutoff_time, 0, 5)));

        return $now->setTime($hour, $minute, 0);
    }

    /** The next cut-off still ahead of us - today's, or tomorrow's if it has passed. */
    public function nextCutoff(Branch $branch, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now($branch->timezone);
        $now = $now->setTimezone($branch->timezone);
        $today = $this->todaysCutoff($branch, $now);

        return $now->lessThan($today) ? $today : $today->addDay();
    }

    /** Sent after today's cut-off, so it will not make tomorrow's normal run. */
    public function isLate(Branch $branch, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now($branch->timezone);
        $at = $at->setTimezone($branch->timezone);

        return $at->greaterThan($this->todaysCutoff($branch, $at));
    }

    /**
     * What the branch home screen counts down to.
     *
     * @return array{at: string, seconds_left: int, is_past: bool, time: string}
     */
    public function countdown(Branch $branch, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now($branch->timezone);
        $now = $now->setTimezone($branch->timezone);

        $today = $this->todaysCutoff($branch, $now);
        $isPast = $now->greaterThan($today);
        $target = $isPast ? $today->addDay() : $today;

        return [
            'at' => $target->toIso8601String(),
            'seconds_left' => max(0, $target->diffInSeconds($now, absolute: true)),
            'is_past' => $isPast,
            'time' => substr($branch->cutoff_time, 0, 5),
        ];
    }
}
