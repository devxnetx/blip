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
    protected $signature = 'pins:import-facebook {city?} {--wide : Wide search, no date cap} {--scheduled : Skip when a wide import succeeded less than 14 days ago} {--run= : Import an actor run that is already going}';

    protected $description = 'Import public Facebook events for every map city into pins';

    public function handle(Importer $importer): int
    {
        if (! Setting::enabled('import_facebook_enabled')) {
            $this->info('The Facebook events import is turned off.');

            return self::SUCCESS;
        }

        $city = $this->argument('city');
        $wide = (bool) $this->option('wide');
        $scheduled = (bool) $this->option('scheduled');
        $run = trim((string) $this->option('run'));
        $cityName = is_string($city) ? trim($city) : '';

        if ($run !== '' && $cityName === '') {
            $this->error('Name the city for a wide import.');

            return self::FAILURE;
        }

        if ($cityName !== '' && ! $wide && $run === '') {
            $this->error('Pass --wide with the city.');

            return self::FAILURE;
        }

        if ($scheduled && (! $wide || $cityName !== '' || $run !== '')) {
            $this->error('The 14 day schedule runs the wide import for every city.');

            return self::FAILURE;
        }

        if ($scheduled && ! PinImport::facebookWideIsDue()) {
            $this->info('The wide Facebook import ran less than 14 days ago.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('pins-import-facebook', 1080 * 60);

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
            $counts = $run !== ''
                ? $importer->saveCollectedRun($cityName, $run, $progress)
                : ($wide
                    ? ($cityName !== ''
                        ? $importer->runWide($cityName, $progress)
                        : $importer->runWideAll($progress))
                    : $importer->run($progress));
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
