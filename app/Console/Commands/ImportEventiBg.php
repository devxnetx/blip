<?php

namespace App\Console\Commands;

use App\Models\PinImport;
use App\Models\Setting;
use App\Services\EventiBg\Importer;
use App\Services\ImportHalted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ImportEventiBg extends Command
{
    protected $signature = 'pins:import-eventibg {--days= : How many days ahead to read}';

    protected $description = 'Import public EventiBG events into pins';

    public function handle(Importer $importer): int
    {
        if (! Setting::enabled('import_eventibg_enabled')) {
            $this->info('The EventiBG import is turned off.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('pins-import-eventibg', 180 * 60);

        if (! $lock->get()) {
            $this->warn('An import is already running.');

            return self::SUCCESS;
        }

        $import = PinImport::begin(PinImport::SOURCE_EVENTIBG);
        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
        ];

        try {
            $days = $this->option('days');
            $counts = $importer->run(function (string $line): void {
                $this->line($line);
            }, is_numeric($days) ? max(1, (int) $days) : null);
            $import->complete($counts);

            $this->info(sprintf(
                'Upcoming %d, on the map now %d, unassigned %d, skipped %d, hidden %d.',
                $counts['upcoming'],
                $counts['current'],
                $counts['unassigned'],
                $counts['skipped'],
                $counts['hidden'],
            ));

            return self::SUCCESS;
        } catch (ImportHalted $exception) {
            $import->fail($exception->counts, $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $import->fail($counts, $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
