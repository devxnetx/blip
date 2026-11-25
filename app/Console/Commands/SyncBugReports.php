<?php

namespace App\Console\Commands;

use App\Services\Trello\BugSync;
use Illuminate\Console\Command;

class SyncBugReports extends Command
{
    protected $signature = 'bugs:trello';

    protected $description = 'Create a Trello card for each bug report in Discord';

    public function handle(BugSync $bugs): int
    {
        if (! $bugs->enabled()) {
            $this->warn('Trello bugs are not configured.');

            return self::SUCCESS;
        }

        $counts = $bugs->sync();
        $this->line('CREATED '.$counts['created'].' UPDATED '.$counts['updated'].' FAILED '.$counts['failed']);

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
