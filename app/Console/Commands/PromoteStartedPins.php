<?php

namespace App\Console\Commands;

use App\Models\Pin;
use Illuminate\Console\Command;

class PromoteStartedPins extends Command
{
    protected $signature = 'pins:promote-started';

    protected $description = 'Turn upcoming pins into normal pins once the event has started';

    public function handle(): int
    {
        $count = Pin::promoteStarted();

        $this->info($count === 1 ? 'Promoted 1 pin.' : "Promoted {$count} pins.");

        return self::SUCCESS;
    }
}
