<?php

namespace Tests\Unit;

use App\Support\ShowLength;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ShowLengthTest extends TestCase
{
    public function test_a_midnight_listing_without_a_real_end_lasts_three_hours(): void
    {
        $start = Carbon::parse('2026-11-26 00:00:00', 'Europe/Sofia');
        $end = Carbon::parse('2026-11-26 23:59:00', 'Europe/Sofia');

        $resolved = ShowLength::shortenPlaceholder($start, $end);

        $this->assertSame('2026-11-26 03:00:00', $resolved->timezone('Europe/Sofia')->format('Y-m-d H:i:s'));
    }

    public function test_a_full_day_and_an_almost_full_day_are_the_same_placeholder(): void
    {
        $start = Carbon::parse('2026-07-15 00:00:00', 'Europe/Sofia');

        $day = ShowLength::shortenPlaceholder($start, $start->copy()->addDay());
        $almost = ShowLength::shortenPlaceholder($start, $start->copy()->addDay()->subSecond());

        $this->assertSame('2026-07-15 03:00:00', $day->timezone('Europe/Sofia')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-15 03:00:00', $almost->timezone('Europe/Sofia')->format('Y-m-d H:i:s'));
    }

    public function test_a_real_end_and_a_later_start_stay(): void
    {
        $midnight = Carbon::parse('2026-11-26 00:00:00', 'Europe/Sofia');
        $short = $midnight->copy()->addHours(2);
        $festival = $midnight->copy()->addDays(3);
        $evening = Carbon::parse('2026-11-26 20:00:00', 'Europe/Sofia');
        $eveningEnd = $evening->copy()->addDay();

        $this->assertTrue(ShowLength::shortenPlaceholder($midnight, $short)->equalTo($short));
        $this->assertTrue(ShowLength::shortenPlaceholder($midnight, $festival)->equalTo($festival));
        $this->assertTrue(ShowLength::shortenPlaceholder($evening, $eveningEnd)->equalTo($eveningEnd));
    }
}
