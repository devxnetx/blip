<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
use App\Services\FacebookEvents\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacebookImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_events_become_upcoming_pins_and_a_missing_point_stays_unassigned(): void
    {
        Storage::fake('public');
        $this->fakeFeed();

        Pin::query()->create([
            'device_hash' => hash('sha256', 'already'),
            'message' => 'Стар пин',
            'latitude' => 43.2,
            'longitude' => 27.9,
            'expires_at' => now()->addDay(),
            'source' => 'facebook',
            'external_id' => '17009',
        ]);

        $this->artisan('pins:import-facebook')
            ->expectsOutputToContain('Upcoming 2, on the map now 1, unassigned 2, skipped 5, hidden 1.')
            ->assertSuccessful();

        $upcoming = Pin::query()->where('external_id', '17001')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $upcoming->kind);
        $this->assertSame('Varna Winter Fest', $upcoming->message);
        $this->assertSame('Концерт на морето.', $upcoming->description);
        $this->assertStringNotContainsString('0888', (string) $upcoming->description);
        $this->assertSame('event', $upcoming->category);
        $this->assertSame('pins/facebook-17001.jpg', $upcoming->image_path);
        $this->assertTrue(Storage::disk('public')->exists('pins/facebook-17001.jpg'));
        $this->assertSame('https://www.facebook.com/events/17001/', $upcoming->source_url);
        $this->assertTrue($upcoming->starts_at->isFuture());
        $this->assertTrue($upcoming->expires_at->greaterThan($upcoming->starts_at));
        $this->assertEqualsWithDelta(43.210854, $upcoming->latitude, 0.00001);
        $this->assertEqualsWithDelta(27.931751, $upcoming->longitude, 0.00001);
        $this->assertTrue($upcoming->isPublic());
        $this->assertTrue($upcoming->isImported());
        $this->assertSame(1, Pin::query()->where('external_id', '17001')->count());

        $current = Pin::query()->where('external_id', '17002')->firstOrFail();
        $this->assertSame(Pin::KIND_NORMAL, $current->kind);
        $this->assertTrue($current->isPublic());

        $unassigned = Pin::query()->where('external_id', '17008')->firstOrFail();
        $this->assertSame(Pin::KIND_UNASSIGNED, $unassigned->kind);
        $this->assertNull($unassigned->latitude);
        $this->assertNull($unassigned->longitude);
        $this->assertSame('Без карта', $unassigned->message);
        $this->assertSame('Мястото още не е уточнено.', $unassigned->description);
        $this->assertSame('pins/facebook-17008.jpg', $unassigned->image_path);
        $this->assertFalse($unassigned->isPublic());

        $stara = Pin::query()->where('external_id', '17101')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $stara->kind);
        $this->assertSame('DJ Pencho', $stara->message);
        $this->assertEqualsWithDelta(42.42277, $stara->latitude, 0.00001);
        $this->assertEqualsWithDelta(25.61956, $stara->longitude, 0.00001);
        $this->assertTrue($stara->starts_at->lessThan(Setting::importUntil()));

        $hidden = Pin::query()->where('external_id', '17009')->firstOrFail();
        $this->assertSame('Стар пин', $hidden->message);
        $this->assertTrue($hidden->expires_at->isPast());

        $abroad = Pin::query()->where('external_id', '17006')->firstOrFail();
        $this->assertSame(Pin::KIND_UNASSIGNED, $abroad->kind);
        $this->assertNull($abroad->latitude);
        $this->assertNull($abroad->longitude);

        $this->assertNull(Pin::query()->where('external_id', '17003')->first());
        $this->assertNull(Pin::query()->where('external_id', '17004')->first());
        $this->assertNull(Pin::query()->where('external_id', '17005')->first());
        $this->assertNull(Pin::query()->where('external_id', '17103')->first());

        $category = Category::query()->where('slug', 'event')->firstOrFail();
        $this->assertSame('Събитие', $category->name);
        $this->assertFalse($category->listed);

        $device = Device::query()->where('device_hash', hash('sha256', 'kostaa-import:facebook'))->firstOrFail();
        $this->assertSame('Facebook събития', $device->label);
        $this->assertSame($device->device_hash, $upcoming->device_hash);

        $run = PinImport::query()->where('source', 'facebook')->firstOrFail();
        $this->assertTrue($run->successful);
        $this->assertSame(5, $run->pins_count);
        $this->assertSame(2, $run->unassigned_count);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return str_contains($request->url(), 'unseenuser~fb-events/run-sync-get-dataset-items')
                && ($data['cityUrls'][0] ?? null) === 'https://www.facebook.com/events/explore/varna-bulgaria/114974968516694'
                && ($data['onlyUpcoming'] ?? null) === true
                && is_int($data['maxResults'] ?? null)
                && ($data['maxResults'] ?? 0) >= 1
                && ($data['maxResults'] ?? 0) <= 10000
                && ($request->header('Authorization')[0] ?? '') === 'Bearer test-token';
        });
        Http::assertSent(function (Request $request) {
            return ($request->data()['queries'][0] ?? null) === 'Stara Zagora';
        });
        Http::assertNotSent(function (Request $request) {
            return ($request->data()['queries'][0] ?? null) === 'Varna';
        });
        Http::assertSent(function (Request $request) {
            $agent = $request->header('User-Agent')[0] ?? '';

            return str_contains($request->url(), 'scontent.xx.fbcdn.net')
                && str_contains($agent, 'KostaaBot/1.0')
                && ! str_contains($agent, 'Mozilla');
        });
        Http::assertNotSent(function (Request $request) {
            return str_contains($request->url(), 'evil.example');
        });
    }

    public function test_extraction_stops_at_the_event_cap(): void
    {
        config(['blip.facebook_events_max_total' => 1]);
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $data = $request->data();

            if (str_contains($request->url(), 'fbcdn.net')) {
                return Http::response($this->jpegBytes(), 200, ['Content-Type' => 'image/jpeg']);
            }

            $this->assertLessThanOrEqual(1, (int) ($data['maxResults'] ?? 0));

            if (($data['cityUrls'][0] ?? null) === 'https://www.facebook.com/events/explore/varna-bulgaria/114974968516694') {
                $limit = (int) ($data['maxResults'] ?? 0);

                return Http::response(array_slice($this->varnaEvents(), 0, $limit));
            }

            return Http::response([]);
        });

        $this->artisan('pins:import-facebook')
            ->expectsOutputToContain('EXTRACTED Варна added=1 total=1')
            ->assertSuccessful();

        $this->assertSame(1, Pin::query()->where('source', 'facebook')->count());
        Http::assertNotSent(function (Request $request) {
            return ($request->data()['cityUrls'][0] ?? null) === 'https://www.facebook.com/events/explore/burgas-bulgaria/114964575187249';
        });
    }

    public function test_a_second_run_keeps_an_admin_placement_and_a_hidden_pin(): void
    {
        Storage::fake('public');
        $this->fakeFeed();
        $this->artisan('pins:import-facebook')->assertSuccessful();

        $placed = Pin::query()->where('external_id', '17001')->firstOrFail();
        $placed->forceFill([
            'latitude' => 43.2,
            'longitude' => 27.9,
            'kind' => Pin::KIND_NORMAL,
            'placed_manually' => true,
        ])->save();

        $hidden = Pin::query()->where('external_id', '17008')->firstOrFail();
        $hidden->forceFill([
            'message' => 'СКРИТ',
            'expires_at' => now(),
            'suppressed_at' => now(),
        ])->save();

        $this->artisan('pins:import-facebook')->assertSuccessful();

        $placed->refresh();
        $this->assertSame('Varna Winter Fest', $placed->message);
        $this->assertEqualsWithDelta(43.2, $placed->latitude, 0.00001);
        $this->assertEqualsWithDelta(27.9, $placed->longitude, 0.00001);
        $this->assertSame(Pin::KIND_NORMAL, $placed->kind);
        $this->assertTrue($placed->placed_manually);

        $hidden->refresh();
        $this->assertSame('СКРИТ', $hidden->message);
        $this->assertNotNull($hidden->suppressed_at);
    }

    public function test_a_failed_read_stops_and_keeps_existing_pins(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.apify.com/*' => Http::response('nope', 500),
        ]);

        $pin = Pin::query()->create([
            'device_hash' => hash('sha256', 'keep'),
            'message' => 'Остава',
            'latitude' => 42.7,
            'longitude' => 25.6,
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('pins:import-facebook')
            ->expectsOutputToContain('did not return a list')
            ->assertFailed();

        $this->assertNotNull($pin->fresh());
        $run = PinImport::query()->where('source', 'facebook')->firstOrFail();
        $this->assertFalse($run->successful);
    }

    public function test_the_import_does_nothing_when_it_is_turned_off(): void
    {
        Setting::put('import_facebook_enabled', '0');
        Http::preventStrayRequests();
        Http::fake();

        $this->artisan('pins:import-facebook')
            ->expectsOutputToContain('turned off')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, Pin::query()->where('source', 'facebook')->count());
    }

    public function test_import_runs_every_three_days(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:import-facebook')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'pins:import-facebook'));

        $this->assertNotNull($event);
        $this->assertSame('20 6 * * *', $event->expression);
        $this->assertSame('Europe/Sofia', $event->timezone);
        $this->assertStringContainsString('--wide', (string) $event->command);
        $this->assertStringContainsString('--scheduled', (string) $event->command);
    }

    public function test_scheduled_wide_import_waits_fourteen_days(): void
    {
        PinImport::query()->create([
            'source' => 'facebook',
            'started_at' => now()->subDays(3),
            'finished_at' => now()->subDays(3),
            'successful' => true,
        ]);
        Http::preventStrayRequests();
        Http::fake();

        $this->artisan('pins:import-facebook', ['--wide' => true, '--scheduled' => true])
            ->expectsOutputToContain('14 days')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_wide_city_import_keeps_events_past_the_day_window(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'fbcdn.net')) {
                return Http::response($this->jpegBytes(), 200, ['Content-Type' => 'image/jpeg']);
            }

            if (str_contains($url, '/runs')) {
                $data = $request->data();
                $queries = $data['queries'] ?? [];
                $this->assertSame(0, $data['maxResults'] ?? null);
                $this->assertFalse($data['onlyUpcoming'] ?? true);
                $this->assertSame('', $data['cityTime'] ?? null);
                $this->assertSame(
                    'https://www.facebook.com/events/explore/stara-zagora-bulgaria/108597975837530',
                    $data['cityUrls'][0] ?? null,
                );
                $this->assertContains('Stara Zagora', $queries);
                $this->assertContains('Стара Загора', $queries);

                return Http::response(['data' => ['id' => 'WideRun123456']]);
            }

            if (str_contains($url, '/actor-runs/WideRun123456')) {
                return Http::response([
                    'data' => ['status' => 'SUCCEEDED', 'defaultDatasetId' => 'WideData12345'],
                ]);
            }

            if (str_contains($url, '/datasets/WideData12345') && ! str_contains($url, '/items')) {
                return Http::response(['data' => ['itemCount' => 4]]);
            }

            $far = now()->addDays(200);
            $near = now()->addDays(10);
            $ended = now()->subDay();

            return Http::response([
                $this->event('17201', 'Далечен концерт', $far, $far->copy()->addHours(3), '108597975837530', 'Stara Zagora, Bulgaria', 42.426, 25.635, '', null),
                $this->event('17202', 'Близко село', $near, $near->copy()->addHours(2), '1', 'Малко село', 42.47, 25.6345, '', null),
                $this->event('17203', 'Минало', $ended, $ended->copy()->addHour(), '108597975837530', 'Stara Zagora, Bulgaria', 42.42, 25.62, '', null),
                $this->event('17204', 'Пловдив', $near, $near->copy()->addHours(2), '999', 'Plovdiv, Bulgaria', 42.14, 24.75, '', null),
            ]);
        });

        $this->artisan('pins:import-facebook', ['city' => 'stara-zagora', '--wide' => true])
            ->expectsOutputToContain('EXTRACTED Стара Загора added=4 total=4')
            ->expectsOutputToContain('Upcoming 2, on the map now 0, unassigned 0, skipped 2, hidden 0.')
            ->assertSuccessful();

        $this->assertNotNull(Pin::query()->where('external_id', '17201')->first());
        $this->assertNotNull(Pin::query()->where('external_id', '17202')->first());
        $this->assertNull(Pin::query()->where('external_id', '17203')->first());
        $this->assertNull(Pin::query()->where('external_id', '17204')->first());
    }

    public function test_wide_import_for_every_city_stops_at_the_event_cap(): void
    {
        config(['blip.facebook_events_max_total' => 1]);
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/usage/monthly')) {
                return Http::response(['data' => ['totalUsageCreditsUsdAfterVolumeDiscount' => 0]]);
            }

            if (str_contains($url, '/runs')) {
                $this->assertSame(
                    'https://www.facebook.com/events/explore/sofia-bulgaria/106013482772674',
                    $data['cityUrls'][0] ?? null,
                );
                $this->assertSame(1, $data['maxResults'] ?? null);
                $this->assertFalse($data['onlyUpcoming'] ?? true);

                return Http::response(['data' => ['id' => 'SofiaRun12345']]);
            }

            if (str_contains($url, '/actor-runs/SofiaRun12345')) {
                return Http::response([
                    'data' => ['status' => 'SUCCEEDED', 'defaultDatasetId' => 'SofiaData1234'],
                ]);
            }

            if (str_contains($url, '/datasets/SofiaData1234') && ! str_contains($url, '/items')) {
                return Http::response(['data' => ['itemCount' => 1]]);
            }

            if (str_contains($url, '/items')) {
                $start = now()->addDays(5);

                return Http::response([
                    $this->event('17401', 'София концерт', $start, $start->copy()->addHours(2), '106013482772674', 'Sofia, Bulgaria', 42.7, 23.32, '', null),
                ]);
            }

            return Http::response('unexpected', 400);
        });

        $this->artisan('pins:import-facebook', ['--wide' => true])
            ->expectsOutputToContain('SPEND usd=0.00 limit=19.00')
            ->expectsOutputToContain('EXTRACTED София added=1 total=1')
            ->expectsOutputToContain('BUDGET Пловдив stopped total=1')
            ->assertSuccessful();

        $this->assertSame(1, Pin::query()->where('source', 'facebook')->count());
        Http::assertNotSent(function (Request $request) {
            return str_contains((string) ($request->data()['cityUrls'][0] ?? ''), 'plovdiv-bulgaria');
        });
    }

    public function test_wide_import_stops_before_the_plan_limit(): void
    {
        config(['blip.facebook_usd_limit' => 18]);
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/usage/monthly')) {
                return Http::response(['data' => ['totalUsageCreditsUsdAfterVolumeDiscount' => 18.2]]);
            }

            return Http::response('unexpected', 400);
        });

        $this->artisan('pins:import-facebook', ['--wide' => true])
            ->expectsOutputToContain('SPEND usd=18.20 limit=19.00')
            ->expectsOutputToContain('BUDGET София stopped total=0')
            ->expectsOutputToContain('Upcoming 0, on the map now 0, unassigned 0, skipped 0, hidden 0.')
            ->assertSuccessful();

        Http::assertNotSent(function (Request $request) {
            return str_contains($request->url(), '/runs');
        });
    }

    public function test_an_existing_actor_run_is_imported_without_a_second_scrape(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, '/actor-runs/Abcd1234Wxyz')) {
                return Http::response([
                    'data' => [
                        'status' => 'SUCCEEDED',
                        'defaultDatasetId' => 'Dataset123456',
                    ],
                ]);
            }

            if (str_contains($url, '/datasets/Dataset123456') && ! str_contains($url, '/items')) {
                return Http::response(['data' => ['itemCount' => 1]]);
            }

            if (str_contains($url, '/datasets/Dataset123456/items')) {
                $far = now()->addDays(120);

                return Http::response([
                    $this->event('17301', 'Още един концерт', $far, $far->copy()->addHours(3), '108597975837530', 'Stara Zagora, Bulgaria', 42.426, 25.635, '', null),
                ]);
            }

            return Http::response('unexpected', 400);
        });

        $this->artisan('pins:import-facebook', ['city' => 'stara-zagora', '--run' => 'Abcd1234Wxyz'])
            ->expectsOutputToContain('ACTOR SUCCEEDED items=1')
            ->expectsOutputToContain('EXTRACTED Стара Загора added=1 total=1')
            ->expectsOutputToContain('Upcoming 1, on the map now 0, unassigned 0, skipped 0, hidden 0.')
            ->assertSuccessful();

        $this->assertNotNull(Pin::query()->where('external_id', '17301')->first());
        Http::assertNotSent(function (Request $request) {
            return str_contains($request->url(), 'run-sync-get-dataset-items');
        });
    }

    public function test_only_a_facebook_cover_can_be_fetched(): void
    {
        $this->assertTrue(Client::isCoverUrl('https://scontent.xx.fbcdn.net/v/t39/cover.jpg'));
        $this->assertFalse(Client::isCoverUrl('https://scontent.xx.fbcdn.net/../secret.jpg'));
        $this->assertFalse(Client::isCoverUrl('https://evil.example/cover.jpg'));
        $this->assertFalse(Client::isCoverUrl('http://scontent.xx.fbcdn.net/v/t39/cover.jpg'));
    }

    private function fakeFeed(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'fbcdn.net')) {
                return Http::response($this->jpegBytes(), 200, ['Content-Type' => 'image/jpeg']);
            }

            if (! str_contains($url, 'run-sync-get-dataset-items')) {
                return Http::response('missing', 404);
            }

            $data = $request->data();
            $cityUrl = $data['cityUrls'][0] ?? null;

            if ($cityUrl === 'https://www.facebook.com/events/explore/stara-zagora-bulgaria/108597975837530') {
                return Http::response([]);
            }

            if ($cityUrl === 'https://www.facebook.com/events/explore/varna-bulgaria/114974968516694') {
                return Http::response($this->varnaEvents());
            }

            if (is_string($cityUrl) && $cityUrl !== '') {
                return Http::response([]);
            }

            if (($data['queries'][0] ?? null) === 'Stara Zagora') {
                return Http::response($this->staraEvents());
            }

            if (isset($data['queries'])) {
                return Http::response([]);
            }

            return Http::response(['error' => 'unexpected'], 400);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function varnaEvents(): array
    {
        $later = now()->addDays(3);
        $started = now()->subHour();
        $ended = now()->subDays(2);
        $tooFar = Setting::importUntil()->addDay();

        return [
            $this->event('17001', 'Varna Winter Fest', $later, $later->copy()->addHours(4), '114974968516694', 'Varna, Bulgaria', 43.210854, 27.931751, 'Концерт на морето. Тел. 0888 123 456', 'https://scontent.xx.fbcdn.net/v/t39/winter.jpg'),
            $this->event('17001', 'Varna Winter Fest', $later, $later->copy()->addHours(4), '114974968516694', 'Varna, Bulgaria', 43.210854, 27.931751, 'Концерт на морето. Тел. 0888 123 456', 'https://scontent.xx.fbcdn.net/v/t39/winter.jpg'),
            $this->event('17002', 'Вече започна', $started, $started->copy()->addHours(4), '114974968516694', 'Varna, Bulgaria', 43.214, 27.914, '', null),
            $this->event('17003', 'Отменено', $later, $later->copy()->addHours(2), '114974968516694', 'Varna, Bulgaria', 43.21, 27.93, '', null, canceled: true),
            $this->event('17004', 'Твърде напред', $tooFar, $tooFar->copy()->addHours(2), '114974968516694', 'Varna, Bulgaria', 43.21, 27.93, '', null),
            $this->event('17005', 'Онлайн', $later, $later->copy()->addHours(2), '114974968516694', 'Varna, Bulgaria', 43.21, 27.93, '', null, online: true),
            $this->event('17006', 'Извън страната', $later, $later->copy()->addHours(2), '114974968516694', 'Varna, Bulgaria', 27.77, -82.63, '', null),
            $this->event('17008', 'Без карта', $later, $later->copy()->addHours(2), '114974968516694', 'Varna, Bulgaria', null, null, 'Мястото още не е уточнено.', 'https://scontent.xx.fbcdn.net/v/t39/nocoords.jpg'),
            $this->event('17009', 'Приключило', $ended, $ended->copy()->addHours(2), '114974968516694', 'Varna, Bulgaria', 43.21, 27.93, '', null),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staraEvents(): array
    {
        $inside = Setting::importUntil()->copy()->subDays(2);
        $ended = now()->subDay();

        return [
            $this->event('17101', 'DJ Pencho', $inside, $inside->copy()->addHours(3), '108597975837530', 'Stara Zagora, Bulgaria', 42.42277, 25.61956, '', null),
            $this->event('17102', 'Минало', $ended, $ended->copy()->addHour(), '108597975837530', 'Stara Zagora, Bulgaria', 42.42, 25.62, '', null),
            $this->event('17103', 'Пловдив', $inside, $inside->copy()->addHours(2), '999', 'Plovdiv, Bulgaria', 42.14, 24.75, '', null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(
        string $id,
        string $name,
        \Illuminate\Support\Carbon $start,
        \Illuminate\Support\Carbon $end,
        string $cityId,
        string $city,
        ?float $latitude,
        ?float $longitude,
        string $description,
        ?string $image,
        bool $canceled = false,
        bool $online = false,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'url' => 'https://www.facebook.com/events/'.$id.'/',
            'start_utc' => $start->copy()->utc()->toISOString(),
            'end_time' => $end->copy()->utc()->toISOString(),
            'city_id' => $cityId,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'cover_photo_url' => $image,
            'is_canceled' => $canceled,
            'is_online' => $online,
            'hosts' => [['name' => 'Някой', 'url' => 'https://www.facebook.com/someone']],
        ];
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image, null, 80);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
