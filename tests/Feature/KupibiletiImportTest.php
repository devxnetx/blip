<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use App\Services\KupiBileti\Client;
use App\Services\KupiBileti\EventPage;
use App\Services\SportenKalendar\NominatimGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class KupibiletiImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $queries = [];

    private bool $sawStreet = false;

    /** @var array{fightTitle?: string, artVenue?: string, past?: bool, haltAfter?: int|null, slugs?: list<string>, tour?: bool} */
    private array $options = [];

    private int $pageHits = 0;

    private bool $faked = false;

    public function test_sections_map_onto_event_sport_and_art(): void
    {
        $place = EventPage::place('БУРГАС - Спортна зала "Младост"', 'гр. Бургас, ж.к. "Славейков" №62');
        $this->assertSame('Спортна зала Младост', $place['venue']);
        $this->assertSame('Бургас', $place['city']);
        $this->assertSame('Бургас, ж.к. Славейков №62', $place['address']);

        $attempts = NominatimGeocoder::lookupAttempts('Бургас', 'Спортна зала Младост');
        $this->assertSame('Спортна зала Младост, Бургас, България', $attempts[0]['q']);
        $this->assertArrayNotHasKey('street', $attempts[0]);

        Storage::fake('public');
        Setting::put('import_days_ahead', '1');
        $this->install();

        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        $fight = Pin::query()->where('external_id', 'elitbet-max-fight-67-65446')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $fight->kind);
        $this->assertSame('sport', $fight->category);
        $this->assertSame('ELITBET MAX FIGHT 67', $fight->message);
        $this->assertSame('Боен вечерта.', $fight->description);
        $this->assertStringNotContainsString('продава билети', (string) $fight->description);
        $this->assertStringNotContainsString('Билети само на сайта', (string) $fight->description);
        $this->assertEqualsWithDelta(42.5188379, $fight->latitude, 0.00001);
        $this->assertEqualsWithDelta(27.4577663, $fight->longitude, 0.00001);
        $this->assertSame('https://kupibileti.bg/events/elitbet-max-fight-67-65446/', $fight->source_url);
        $this->assertSame('pins/kupibileti-elitbet-max-fight-67-65446.jpg', $fight->image_path);
        $this->assertSame(720, $fight->duration_minutes);
        $this->assertSame('17:30', $fight->starts_at->timezone('Europe/Sofia')->format('H:i'));
        $this->assertSame('05:30', $fight->expires_at->timezone('Europe/Sofia')->format('H:i'));
        $this->assertTrue($fight->isPublic());
        Storage::disk('public')->assertExists($fight->image_path);

        $music = Pin::query()->where('external_id', 'lili-ivanova-varna-66324')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $music->kind);
        $this->assertSame('event', $music->category);
        $this->assertTrue($music->starts_at->greaterThan(now()->addDays(90)));
        $this->assertEqualsWithDelta(43.2038, $music->latitude, 0.00001);

        $art = Pin::query()->where('external_id', 'narodna-opera-100')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $art->kind);
        $this->assertSame('art', $art->category);
        $this->assertEqualsWithDelta(42.69403, $art->latitude, 0.00001);

        $plain = Pin::query()->where('external_id', 'bez-myasto-200')->firstOrFail();
        $this->assertSame(Pin::KIND_UNASSIGNED, $plain->kind);
        $this->assertSame('event', $plain->category);
        $this->assertNull($plain->latitude);
        $this->assertFalse($plain->isPublic());

        $this->assertNull(Pin::query()->where('external_id', 'prikluchilo-300')->first());
        $this->assertNull(Category::query()->where('slug', 'cinema')->first());

        $artCategory = Category::query()->where('slug', 'art')->firstOrFail();
        $this->assertSame('Арт', $artCategory->name);
        $this->assertTrue($artCategory->listed);

        $eventCategory = Category::query()->where('slug', 'event')->firstOrFail();
        $this->assertSame('Събитие', $eventCategory->name);
        $this->assertFalse($eventCategory->listed);

        $sportCategory = Category::query()->where('slug', 'sport')->firstOrFail();
        $this->assertSame('Спорт', $sportCategory->name);
        $this->assertTrue($sportCategory->listed);

        $device = Device::query()->where('device_hash', hash('sha256', 'kostaa-import:kupibileti'))->firstOrFail();
        $this->assertSame('Купи билети', $device->label);
        $this->assertSame($device->device_hash, $fight->device_hash);

        $run = PinImport::query()->where('source', 'kupibileti')->firstOrFail();
        $this->assertTrue($run->successful);
        $this->assertSame(4, $run->pins_count);
        $this->assertSame(1, $run->unassigned_count);

        $ids = collect($this->getJson('/api/categories')->json('categories'))->pluck('id');
        $this->assertTrue($ids->contains('art'));
        $this->assertTrue($ids->contains('sport'));
        $this->assertFalse($ids->contains('event'));

        $this->get('/map')
            ->assertOk()
            ->assertSee('data-category="art"', false)
            ->assertSee('data-category="sport"', false)
            ->assertDontSee('data-category="event"', false);

        $this->assertFalse($this->sawStreet);
        $hall = collect($this->queries)->first(fn (array $query): bool => str_contains((string) ($query['q'] ?? ''), 'Младост'));
        $this->assertSame('Спортна зала Младост, Бургас, България', $hall['q'] ?? null);
        foreach ($this->queries as $query) {
            $this->assertArrayNotHasKey('street', $query);
            $blob = (string) json_encode($query, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('Славейков', $blob);
        }
    }

    public function test_a_second_run_keeps_an_admin_placement_and_a_previous_point(): void
    {
        Storage::fake('public');
        $this->install();
        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        $placed = Pin::query()->where('external_id', 'elitbet-max-fight-67-65446')->firstOrFail();
        $placed->forceFill([
            'latitude' => 43.2,
            'longitude' => 27.9,
            'kind' => Pin::KIND_NORMAL,
            'placed_manually' => true,
        ])->save();

        $hidden = Pin::query()->where('external_id', 'bez-myasto-200')->firstOrFail();
        $hidden->forceFill([
            'message' => 'СКРИТ',
            'expires_at' => now(),
            'suppressed_at' => now(),
        ])->save();

        $art = Pin::query()->where('external_id', 'narodna-opera-100')->firstOrFail();
        $keptLat = (float) $art->latitude;
        $keptLng = (float) $art->longitude;

        $this->install([
            'fightTitle' => 'НОВО ИМЕ',
            'artVenue' => 'Зала без карта',
        ]);
        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        $placed->refresh();
        $this->assertSame('НОВО ИМЕ', $placed->message);
        $this->assertEqualsWithDelta(43.2, $placed->latitude, 0.00001);
        $this->assertEqualsWithDelta(27.9, $placed->longitude, 0.00001);
        $this->assertSame(Pin::KIND_NORMAL, $placed->kind);
        $this->assertTrue($placed->placed_manually);

        $hidden->refresh();
        $this->assertSame('СКРИТ', $hidden->message);
        $this->assertNotNull($hidden->suppressed_at);

        $art->refresh();
        $this->assertEqualsWithDelta($keptLat, $art->latitude, 0.00001);
        $this->assertEqualsWithDelta($keptLng, $art->longitude, 0.00001);
        $this->assertSame('art', $art->category);
        $this->assertSame(1, Pin::query()->where('external_id', 'elitbet-max-fight-67-65446')->count());
    }

    public function test_an_event_that_has_ended_is_taken_off_the_map(): void
    {
        Storage::fake('public');
        $this->install(['slugs' => ['live-show']]);
        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        $pin = Pin::query()->where('external_id', 'live-show')->firstOrFail();
        $this->assertTrue($pin->isPublic());

        $this->install(['slugs' => ['live-show'], 'past' => true]);
        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        $pin->refresh();
        $this->assertTrue($pin->expires_at->lessThanOrEqualTo(now()));
        $this->assertFalse($pin->isPublic());
        $this->assertSame('Жив концерт', $pin->message);

        $run = PinImport::query()->where('source', 'kupibileti')->latest('id')->firstOrFail();
        $this->assertTrue($run->successful);
        $this->assertSame(0, $run->pins_count);
    }

    public function test_a_slow_down_stops_the_import_and_keeps_pins_already_saved(): void
    {
        Storage::fake('public');
        $this->install(['haltAfter' => 1]);

        $this->artisan('pins:import-kupibileti')->assertFailed();

        $this->assertNotNull(Pin::query()->where('external_id', 'elitbet-max-fight-67-65446')->first());
        $this->assertNull(Pin::query()->where('external_id', 'lili-ivanova-varna-66324')->first());

        $run = PinImport::query()->where('source', 'kupibileti')->firstOrFail();
        $this->assertFalse($run->successful);
        $this->assertSame(1, $run->pins_count);
        $this->assertStringContainsString('slow down', (string) $run->error);
    }

    public function test_a_blocked_event_list_stops_the_import(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            return Http::response('<html><title>Just a moment...</title></html>', 403);
        });

        $this->artisan('pins:import-kupibileti')->assertFailed();

        $run = PinImport::query()->where('source', 'kupibileti')->firstOrFail();
        $this->assertFalse($run->successful);
        $this->assertStringContainsString('event list', (string) $run->error);
        $this->assertSame(0, Pin::query()->count());
    }

    public function test_a_turned_off_import_does_nothing(): void
    {
        Http::preventStrayRequests();
        Setting::put('import_kupibileti_enabled', '0');

        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, Pin::query()->count());
        $this->assertSame(0, PinImport::query()->count());
    }

    public function test_only_public_pages_are_requested(): void
    {
        Http::preventStrayRequests();
        $client = app(Client::class);

        foreach ([
            'https://kupibileti.bg/api/events',
            'https://example.com/events/show/',
        ] as $url) {
            try {
                $client->get($url);
                $this->fail('Allowed a blocked request: '.$url);
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        try {
            $client->image('https://example.com/events/a.jpg');
            $this->fail('Allowed a blocked photo');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('event photo', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_the_address_card_places_a_pin_when_the_hall_name_is_unknown(): void
    {
        Storage::fake('public');
        $this->install(['address' => true, 'slugs' => ['arena-izi-66348', 'domat-na-dyado-koleda-30-11-pernik-66104']]);

        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        $arena = Pin::query()->where('external_id', 'arena-izi-66348')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $arena->kind);
        $this->assertEqualsWithDelta(42.6501, $arena->latitude, 0.00001);
        $this->assertEqualsWithDelta(23.3802, $arena->longitude, 0.00001);

        $pernik = Pin::query()->where('external_id', 'domat-na-dyado-koleda-30-11-pernik-66104')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $pernik->kind);
        $this->assertEqualsWithDelta(42.60621, $pernik->latitude, 0.00001);
        $this->assertEqualsWithDelta(23.0302169, $pernik->longitude, 0.00001);
        $this->assertSame(2, Pin::query()->count());
    }

    public function test_a_tour_becomes_one_upcoming_pin_per_date(): void
    {
        Storage::fake('public');
        $this->install(['tour' => true, 'slugs' => ['lili-ivanova-varna-66324']]);

        $this->artisan('pins:import-kupibileti')->assertSuccessful();

        $varna = Pin::query()->where('external_id', 'lili-ivanova-varna-66324')->firstOrFail();
        $sofia = Pin::query()->where('external_id', 'lili-ivanova-66322')->firstOrFail();

        $this->assertSame(Pin::KIND_UPCOMING, $varna->kind);
        $this->assertSame(Pin::KIND_UPCOMING, $sofia->kind);
        $this->assertSame('event', $varna->category);
        $this->assertSame('event', $sofia->category);
        $this->assertSame('Лили Иванова', $varna->message);
        $this->assertSame('Лили Иванова', $sofia->message);
        $this->assertSame('Концерт.', $varna->description);
        $this->assertSame('Концерт в София.', $sofia->description);
        $this->assertNotNull($varna->latitude);
        $this->assertNotNull($sofia->latitude);
        $this->assertSame('pins/kupibileti-lili-ivanova-66322.jpg', $sofia->image_path);
        $this->assertSame('https://kupibileti.bg/events/lili-ivanova-66322/', $sofia->source_url);
        $this->assertNull(Pin::query()->where('external_id', 'lili-ivanova-turne-2027-4593')->first());
        $this->assertSame(2, Pin::query()->count());
    }

    public function test_import_runs_once_a_week(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:import-kupibileti')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'pins:import-kupibileti'));

        $this->assertNotNull($event);
        $this->assertSame('0 6 * * 1', $event->expression);
        $this->assertSame('Europe/Sofia', $event->timezone);
    }

    /**
     * @param  array{fightTitle?: string, artVenue?: string, past?: bool, haltAfter?: int|null, slugs?: list<string>, tour?: bool}  $options
     */
    private function install(array $options = []): void
    {
        $this->options = $options;
        $this->queries = [];
        $this->sawStreet = false;
        $this->pageHits = 0;

        if ($this->faked) {
            return;
        }

        $this->faked = true;
        Http::preventStrayRequests();
        $jpeg = $this->jpegBytes();

        Http::fake(function (Request $request) use ($jpeg) {
            $host = parse_url($request->url(), PHP_URL_HOST) ?? '';
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            if (str_contains($host, 'nominatim.openstreetmap.org')) {
                $data = $request->data();

                if (array_key_exists('street', $data)) {
                    $this->sawStreet = true;
                    $street = (string) $data['street'];

                    if (str_contains($street, 'Лазаров')) {
                        return Http::response([[
                            'lat' => '42.6501',
                            'lon' => '23.3802',
                            'address' => ['house_number' => '14', 'road' => 'бул. проф. Цветан Лазаров', 'city' => 'София'],
                        ]]);
                    }

                    return Http::response([[
                        'lat' => '42.5180661',
                        'lon' => '27.4566817',
                        'address' => ['house_number' => '62', 'road' => 'ж.к. Славейков', 'city' => 'Бургас'],
                    ]]);
                }

                $this->queries[] = $data;
                $query = (string) ($data['q'] ?? '');

                if (str_contains($query, 'Младост')) {
                    return Http::response([[
                        'lat' => '42.5188379',
                        'lon' => '27.4577663',
                        'category' => 'leisure',
                        'type' => 'sports_centre',
                        'address' => ['leisure' => 'Спортна зала Младост', 'road' => 'Георги Минков', 'city' => 'Бургас'],
                    ]]);
                }

                if (str_contains($query, 'Вазов')) {
                    return Http::response([[
                        'lat' => '42.69403',
                        'lon' => '23.32616',
                        'address' => ['amenity' => 'Народен театър Иван Вазов', 'city' => 'София'],
                    ]]);
                }

                if (str_contains($query, 'Летен')) {
                    return Http::response([[
                        'lat' => '43.2038',
                        'lon' => '27.9147',
                        'address' => ['amenity' => 'Летен театър', 'city' => 'Варна'],
                    ]]);
                }

                if (str_contains($query, 'Мемориал,')) {
                    return Http::response([[
                        'lat' => '42.6062100',
                        'lon' => '23.0302169',
                        'category' => 'historic',
                        'type' => 'memorial',
                        'address' => ['historic' => 'Мемориал на миньорския труд', 'city' => 'Перник'],
                    ]]);
                }

                if (str_contains($query, 'Свобода')) {
                    return Http::response([[
                        'lat' => '42.5470294',
                        'lon' => '22.9647027',
                        'category' => 'leisure',
                        'type' => 'park',
                        'address' => ['park' => 'Площад Свобода', 'town' => 'Радомир'],
                    ]]);
                }

                return Http::response([]);
            }

            if ($host === 'static-client.aws.kupibileti.bg') {
                return Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']);
            }

            if ($host === 'api.scrape.ly') {
                $data = $request->data();
                $target = (string) ($data['url'] ?? '');
                $this->assertSame('json', $data['format'] ?? null);
                $this->assertSame('BG', $data['country'] ?? null);
                $this->assertSame(['Bearer test-token'], $request->header('Authorization'));

                return $this->scraped($target);
            }

            if (in_array($host, ['kupibileti.bg', 'www.kupibileti.bg'], true)) {
                return Http::response('direct', 500);
            }

            return Http::response('missing', 404);
        });
    }

    private function scraped(string $target)
    {
        $path = parse_url($target, PHP_URL_PATH) ?? '';
        $pages = $this->pages();
        $page = rtrim($path, '/').'/';

        if ($path === '/sitemap/bg.xml') {
            $sitemap = '';

            foreach (array_keys($pages) as $listed) {
                if (($this->options['tour'] ?? false) && str_contains($listed, 'lili-ivanova-66322')) {
                    continue;
                }

                $sitemap .= '<loc>https://kupibileti.bg'.$listed.'</loc>';
            }

            if ($this->options['tour'] ?? false) {
                $sitemap .= '<loc>https://kupibileti.bg/event-groups/lili-ivanova-turne-2027-4593/</loc>';
            }

            return $this->pageBody($target, '<urlset>'.$sitemap.'</urlset>');
        }

        if ($page === '/event-groups/lili-ivanova-turne-2027-4593/') {
            return $this->pageBody($target, $this->seriesHtml([
                'lili-ivanova-varna-66324',
                'lili-ivanova-66322',
            ]));
        }

        if (isset($pages[$page])) {
            $this->pageHits++;
            $haltAfter = $this->options['haltAfter'] ?? null;

            if ($haltAfter !== null && $this->pageHits > $haltAfter) {
                return Http::response([
                    'url' => $target,
                    'loadedUrl' => $target,
                    'status' => 429,
                    'html' => 'slow',
                ]);
            }

            return $this->pageBody($target, $pages[$page]);
        }

        return Http::response([
            'error' => ['type' => 'page-not-loaded', 'message' => 'missing'],
        ], 422);
    }

    private function pageBody(string $target, string $html)
    {
        return Http::response([
            'url' => $target,
            'loadedUrl' => $target,
            'status' => 200,
            'chrome' => false,
            'html' => $html,
        ]);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function seriesHtml(array $slugs): string
    {
        $subs = [];

        foreach ($slugs as $slug) {
            $subs[] = [
                '@type' => 'Event',
                'name' => 'Лили Иванова',
                'url' => 'https://kupibileti.bg/events/'.$slug.'/',
            ];
        }

        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'EventSeries',
            'name' => 'ЛИЛИ ИВАНОВА - ТУРНЕ 2027',
            'subEvent' => $subs,
        ];

        return '<html><head><script type="application/ld+json">'
            .json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</script></head></html>';
    }

    /**
     * @return array<string, string>
     */
    private function pages(): array
    {
        $fightTitle = $this->options['fightTitle'] ?? 'ELITBET MAX FIGHT 67';
        $artVenue = $this->options['artVenue'] ?? 'Народен театър Иван Вазов';
        $past = (bool) ($this->options['past'] ?? false);
        $pages = [];

        foreach ($this->events($fightTitle, $artVenue, $past) as $event) {
            if (isset($this->options['slugs']) && ! in_array($event['slug'], $this->options['slugs'], true)) {
                continue;
            }

            if (! isset($this->options['slugs']) && $event['slug'] === 'live-show') {
                continue;
            }

            $pages['/events/'.$event['slug'].'/'] = $this->eventHtml($event);
        }

        if ($this->options['address'] ?? false) {
            $arenaStart = now('Europe/Sofia')->addDays(12)->setTime(19, 0, 0);
            $pernikStart = now('Europe/Sofia')->addDays(9)->setTime(10, 15, 0);
            $pages['/events/arena-izi-66348/'] = $this->eventHtml([
                'slug' => 'arena-izi-66348',
                'name' => 'БК ЦСКА',
                'section' => 'sport',
                'sectionName' => 'Спорт',
                'start' => $arenaStart,
                'end' => $arenaStart->copy()->addHours(2),
                'location' => 'СОФИЯ - Арена Изи',
                'street' => 'бул. „проф. Цветан Лазаров“ 14',
                'description' => 'Мач.',
                'cards' => true,
            ]);
            $pages['/events/domat-na-dyado-koleda-30-11-pernik-66104/'] = $this->eventHtml([
                'slug' => 'domat-na-dyado-koleda-30-11-pernik-66104',
                'name' => 'Домът на Дядо Коледа - 30.11 - Перник',
                'section' => 'event',
                'sectionName' => 'Събития',
                'start' => $pernikStart,
                'end' => $pernikStart->copy()->addHours(10),
                'location' => 'ПЕРНИК - Домът на Дядо Коледа',
                'street' => 'пл. Свобода, Мемориала',
                'description' => 'Коледа.',
                'cards' => true,
            ]);
        }

        if ($this->options['tour'] ?? false) {
            $sofiaStart = now('Europe/Sofia')->addDays(405)->setTime(20, 0, 0);
            $pages['/events/lili-ivanova-66322/'] = $this->eventHtml([
                'slug' => 'lili-ivanova-66322',
                'name' => 'Лили Иванова',
                'section' => 'music',
                'sectionName' => 'Музика',
                'start' => $sofiaStart,
                'end' => $sofiaStart->copy()->addHours(2),
                'location' => 'СОФИЯ - Народен театър Иван Вазов',
                'street' => 'гр. София',
                'description' => 'Концерт в София.',
                'image' => 'https://static-client.aws.kupibileti.bg/events/sofia-show.jpg',
            ]);
        }

        return $pages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(string $fightTitle, string $artVenue, bool $past): array
    {
        $fightStart = now('Europe/Sofia')->addDays(10)->setTime(17, 30, 0);
        $fightEnd = $fightStart->copy()->addHours(12);
        $musicStart = now('Europe/Sofia')->addDays(400)->setTime(20, 0, 0);
        $artStart = now('Europe/Sofia')->addDays(20)->setTime(19, 0, 0);
        $plainStart = now('Europe/Sofia')->addDays(8)->setTime(18, 0, 0);
        $endedStart = now('Europe/Sofia')->subDays(3)->setTime(18, 0, 0);
        $endedEnd = now('Europe/Sofia')->subDay()->setTime(22, 0, 0);
        $liveStart = $past
            ? now('Europe/Sofia')->subDays(2)->setTime(18, 0, 0)
            : now('Europe/Sofia')->addDays(4)->setTime(18, 0, 0);
        $liveEnd = $past
            ? now('Europe/Sofia')->subHour()->startOfMinute()
            : $liveStart->copy()->addHours(3);

        return [
            [
                'slug' => 'elitbet-max-fight-67-65446',
                'name' => $fightTitle,
                'section' => 'sport',
                'sectionName' => 'Спорт',
                'start' => $fightStart,
                'end' => $fightEnd,
                'location' => 'БУРГАС - Спортна зала "Младост"',
                'street' => 'гр. Бургас, ж.к. "Славейков" №62',
                'description' => "Боен вечерта.\n\nkupibileti.bg продава билети за това събитие.\nБилети само на сайта kupibileti.bg и на касата.",
                'image' => 'https://static-client.aws.kupibileti.bg/events/a126fcf9-fbc4-4434-be27-62fe417cdcc2.jpg',
            ],
            [
                'slug' => 'lili-ivanova-varna-66324',
                'name' => 'Лили Иванова',
                'section' => 'music',
                'sectionName' => 'Музика',
                'start' => $musicStart,
                'end' => $musicStart->copy()->addHours(2),
                'location' => 'ВАРНА - Летен театър',
                'street' => 'гр. Варна',
                'description' => 'Концерт.',
            ],
            [
                'slug' => 'narodna-opera-100',
                'name' => 'Народна опера',
                'section' => 'art',
                'sectionName' => 'Арт',
                'start' => $artStart,
                'end' => $artStart->copy()->addHours(2),
                'location' => 'СОФИЯ - '.$artVenue,
                'street' => 'гр. София',
                'description' => 'Изложба.',
            ],
            [
                'slug' => 'bez-myasto-200',
                'name' => 'Без място',
                'section' => 'cinema',
                'sectionName' => 'Кино',
                'start' => $plainStart,
                'end' => $plainStart->copy()->addHours(2),
                'description' => 'Без място.',
            ],
            [
                'slug' => 'prikluchilo-300',
                'name' => 'Приключило',
                'section' => 'music',
                'sectionName' => 'Музика',
                'start' => $endedStart,
                'end' => $endedEnd,
                'location' => 'СОФИЯ - Народен театър Иван Вазов',
                'street' => 'гр. София',
                'description' => 'Минало.',
            ],
            [
                'slug' => 'live-show',
                'name' => 'Жив концерт',
                'section' => 'music',
                'sectionName' => 'Музика',
                'start' => $liveStart,
                'end' => $liveEnd,
                'location' => 'ВАРНА - Летен театър',
                'street' => 'гр. Варна',
                'description' => 'Жив.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventHtml(array $event): string
    {
        $node = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event['name'],
            'description' => $event['description'],
            'startDate' => $event['start']->toIso8601String(),
            'endDate' => $event['end']->toIso8601String(),
        ];

        if (isset($event['image'])) {
            $node['image'] = $event['image'];
        }

        if (isset($event['location'])) {
            $node['location'] = [
                '@type' => 'Place',
                'name' => $event['location'],
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $event['street'] ?? '',
                    'addressCountry' => 'BG',
                ],
            ];
        }

        $graph = [
            $node,
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Начало', 'item' => 'https://kupibileti.bg/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $event['sectionName'], 'item' => 'https://kupibileti.bg/'.$event['section'].'/'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $event['name'], 'item' => 'https://kupibileti.bg/events/'.$event['slug'].'/'],
                ],
            ],
        ];

        $cards = '';

        if (($event['cards'] ?? false) === true) {
            $cards = '<ul class="event-info-cards">'
                .'<li data-fact="venue"><dl><dt>Място</dt><dd>'.($event['location'] ?? '').'</dd></dl></li>'
                .'<li data-fact="address"><dl><dt>Адрес</dt><dd>'.($event['street'] ?? '').' Виж на картата →</dd></dl></li>'
                .'</ul>';
        }

        return '<html><head><script type="application/ld+json">'
            .json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</script></head><body>'.$cards.'</body></html>';
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
