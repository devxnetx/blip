<?php

namespace App\Console\Commands;

use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
use App\Services\ImportHalted;
use App\Services\SportenKalendar\CalendarClient;
use App\Services\SportenKalendar\Importer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ImportSportenKalendar extends Command
{
    protected $signature = 'pins:import-sporten {--fresh : Delete every pin before importing} {--limit= : Stop after this many event pages}';

    protected $description = 'Import public sports-calendar events into pins';

    public function handle(Importer $importer): int
    {
        if (! Setting::enabled('import_sporten_enabled')) {
            $this->info('The sports calendar import is turned off.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('pins-import-sporten', 180 * 60);

        if (! $lock->get()) {
            $this->warn('An import is already running.');

            return self::SUCCESS;
        }

        $import = PinImport::begin(PinImport::SOURCE_SPORTEN);
        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];

        try {
            if ($this->option('fresh') && ! $this->calendarIsReachable()) {
                $import->fail($counts, 'The sports calendar did not answer. Existing pins were left in place.');

                return self::FAILURE;
            }

            if ($this->option('fresh')) {
                $this->deletePins();
            }

            $limit = $this->option('limit');
            $counts = $importer->run(function (string $line): void {
                $this->line($line);
            }, is_numeric($limit) ? (int) $limit : null);
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

    private function calendarIsReachable(): bool
    {
        try {
            $body = app(CalendarClient::class)->get('https://sportenkalendar.bg/sitemap');
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return false;
        }

        if (! is_string($body) || (! str_contains($body, '/sybitie/') && ! str_contains($body, '/sportove/'))) {
            $this->error('The sports calendar did not answer. Existing pins were left in place.');

            return false;
        }

        return true;
    }

    private function deletePins(): void
    {
        $this->warn('Deleting every pin.');

        foreach (Pin::query()->orderBy('id')->get() as $pin) {
            $pin->deleteImage();
            $pin->delete();
        }
    }
}
