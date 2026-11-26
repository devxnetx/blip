<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ShowLength
{
    public static function startsAtSofiaMidnight(Carbon $startsAt): bool
    {
        return $startsAt->copy()->timezone('Europe/Sofia')->format('H:i:s') === '00:00:00';
    }

    /**
     * A date with no clock is stored from midnight to the end of that day
     * (1439 or 1440 minutes). That is not a real end. The show lasts 3 hours.
     */
    public static function shortenPlaceholder(Carbon $startsAt, Carbon $endsAt): Carbon
    {
        if (! self::startsAtSofiaMidnight($startsAt) || ! $endsAt->greaterThan($startsAt)) {
            return $endsAt;
        }

        $minutes = (int) round(($endsAt->getTimestamp() - $startsAt->getTimestamp()) / 60);

        if ($minutes < 1439 || $minutes > 1440) {
            return $endsAt;
        }

        return $startsAt->copy()->addHours(3);
    }
}
