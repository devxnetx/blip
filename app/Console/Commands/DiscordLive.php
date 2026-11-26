<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Discord\CityNotifier;
use Illuminate\Console\Command;

class DiscordLive extends Command
{
    protected $signature = 'pins:discord-live {city?}';

    protected $description = 'Post the events running now and starting soon in each city Discord channel';

    public function handle(CityNotifier $discord): int
    {
        if (! $discord->enabled()) {
            $this->warn('Discord is not configured.');

            return self::SUCCESS;
        }

        $slug = $this->argument('city');
        $slug = is_string($slug) ? trim($slug) : null;
        $named = $slug !== null && $slug !== '';

        if (! $named && ! $this->due()) {
            $this->line('SKIP hour');

            return self::SUCCESS;
        }

        $rows = $discord->liveByCity($named ? $slug : null);

        if ($named && $rows === []) {
            $this->error('Unknown city.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($rows as $row) {
            $count = $row['running']->count() + $row['upcoming']->count();

            if ($row['upcoming']->isEmpty()) {
                $this->line('SKIP '.$row['city']['name']);
                continue;
            }

            $ok = $discord->postLive($row['city'], $row['running'], $row['upcoming']);
            $this->line(($ok ? 'POSTED ' : 'FAIL ').$row['city']['name'].' '.$count);

            if (! $ok) {
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function due(): bool
    {
        $hour = (int) now('Europe/Sofia')->format('G');

        return $hour % Setting::discordEveryHours() === 0;
    }
}
