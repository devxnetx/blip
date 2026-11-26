<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
use App\Services\SportenKalendar\CalendarClient;
use App\Services\SportenKalendar\EventPageParser;
use App\Services\SportenKalendar\NominatimGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SportenImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_become_upcoming_or_unassigned(): void
    {
        Storage::fake('public');
        $this->fakeCalendar();

        Pin::query()->create([
            'device_hash' => hash('sha256', 'already'),
            'message' => 'Стар мач',
            'latitude' => 42.1,
            'longitude' => 24.1,
            'expires_at' => now()->addDay(),
            'source' => 'sportenkalendar',
            'external_id' => '10',
        ]);

        $this->artisan('pins:import-sporten')->assertSuccessful();

        $ended = Pin::query()->where('external_id', '10')->firstOrFail();
        $this->assertTrue($ended->expires_at->isPast());
        $this->assertNull(Pin::query()->where('message', 'Стар мач')->where('expires_at', '>', now())->first());

        $street = Pin::query()->where('external_id', '11')->firstOrFail();
        $sameHall = Pin::query()->where('external_id', '12')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $street->kind);
        $this->assertSame(Pin::KIND_UPCOMING, $sameHall->kind);
        $this->assertEqualsWithDelta(42.4321, $street->latitude, 0.00001);
        $this->assertEqualsWithDelta(25.6412, $street->longitude, 0.00001);
        $this->assertSame($street->latitude, $sameHall->latitude);
        $this->assertSame($street->longitude, $sameHall->longitude);
        $this->assertSame($street->device_hash, $sameHall->device_hash);
        $this->assertSame('sport', $street->category);
        $this->assertSame('pins/sporten-11.jpg', $street->image_path);
        $this->assertSame('https://sportenkalendar.bg/sybitie/future-street-11', $street->source_url);
        $this->assertSame('Мачът е в залата на Берое.', $street->description);
        $this->assertTrue($street->isImported());
        $this->assertTrue(Storage::disk('public')->exists('pins/sporten-11.jpg'));
        $this->assertTrue($street->starts_at->isFuture());
        $this->assertTrue($street->expires_at->greaterThan($street->starts_at));
        $this->assertTrue($street->isPublic());

        $noAddress = Pin::query()->where('external_id', '13')->firstOrFail();
        $cityOnly = Pin::query()->where('external_id', '14')->firstOrFail();
        $this->assertSame(Pin::KIND_UNASSIGNED, $noAddress->kind);
        $this->assertSame(Pin::KIND_UNASSIGNED, $cityOnly->kind);
        $this->assertNull($noAddress->latitude);
        $this->assertNull($cityOnly->longitude);
        $this->assertFalse($noAddress->isPublic());
        $this->assertSame('pins/sporten-13.jpg', $noAddress->image_path);

        $this->assertSame(4, Pin::query()->where('source', 'sportenkalendar')->where('expires_at', '>', now())->count());
        $this->assertSame(2, Pin::query()->onMap()->count());
        $this->assertSame(1, Pin::query()->where('external_id', '11')->count());

        $sport = Category::query()->where('slug', 'sport')->firstOrFail();
        $this->assertTrue($sport->listed);
        $this->assertSame('Спорт', $sport->name);
        $this->assertNull(Category::query()->where('slug', 'badminton')->first());

        $device = Device::query()->where('device_hash', hash('sha256', 'kostaa-import:sportenkalendar'))->firstOrFail();
        $this->assertSame('Спортен календар', $device->label);

        $run = PinImport::query()->where('source', 'sportenkalendar')->firstOrFail();
        $this->assertTrue($run->successful);
        $this->assertSame(4, $run->pins_count);
        $this->assertSame(2, $run->unassigned_count);
        $this->assertNotNull($run->finished_at);

        $map = $this->get('/map');
        $map->assertOk()
            ->assertSee('data-category="sport"', false)
            ->assertDontSee('data-category="badminton"', false);

        $ids = collect($this->getJson('/api/categories')->json('categories'))->pluck('id');
        $this->assertTrue($ids->contains('sport'));
        $this->assertFalse($ids->contains('badminton'));
        $this->assertTrue($ids->contains('other'));
    }

    public function test_a_second_run_updates_the_same_event(): void
    {
        Storage::fake('public');
        $this->fakeCalendar();

        $this->artisan('pins:import-sporten')->assertSuccessful();
        $pin = Pin::query()->where('external_id', '11')->firstOrFail();
        $pin->forceFill(['message' => 'OLD'])->save();

        $this->artisan('pins:import-sporten')->assertSuccessful();

        $this->assertSame(1, Pin::query()->where('external_id', '11')->count());
        $this->assertSame('Държавен личен шампионат', $pin->fresh()->message);
    }

    public function test_an_admin_placement_and_a_hidden_pin_stay(): void
    {
        Storage::fake('public');
        $this->fakeCalendar();
        $this->artisan('pins:import-sporten')->assertSuccessful();

        $placed = Pin::query()->where('external_id', '11')->firstOrFail();
        $placed->forceFill([
            'latitude' => 43.2,
            'longitude' => 27.9,
            'kind' => Pin::KIND_NORMAL,
            'placed_manually' => true,
        ])->save();

        $hidden = Pin::query()->where('external_id', '13')->firstOrFail();
        $hidden->forceFill([
            'message' => 'СКРИТ',
            'expires_at' => now(),
            'suppressed_at' => now(),
        ])->save();

        $this->artisan('pins:import-sporten')->assertSuccessful();

        $placed->refresh();
        $this->assertEqualsWithDelta(43.2, $placed->latitude, 0.00001);
        $this->assertEqualsWithDelta(27.9, $placed->longitude, 0.00001);
        $this->assertSame(Pin::KIND_NORMAL, $placed->kind);
        $this->assertTrue($placed->placed_manually);

        $hidden->refresh();
        $this->assertSame('СКРИТ', $hidden->message);
        $this->assertNotNull($hidden->suppressed_at);
        $this->assertTrue($hidden->expires_at->lessThanOrEqualTo(now()));
    }

    public function test_fresh_leaves_pins_when_the_calendar_does_not_answer(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://sportenkalendar.bg/*' => Http::response('slow', 503),
        ]);

        $pin = Pin::query()->create([
            'device_hash' => hash('sha256', 'keep'),
            'message' => 'Остава',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('pins:import-sporten', ['--fresh' => true])->assertFailed();
        $this->assertNotNull($pin->fresh());
    }

    public function test_fresh_replaces_existing_pins(): void
    {
        Storage::fake('public');
        $this->fakeCalendar();

        $pin = Pin::query()->create([
            'device_hash' => hash('sha256', 'gone'),
            'message' => 'Стар пин',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'expires_at' => now()->addHour(),
            'image_path' => 'pins/old.jpg',
        ]);
        Storage::disk('public')->put('pins/old.jpg', 'old');

        $this->artisan('pins:import-sporten', ['--fresh' => true])->assertSuccessful();

        $this->assertNull(Pin::query()->find($pin->id));
        $this->assertFalse(Storage::disk('public')->exists('pins/old.jpg'));
        $this->assertNotNull(Pin::query()->where('external_id', '11')->first());
    }

    public function test_blocked_feeds_are_never_requested(): void
    {
        Http::preventStrayRequests();

        $client = app(CalendarClient::class);

        foreach ([
            'https://sportenkalendar.bg/alleventsdata',
            'https://sportenkalendar.bg/tarsi?x=1',
            'https://sportenkalendar.bg/monthsevents',
            'https://sportenkalendar.bg/alluserseventsdata',
            'https://sportenkalendar.bg/sportevents/1',
            'https://sportenkalendar.bg/customer/events/mbd',
            'https://example.com/sybitie/match-1',
        ] as $url) {
            try {
                $client->get($url);
                $this->fail('Allowed a blocked request: '.$url);
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_parser_reads_the_public_event_page(): void
    {
        $start = now('Europe/Sofia')->addDays(12)->format('d-m-Y');
        $end = now('Europe/Sofia')->addDays(13)->format('d-m-Y');
        $html = '<nav><a href="/sportove/football">Футбол</a></nav>'
            .'<h1>Държавен личен шампионат - ветерани</h1>'
            .'<meta property="og:image" content="https://sportenkalendar.bg//uploads/events/badge.jpg"/>'
            .'<p><strong>Начало:</strong> '.$start.' от 09:00 ч.</p>'
            .'<p><strong>Край:</strong> '.$end.' до 16:00 ч.</p>'
            .'<p class="single-event-top-category"><a href="/sportove/badminton">Бадминтон</a></p>'
            .'<p><strong>Място на провеждане: </strong> <a href="/zali/sportna-zala-beroe">Спортна зала &quot;Берое&quot;</a></p>'
            .'<p><strong>Адрес: </strong> Стара Загора, ул.&quot;Цар Иван Шишман&quot; № 60</p>'
            .'<div class="single-event-info-text-block"><p>Двудневен турнир за ветерани.</p></div>';

        $event = (new EventPageParser)->parse($html, 'https://sportenkalendar.bg/sybitie/d-rzaven-licen-sampionat-veterani-7766');

        $this->assertNotNull($event);
        $this->assertSame('7766', $event->externalId);
        $this->assertSame('Държавен личен шампионат - ветерани', $event->title);
        $this->assertSame('badminton', $event->sportSlug);
        $this->assertSame('Бадминтон', $event->sportName);
        $this->assertStringContainsString('Шишман', (string) $event->address);
        $this->assertSame('Спортна зала "Берое"', $event->venue);
        $this->assertSame('https://sportenkalendar.bg/uploads/events/badge.jpg', $event->imageUrl);
        $this->assertSame('Двудневен турнир за ветерани.', $event->description);
        $this->assertTrue($event->endsAt->greaterThan($event->startsAt));
    }

    public function test_a_midnight_event_without_an_end_lasts_three_hours(): void
    {
        $day = now('Europe/Sofia')->addDays(4)->format('d-m-Y');
        $html = '<h1>Концерт</h1>'
            .'<p><strong>Начало:</strong> '.$day.' от 00:00 ч.</p>';

        $event = (new EventPageParser)->parse($html, 'https://sportenkalendar.bg/sybitie/koncert-55');

        $this->assertNotNull($event);
        $this->assertSame('00:00:00', $event->startsAt->timezone('Europe/Sofia')->format('H:i:s'));
        $this->assertTrue($event->endsAt->equalTo($event->startsAt->copy()->addHours(3)));
    }

    public function test_a_midnight_event_keeps_a_real_end(): void
    {
        $day = now('Europe/Sofia')->addDays(4)->format('d-m-Y');
        $html = '<h1>Турнир</h1>'
            .'<p><strong>Начало:</strong> '.$day.' от 00:00 ч.</p>'
            .'<p><strong>Край:</strong> '.$day.' до 18:00 ч.</p>';

        $event = (new EventPageParser)->parse($html, 'https://sportenkalendar.bg/sybitie/turnir-56');

        $this->assertNotNull($event);
        $this->assertSame('18:00:00', $event->endsAt->timezone('Europe/Sofia')->format('H:i:s'));
    }

    public function test_only_a_specific_bulgarian_address_is_pinned(): void
    {
        $street = NominatimGeocoder::pointFromPayload([[
            'lat' => '42.4321',
            'lon' => '25.6412',
            'address' => ['road' => 'ул. Цар Иван Шишман', 'house_number' => '60', 'city' => 'Стара Загора'],
        ]], 'Стара Загора');
        $city = NominatimGeocoder::pointFromPayload([[
            'lat' => '42.6977',
            'lon' => '23.3219',
            'address' => ['city' => 'София', 'country' => 'България'],
        ]], 'София');
        $abroad = NominatimGeocoder::pointFromPayload([[
            'lat' => '48.2',
            'lon' => '16.3',
            'address' => ['road' => 'Ring', 'house_number' => '1'],
        ]], 'Виена');

        $this->assertSame(42.4321, $street['latitude']);
        $this->assertNull($city);
        $this->assertNull($abroad);

        $hall = NominatimGeocoder::structuredQuery('Стара Загора, Градска спортна зала , ул."Цар Иван Шишман" № 60');
        $this->assertSame('Цар Иван Шишман 60', $hall['street']);
        $this->assertSame('Стара Загора', $hall['city']);
        $byala = NominatimGeocoder::structuredQuery('Бяла (Русе), ул. "Цар Освободител" 36');
        $this->assertSame('Цар Освободител 36', $byala['street']);
        $this->assertSame('Бяла (Русе)', $byala['city']);
        $this->assertNull(NominatimGeocoder::structuredQuery('Люлин планина, Бонсови поляни'));

        $park = NominatimGeocoder::pointFromPayload([[
            'lat' => '42.4865072',
            'lon' => '26.4968841',
            'category' => 'leisure',
            'type' => 'park',
            'address' => ['park' => 'Градски парк', 'city' => 'Ямбол'],
        ]], 'Ямбол');
        $this->assertSame(42.4865072, $park['latitude']);

        $squarePoint = NominatimGeocoder::pointFromPayload([[
            'lat' => '42.1907450',
            'lon' => '24.3316809',
            'category' => 'place',
            'type' => 'square',
            'address' => ['square' => 'пл. Константин Величков', 'city' => 'Пазарджик'],
        ]], 'Пазарджик');
        $this->assertSame(42.1907450, $squarePoint['latitude']);

        $cinema = NominatimGeocoder::lookupAttempts('бул. Княгиня Мария Луиза, София', 'Кино-театър "Освобождение"');
        $this->assertSame('Освобождение, Княгиня Мария Луиза, София, България', $cinema[0]['q']);
        $this->assertArrayNotHasKey('street', $cinema[0]);

        $aleko = NominatimGeocoder::lookupAttempts('ул. „Алеко Константинов“, Бургас');
        $this->assertSame('Алеко Константинов, Бургас, България', $aleko[0]['q']);
        $this->assertArrayNotHasKey('street', $aleko[0]);

        $square = NominatimGeocoder::lookupAttempts('площад „Св. св. Кирил и Методий“, Бургас');
        $this->assertSame('пл. Св. св. Кирил и Методий, Бургас, България', $square[0]['q']);

        $numberedSquare = NominatimGeocoder::lookupAttempts('пл. „Константин Величков“ 1, Пазарджик', 'ДКТ Константин Величков');
        $this->assertSame('пл. Константин Величков 1, Пазарджик, България', $numberedSquare[0]['q']);
        $this->assertArrayNotHasKey('street', $numberedSquare[0]);

        $slavianska = NominatimGeocoder::lookupAttempts('ул. „Славянска“ 69, гр. Бургас', 'дворът на Етнографската експозиция');
        $this->assertSame('Славянска 69', $slavianska[0]['street']);
        $this->assertSame('Бургас', $slavianska[0]['city']);

        $boris = NominatimGeocoder::lookupAttempts('бул. „Княз Борис I-ви“ 115, Варна', 'MAZE CLUB');
        $this->assertSame('Княз Борис I 115', $boris[0]['street']);
        $this->assertSame('Варна', $boris[0]['city']);
        $written = array_values(array_filter($boris, fn (array $attempt): bool => str_contains((string) ($attempt['q'] ?? ''), 'бул. Княз Борис I 115')));
        $this->assertNotSame([], $written);

        $yambol = NominatimGeocoder::lookupAttempts('Ямбол, Градски парк, Ямбол', 'Спортна зала "Диана"');
        $this->assertSame('Спортна зала Диана, Ямбол, България', $yambol[0]['q']);
        $this->assertSame('Градски парк, Ямбол, България', $yambol[1]['q']);
        $this->assertCount(2, $yambol);

        $mountain = NominatimGeocoder::lookupAttempts('Люлин планина, Бонсови поляни');
        $this->assertSame([['q' => 'Люлин планина, Бонсови поляни, България', '_city' => '']], $mountain);
    }

    public function test_a_named_hall_is_pinned_when_the_address_has_no_street(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        $start = now('Europe/Sofia')->addDays(2)->format('d-m-Y');
        $end = now('Europe/Sofia')->addDays(2)->format('d-m-Y');
        $jpeg = $this->jpegBytes();
        $pages = [
            'https://sportenkalendar.bg/sitemap' => '<loc>https://sportenkalendar.bg/sybitie/dp-ambol-8119</loc>'
                .'<loc>https://sportenkalendar.bg/sybitie/park-only-20</loc>',
            'https://sportenkalendar.bg/sybitie/dp-ambol-8119' => $this->eventPage(
                'Държавно първенство по брейк',
                $start,
                $end,
                'Ямбол, Градски парк, Ямбол',
                'Спортна зала "Диана"',
            ),
            'https://sportenkalendar.bg/sybitie/park-only-20' => $this->eventPage(
                'Тренировка в парка',
                $start,
                $end,
                'Ямбол, Градски парк, Ямбол',
            ),
        ];

        Http::fake(function (Request $request) use ($pages, $jpeg) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org')) {
                $query = (string) ($request->data()['q'] ?? '');

                if (str_contains($query, 'Диана')) {
                    return Http::response([[
                        'lat' => '42.4872787',
                        'lon' => '26.4997273',
                        'category' => 'leisure',
                        'type' => 'sports_centre',
                        'address' => ['leisure' => 'Спортна зала Диана', 'road' => 'Иван Параскевов', 'city' => 'Ямбол'],
                    ]]);
                }

                if (str_contains($query, 'парк')) {
                    return Http::response([[
                        'lat' => '42.4865072',
                        'lon' => '26.4968841',
                        'category' => 'leisure',
                        'type' => 'park',
                        'address' => ['park' => 'Градски парк', 'city' => 'Ямбол'],
                    ]]);
                }

                return Http::response([[
                    'lat' => '42.484',
                    'lon' => '26.503',
                    'address' => ['city' => 'Ямбол', 'country' => 'България'],
                ]]);
            }

            if (str_contains($url, '/uploads/events/')) {
                return Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']);
            }

            foreach ($pages as $page => $body) {
                if ($url === $page) {
                    return Http::response($body);
                }
            }

            return Http::response('missing', 404);
        });

        $this->artisan('pins:import-sporten')->assertSuccessful();

        $hall = Pin::query()->where('external_id', '8119')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $hall->kind);
        $this->assertEqualsWithDelta(42.4872787, $hall->latitude, 0.00001);
        $this->assertEqualsWithDelta(26.4997273, $hall->longitude, 0.00001);

        $park = Pin::query()->where('external_id', '20')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $park->kind);
        $this->assertEqualsWithDelta(42.4865072, $park->latitude, 0.00001);
        $this->assertEqualsWithDelta(26.4968841, $park->longitude, 0.00001);
    }

    public function test_events_past_the_chosen_window_are_left_out(): void
    {
        Storage::fake('public');
        $this->fakeCalendar();
        Setting::put('import_days_ahead', '1');

        $this->artisan('pins:import-sporten')->assertSuccessful();

        $this->assertNull(Pin::query()->where('external_id', '11')->first());
    }

    public function test_import_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:import-sporten')
            ->assertSuccessful();
    }

    private function fakeCalendar(): void
    {
        Http::preventStrayRequests();
        $start = now('Europe/Sofia')->addDays(12)->format('d-m-Y');
        $end = now('Europe/Sofia')->addDays(13)->format('d-m-Y');
        $pastStart = now('Europe/Sofia')->subDays(4)->format('d-m-Y');
        $pastEnd = now('Europe/Sofia')->subDays(3)->format('d-m-Y');
        $address = 'Стара Загора, ул. Цар Иван Шишман № 60';

        $pages = [
            'https://sportenkalendar.bg/sitemap' => '<url><loc>https://sportenkalendar.bg/sybitie/ended-match-10</loc></url>'
                .'<url><loc>https://sportenkalendar.bg/sportove/badminton</loc></url>',
            'https://sportenkalendar.bg/sportove/badminton' => '<a href="/sybitie/future-street-11"></a>'
                .'<a href="/sybitie/future-same-hall-12"></a>'
                .'<a href="/sybitie/future-no-address-13"></a>'
                .'<a href="/sybitie/future-city-14"></a>',
            'https://sportenkalendar.bg/sybitie/ended-match-10' => $this->eventPage('Стар мач', $pastStart, $pastEnd, $address),
            'https://sportenkalendar.bg/sybitie/future-street-11' => $this->eventPage('Държавен личен шампионат', $start, $end, $address),
            'https://sportenkalendar.bg/sybitie/future-same-hall-12' => $this->eventPage('Втори мач в залата', $start, $end, $address),
            'https://sportenkalendar.bg/sybitie/future-no-address-13' => $this->eventPage('Турнир без адрес', $start, $end, null),
            'https://sportenkalendar.bg/sybitie/future-city-14' => $this->eventPage('Турнир в града', $start, $end, 'София'),
        ];

        $jpeg = $this->jpegBytes();

        Http::fake(function (Request $request) use ($pages, $jpeg) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org')) {
                $query = (string) ($request->data()['q'] ?? '');

                $street = (string) ($request->data()['street'] ?? '');

                if (str_contains($query, 'Шишман') || str_contains($street, 'Шишман')) {
                    return Http::response([[
                        'lat' => '42.4321',
                        'lon' => '25.6412',
                        'address' => ['road' => 'ул. Цар Иван Шишман', 'house_number' => '60'],
                    ]]);
                }

                return Http::response([[
                    'lat' => '42.6977',
                    'lon' => '23.3219',
                    'address' => ['city' => 'София', 'country' => 'България'],
                ]]);
            }

            if (str_contains($url, '/uploads/events/')) {
                return Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']);
            }

            foreach ($pages as $page => $body) {
                if ($url === $page) {
                    return Http::response($body);
                }
            }

            return Http::response('missing', 404);
        });
    }

    private function eventPage(string $title, string $start, string $end, ?string $address, ?string $venue = null): string
    {
        $venueHtml = $venue === null
            ? ''
            : '<p><strong>Място на провеждане: </strong> <a href="/zali/hall">'.htmlspecialchars($venue, ENT_QUOTES).'</a></p>';
        $addressHtml = $address === null
            ? ''
            : '<p><strong>Адрес: </strong> '.htmlspecialchars($address, ENT_QUOTES).'</p>';

        return '<html><head><meta property="og:image" content="https://sportenkalendar.bg//uploads/events/badge.jpg"/></head><body>'
            .'<nav><a href="/sportove/football">Футбол</a></nav>'
            .'<h1>'.$title.'</h1>'
            .'<p><strong>Начало:</strong> '.$start.' от 09:00 ч.</p>'
            .'<p><strong>Край:</strong> '.$end.' до 16:00 ч.</p>'
            .'<a href="/sportove/badminton">Бадминтон</a>'
            .$venueHtml
            .$addressHtml
            .'<div class="single-event-info-text-block"><p>Мачът е в залата на Берое.</p></div>'
            .'</body></html>';
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
