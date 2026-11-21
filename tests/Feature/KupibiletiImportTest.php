<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
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

    /** @var array{fightTitle?: string, artVenue?: string, past?: bool, haltAfter?: int|null, slugs?: list<string>} */
    private array $options = [];

    private int $pageHits = 0;

    private bool $faked = false;

    public function test_sections_map_onto_event_sport_and_art(): void
    {
        $place = EventPage::place('БУРГАС - Спортна зала "Младост"', 'гр. Бургас, ж.к. "Славейков" №62');
        $this->assertSame('Спортна зала Младост', $place['venue']);
        $this->assertSame('Бургас', $place['city']);
        $this->assertNull($place['address']);

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

    public function test_import_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:import-kupibileti')
            ->assertSuccessful();
    }

    /**
     * @param  array{fightTitle?: string, artVenue?: string, past?: bool, haltAfter?: int|null, slugs?: list<string>}  $options
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

                return Http::response([]);
            }

            if ($host === 'static-client.aws.kupibileti.bg') {
                return Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']);
            }

            $pages = $this->pages();

            if ($path === '/sitemap/bg.xml') {
                $sitemap = '';

                foreach (array_keys($pages) as $page) {
                    $sitemap .= '<loc>https://kupibileti.bg'.$page.'</loc>';
                }

                return Http::response('<urlset>'.$sitemap.'</urlset>', 200, ['Content-Type' => 'application/xml']);
            }

            $page = rtrim($path, '/').'/';

            if (isset($pages[$page])) {
                $this->pageHits++;
                $haltAfter = $this->options['haltAfter'] ?? null;

                if ($haltAfter !== null && $this->pageHits > $haltAfter) {
                    return Http::response('slow', 429);
                }

                return Http::response($pages[$page]);
            }

            return Http::response('missing', 404);
        });
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

        return '<html><head><script type="application/ld+json">'
            .json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</script></head><body></body></html>';
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
