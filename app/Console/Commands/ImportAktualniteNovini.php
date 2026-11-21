<?php

namespace App\Console\Commands;

use App\Models\PinImport;
use App\Models\Setting;
use App\Services\AktualniteNovini\Importer;
use App\Services\ImportHalted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ImportAktualniteNovini extends Command
{
    protected $signature = 'pins:import-aktualnitenovini';

    protected $description = 'Import public Aktualnite Novini events into pins';

    public function handle(Importer $importer): int
    {
        if (! Setting::enabled('import_aktualnitenovini_enabled')) {
            $this->info('The Aktualnite Novini import is turned off.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('pins-import-aktualnitenovini', 180 * 60);

        if (! $lock->get()) {
            $this->warn('An import is already running.');

            return self::SUCCESS;
        }

        $import = PinImport::begin(PinImport::SOURCE_AKTUALNITENOVINI);
        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
        ];

        try {
            $counts = $importer->run(function (string $line): void {
                $this->line($line);
            });
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
