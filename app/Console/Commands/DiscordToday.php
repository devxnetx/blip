<?php

namespace App\Console\Commands;

use App\Services\Discord\CityNotifier;
use Illuminate\Console\Command;

class DiscordToday extends Command
{
    protected $signature = 'pins:discord-today {city?}';

    protected $description = 'Post today\'s event titles in each city Discord channel';

    public function handle(CityNotifier $discord): int
    {
        if (! $discord->enabled()) {
            $this->warn('Discord is not configured.');

            return self::SUCCESS;
        }

        $slug = $this->argument('city');
        $slug = is_string($slug) ? trim($slug) : null;
        $rows = $discord->todayByCity($slug !== '' ? $slug : null);

        if ($slug !== null && $slug !== '' && $rows === []) {
            $this->error('Unknown city.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($rows as $row) {
            $count = $row['pins']->count();

            if ($count === 0 && ($slug === null || $slug === '')) {
                $this->line('SKIP '.$row['city']['name']);
                continue;
            }

            $ok = $discord->postToday($row['city'], $row['pins']);
            $this->line(($ok ? 'POSTED ' : 'FAIL ').$row['city']['name'].' '.$count);

            if (! $ok) {
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
