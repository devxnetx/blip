<?php

namespace App\Console\Commands;

use App\Models\PinImport;
use App\Models\Setting;
use App\Services\FacebookEvents\Importer;
use App\Services\ImportHalted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ImportFacebookEvents extends Command
{
    protected $signature = 'pins:import-facebook {city?} {--wide : One city, every page, no date cap}';

    protected $description = 'Import public Facebook events for every map city into pins';

    public function handle(Importer $importer): int
    {
        if (! Setting::enabled('import_facebook_enabled')) {
            $this->info('The Facebook events import is turned off.');

            return self::SUCCESS;
        }

        $city = $this->argument('city');
        $wide = (bool) $this->option('wide');

        if ($wide && (! is_string($city) || trim($city) === '')) {
            $this->error('Name the city for a wide import.');

            return self::FAILURE;
        }

        if (is_string($city) && trim($city) !== '' && ! $wide) {
            $this->error('Pass --wide with the city.');

            return self::FAILURE;
        }

        $lock = Cache::lock('pins-import-facebook', 480 * 60);

        if (! $lock->get()) {
            $this->warn('An import is already running.');

            return self::SUCCESS;
        }

        $import = PinImport::begin(PinImport::SOURCE_FACEBOOK);
        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
        ];

        try {
            $progress = function (string $line): void {
                $this->output->writeln($line);

                if (defined('STDOUT') && is_resource(STDOUT)) {
                    fflush(STDOUT);
                }
            };
            $counts = $wide
                ? $importer->runWide(trim((string) $city), $progress)
                : $importer->run($progress);
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
