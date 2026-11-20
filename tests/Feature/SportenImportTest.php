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
        $this->assertSame('badminton', $street->category);
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

        $sport = Category::query()->where('slug', 'badminton')->firstOrFail();
        $this->assertFalse($sport->listed);
        $this->assertSame('Бадминтон', $sport->name);

        $device = Device::query()->where('device_hash', hash('sha256', 'kostaa-import:sportenkalendar'))->firstOrFail();
        $this->assertSame('Спортен календар', $device->label);

        $run = PinImport::query()->where('source', 'sportenkalendar')->firstOrFail();
        $this->assertTrue($run->successful);
        $this->assertSame(4, $run->pins_count);
        $this->assertSame(2, $run->unassigned_count);
        $this->assertNotNull($run->finished_at);

        $map = $this->get('/map');
        $map->assertOk()
            ->assertDontSee('data-category="badminton"', false)
            ->assertSee('badminton', false);

        $ids = collect($this->getJson('/api/categories')->json('categories'))->pluck('id');
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
            .'<p><strong>Адрес: </strong> Стара Загора, ул.&quot;Цар Иван Шишман&quot; № 60</p>'
            .'<div class="single-event-info-text-block"><p>Двудневен турнир за ветерани.</p></div>';

        $event = (new EventPageParser)->parse($html, 'https://sportenkalendar.bg/sybitie/d-rzaven-licen-sampionat-veterani-7766');

        $this->assertNotNull($event);
        $this->assertSame('7766', $event->externalId);
        $this->assertSame('Държавен личен шампионат - ветерани', $event->title);
        $this->assertSame('badminton', $event->sportSlug);
        $this->assertSame('Бадминтон', $event->sportName);
        $this->assertStringContainsString('Шишман', (string) $event->address);
        $this->assertSame('https://sportenkalendar.bg/uploads/events/badge.jpg', $event->imageUrl);
        $this->assertSame('Двудневен турнир за ветерани.', $event->description);
        $this->assertTrue($event->endsAt->greaterThan($event->startsAt));
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
        $this->assertNull(NominatimGeocoder::structuredQuery('Люлин планина, Бонсови поляни'));
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

    private function eventPage(string $title, string $start, string $end, ?string $address): string
    {
        $addressHtml = $address === null
            ? ''
            : '<p><strong>Адрес: </strong> '.htmlspecialchars($address, ENT_QUOTES).'</p>';

        return '<html><head><meta property="og:image" content="https://sportenkalendar.bg//uploads/events/badge.jpg"/></head><body>'
            .'<nav><a href="/sportove/football">Футбол</a></nav>'
            .'<h1>'.$title.'</h1>'
            .'<p><strong>Начало:</strong> '.$start.' от 09:00 ч.</p>'
            .'<p><strong>Край:</strong> '.$end.' до 16:00 ч.</p>'
            .'<a href="/sportove/badminton">Бадминтон</a>'
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
