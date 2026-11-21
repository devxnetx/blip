<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
use App\Services\AktualniteNovini\Client;
use App\Services\AktualniteNovini\VenueLine;
use App\Services\SportenKalendar\NominatimGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AktualnitenoviniImportTest extends TestCase
{
    use RefreshDatabase;

    private bool $nominatimMiss = false;

    public function test_events_become_pins_and_the_photo_is_saved(): void
    {
        Storage::fake('public');
        $this->fakeFeed();

        Pin::query()->create([
            'device_hash' => hash('sha256', 'already'),
            'message' => 'Стар пин',
            'latitude' => 42.1,
            'longitude' => 24.1,
            'expires_at' => now()->addDay(),
            'source' => 'aktualnitenovini',
            'external_id' => '9004',
        ]);

        $this->artisan('pins:import-aktualnitenovini')
            ->expectsOutputToContain('Upcoming 3, on the map now 1, unassigned 1, skipped 1, hidden 1.')
            ->assertSuccessful();

        $upcoming = Pin::query()->where('external_id', '9001')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $upcoming->kind);
        $this->assertSame('Ирина Флорин – клуб', $upcoming->message);
        $this->assertSame('Концертът е в клуб FOMO.', $upcoming->description);
        $this->assertStringNotContainsString('0885', (string) $upcoming->description);
        $this->assertSame('event', $upcoming->category);
        $this->assertSame('pins/aktualnitenovini-9001.jpg', $upcoming->image_path);
        $this->assertTrue(Storage::disk('public')->exists('pins/aktualnitenovini-9001.jpg'));
        $this->assertSame('https://aktualnitenovini.com/events/irina-florin/', $upcoming->source_url);
        $this->assertSame('00:00', $upcoming->starts_at->timezone('Europe/Sofia')->format('H:i'));
        $this->assertTrue($upcoming->starts_at->isFuture());
        $this->assertTrue($upcoming->expires_at->greaterThan($upcoming->starts_at));
        $this->assertEqualsWithDelta(42.697, $upcoming->latitude, 0.00001);
        $this->assertEqualsWithDelta(23.322, $upcoming->longitude, 0.00001);
        $this->assertTrue($upcoming->isPublic());
        $this->assertTrue($upcoming->isImported());

        $current = Pin::query()->where('external_id', '9002')->firstOrFail();
        $this->assertSame(Pin::KIND_NORMAL, $current->kind);
        $this->assertSame($upcoming->latitude, $current->latitude);
        $this->assertTrue($current->isPublic());

        $unassigned = Pin::query()->where('external_id', '9003')->firstOrFail();
        $this->assertSame(Pin::KIND_UNASSIGNED, $unassigned->kind);
        $this->assertNull($unassigned->latitude);
        $this->assertSame('pins/aktualnitenovini-9003.jpg', $unassigned->image_path);
        $this->assertFalse($unassigned->isPublic());

        $hidden = Pin::query()->where('external_id', '9004')->firstOrFail();
        $this->assertSame('Стар пин', $hidden->message);
        $this->assertTrue($hidden->expires_at->isPast());

        $this->assertNull(Pin::query()->where('external_id', '9005')->first());
        $kept = Pin::query()->where('external_id', '9006')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $kept->kind);
        $this->assertEqualsWithDelta(42.874, $kept->latitude, 0.00001);
        $this->assertTrue($kept->starts_at->lessThan(Setting::importUntil()));

        $offsite = Pin::query()->where('external_id', '9007')->firstOrFail();
        $this->assertSame('https://aktualnitenovini.com/events/bar-hey/', $offsite->source_url);
        $this->assertNull($offsite->image_path);
        $this->assertSame(1, Pin::query()->where('external_id', '9001')->count());

        $category = Category::query()->where('slug', 'event')->firstOrFail();
        $this->assertSame('Събитие', $category->name);
        $this->assertFalse($category->listed);
        $ids = collect($this->getJson('/api/categories')->json('categories'))->pluck('id');
        $this->assertFalse($ids->contains('event'));

        $device = Device::query()->where('device_hash', hash('sha256', 'kostaa-import:aktualnitenovini'))->firstOrFail();
        $this->assertSame('Актуалните новини', $device->label);
        $this->assertSame($device->device_hash, $upcoming->device_hash);

        $run = PinImport::query()->where('source', 'aktualnitenovini')->firstOrFail();
        $this->assertTrue($run->successful);
        $this->assertSame(5, $run->pins_count);
        $this->assertSame(1, $run->unassigned_count);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/wp-json/tribe/events/v1/events')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $until = Setting::importUntil()->timezone('Europe/Sofia')->subDay()->toDateString();
            $agent = $request->header('User-Agent')[0] ?? '';

            return ($query['per_page'] ?? null) === '50'
                && ($query['start_date'] ?? null) === now('Europe/Sofia')->toDateString()
                && ($query['end_date'] ?? null) === $until
                && ($query['status'] ?? null) === 'publish'
                && str_contains($agent, 'KostaaBot/1.0')
                && ! str_contains($agent, 'Mozilla');
        });

        Http::assertSent(function (Request $request) {
            $agent = $request->header('User-Agent')[0] ?? '';

            return str_contains($request->url(), '/wp-content/uploads/2026/09/irina-florin-konczert.jpg')
                && str_contains($agent, 'KostaaBot/1.0')
                && ! str_contains($agent, 'Mozilla');
        });
    }

    public function test_a_second_run_keeps_an_admin_placement_and_a_hidden_pin(): void
    {
        Storage::fake('public');
        $this->fakeFeed();
        $this->artisan('pins:import-aktualnitenovini')->assertSuccessful();

        $placed = Pin::query()->where('external_id', '9001')->firstOrFail();
        $placed->forceFill([
            'latitude' => 43.2,
            'longitude' => 27.9,
            'kind' => Pin::KIND_NORMAL,
            'placed_manually' => true,
        ])->save();

        $hidden = Pin::query()->where('external_id', '9003')->firstOrFail();
        $hidden->forceFill([
            'message' => 'СКРИТ',
            'expires_at' => now(),
            'suppressed_at' => now(),
        ])->save();

        $this->artisan('pins:import-aktualnitenovini')->assertSuccessful();

        $placed->refresh();
        $this->assertSame('Ирина Флорин – клуб', $placed->message);
        $this->assertEqualsWithDelta(43.2, $placed->latitude, 0.00001);
        $this->assertEqualsWithDelta(27.9, $placed->longitude, 0.00001);
        $this->assertSame(Pin::KIND_NORMAL, $placed->kind);
        $this->assertTrue($placed->placed_manually);

        $hidden->refresh();
        $this->assertSame('СКРИТ', $hidden->message);
        $this->assertNotNull($hidden->suppressed_at);
    }

    public function test_a_failed_lookup_keeps_the_previous_point(): void
    {
        Storage::fake('public');
        $this->fakeFeed();
        $this->artisan('pins:import-aktualnitenovini')->assertSuccessful();

        $pin = Pin::query()->where('external_id', '9001')->firstOrFail();
        $latitude = $pin->latitude;
        Cache::flush();
        $this->nominatimMiss = true;

        $this->artisan('pins:import-aktualnitenovini')->assertSuccessful();

        $pin->refresh();
        $this->assertEqualsWithDelta($latitude, $pin->latitude, 0.00001);
        $this->assertEqualsWithDelta(23.322, $pin->longitude, 0.00001);
    }

    public function test_a_missing_event_list_stops_the_import(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://aktualnitenovini.com/*' => Http::response(['total' => 1], 200),
        ]);

        $pin = Pin::query()->create([
            'device_hash' => hash('sha256', 'keep'),
            'message' => 'Остава',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('pins:import-aktualnitenovini')
            ->expectsOutputToContain('did not return the event list')
            ->assertFailed();

        $this->assertNotNull($pin->fresh());
        $run = PinImport::query()->where('source', 'aktualnitenovini')->firstOrFail();
        $this->assertFalse($run->successful);
    }

    public function test_the_import_does_nothing_when_it_is_turned_off(): void
    {
        Setting::put('import_aktualnitenovini_enabled', '0');
        Http::preventStrayRequests();
        Http::fake();

        $this->artisan('pins:import-aktualnitenovini')
            ->expectsOutputToContain('turned off')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, Pin::query()->where('source', 'aktualnitenovini')->count());
    }

    public function test_import_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:import-aktualnitenovini')
            ->assertSuccessful();
    }

    public function test_place_lines_drop_the_phone_and_the_postal_code(): void
    {
        $fomo = VenueLine::place('CLUB FOMO, София, ул. „Позитано“ 1000 / тел. 0885 520 550', null);
        $this->assertSame('CLUB FOMO', $fomo['venue']);
        $this->assertSame('ул. Позитано, София', $fomo['address']);
        $this->assertPlaceHasNo('1000', $fomo['address'], $fomo['venue']);
        $this->assertPlaceHasNo('0885', $fomo['address'], $fomo['venue']);

        $leon = VenueLine::place('LEON EVENT HALL', 'Стара Загора, бул. "бул. Цар Симеон Велики" 105А');
        $this->assertSame('LEON EVENT HALL', $leon['venue']);
        $this->assertStringContainsString('бул. Цар Симеон Велики 105А', $leon['address']);
        $this->assertStringContainsString('Стара Загора', $leon['address']);
        $leonAttempts = NominatimGeocoder::lookupAttempts($leon['address'], $leon['venue']);
        $this->assertSame('Стара Загора', $leonAttempts[0]['city']);
        $this->assertStringContainsString('105', $leonAttempts[0]['street']);

        $club = VenueLine::place('ALLEGRO PREMIUM CLUB, Габрово, ул. „Съзаклятие“ 2 / тел. 0894 004 040', null);
        $attempts = NominatimGeocoder::lookupAttempts($club['address'], $club['venue']);
        $this->assertSame('Съзаклятие 2', $attempts[0]['street']);
        $this->assertSame('Габрово', $attempts[0]['city']);
        $this->assertPlaceHasNo('0894', $club['address'], $club['venue']);

        $bank = VenueLine::place('THE BANK, РУСЕ, ул. „Петко Д. Петков“ 1 / тел. 0877 617 070', null);
        $this->assertSame('THE BANK', $bank['venue']);
        $this->assertStringContainsString('РУСЕ', $bank['address']);
        $bankAttempts = NominatimGeocoder::lookupAttempts($bank['address'], $bank['venue']);
        $this->assertSame('РУСЕ', $bankAttempts[0]['city']);

        $arena = VenueLine::place('Vidas Art Arena, София (Колодрум, Борисова градина)', null);
        $this->assertSame('София', $arena['address']);
        $this->assertStringContainsString('Arena', $arena['venue']);
        $this->assertStringContainsString('Колодрум', $arena['venue']);

        $hall = VenueLine::place('София, НДК, Зала 1, бул „България“ 1', null);
        $this->assertStringContainsString('бул България 1', $hall['address']);
        $this->assertStringContainsString('София', $hall['address']);
        $hallAttempts = NominatimGeocoder::lookupAttempts($hall['address'], $hall['venue']);
        $this->assertSame('България 1', $hallAttempts[0]['street']);
        $this->assertSame('София', $hallAttempts[0]['city']);

        $this->assertNull(VenueLine::place(null, '  '));
        $this->assertNull(VenueLine::place('', null));

        $this->assertTrue(Client::isImageUrl('https://aktualnitenovini.com/wp-content/uploads/2026/09/irina-florin-konczert.jpg'));
        $this->assertTrue(Client::isImageUrl('https://www.aktualnitenovini.com/wp-content/uploads/2026/09/ирина.jpg'));
        $this->assertFalse(Client::isImageUrl('https://aktualnitenovini.com/wp-content/uploads/../secret.jpg'));
        $this->assertFalse(Client::isImageUrl('https://example.com/wp-content/uploads/a.jpg'));
        $this->assertFalse(Client::isImageUrl('https://aktualnitenovini.com/api/photo.jpg'));
    }

    private function assertPlaceHasNo(string $needle, string $address, string $venue): void
    {
        $attempts = NominatimGeocoder::lookupAttempts($address, $venue);

        foreach ($attempts as $attempt) {
            $blob = (string) json_encode($attempt, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString($needle, $blob);
        }
    }

    private function fakeFeed(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org')) {
                return $this->nominatim($request);
            }

            if (str_contains($url, '/wp-content/uploads/')) {
                return Http::response($this->jpegBytes(), 200, ['Content-Type' => 'image/jpeg']);
            }

            if (str_contains($url, '/wp-json/tribe/events/v1/events')) {
                return Http::response([
                    'events' => $this->events(),
                    'total' => count($this->events()),
                    'total_pages' => 1,
                ]);
            }

            return Http::response('missing', 404);
        });
    }

    private function nominatim(Request $request)
    {
        if ($this->nominatimMiss) {
            return Http::response([]);
        }

        $street = (string) ($request->data()['street'] ?? '');
        $city = (string) ($request->data()['city'] ?? '');
        $query = (string) ($request->data()['q'] ?? '');
        $blob = $street.' '.$city.' '.$query;

        if (str_contains($blob, 'Съзаклятие')) {
            return Http::response([[
                'lat' => '42.874',
                'lon' => '25.318',
                'address' => ['house_number' => '2', 'road' => 'ул. Съзаклятие', 'city' => 'Габрово'],
            ]]);
        }

        if (str_contains($blob, 'Палах')) {
            return Http::response([[
                'lat' => '43.214',
                'lon' => '27.914',
                'address' => ['house_number' => '10', 'road' => 'ул. Ян Палах', 'city' => 'Варна'],
            ]]);
        }

        if (str_contains($blob, 'FOMO') || str_contains($blob, 'Позитано')) {
            return Http::response([[
                'lat' => '42.697',
                'lon' => '23.322',
                'address' => ['amenity' => 'CLUB FOMO', 'road' => 'ул. Позитано', 'city' => 'София'],
            ]]);
        }

        return Http::response([]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(): array
    {
        $tomorrow = now('Europe/Sofia')->addDay()->startOfDay();
        $today = now('Europe/Sofia')->startOfDay();
        $yesterday = now('Europe/Sofia')->subDay()->startOfDay();
        $edge = Setting::importUntil();
        $inside = Setting::importUntil()->copy()->subDays(2);

        return [
            $this->event('9001', 'Ирина Флорин &#8211; клуб', $tomorrow, $tomorrow->copy()->setTime(23, 59, 0), 'CLUB FOMO, София, ул. „Позитано“ 1000 / тел. 0885 520 550', null, 'https://aktualnitenovini.com/events/irina-florin/', 'irina-florin', '<p>Концертът е в <b>клуб FOMO</b>.</p>', 'https://aktualnitenovini.com/wp-content/uploads/2026/09/irina-florin-konczert.jpg'),
            $this->event('9001', 'Ирина Флорин &#8211; клуб', $tomorrow, $tomorrow->copy()->setTime(23, 59, 0), 'CLUB FOMO, София, ул. „Позитано“ 1000 / тел. 0885 520 550', null, 'https://aktualnitenovini.com/events/irina-florin/', 'irina-florin', '<p>Концертът е в <b>клуб FOMO</b>.</p>', 'https://aktualnitenovini.com/wp-content/uploads/2026/09/irina-florin-konczert.jpg'),
            $this->event('9002', 'Джена', $today, $today->copy()->endOfDay(), 'CLUB FOMO, София, ул. „Позитано“ 1000 / тел. 0885 520 550', null, 'https://aktualnitenovini.com/events/dzhena/', 'dzhena', '', null),
            $this->event('9003', 'Светла Дукатева', $tomorrow, $tomorrow->copy()->setTime(23, 59, 0), null, null, 'https://aktualnitenovini.com/events/svetla/', 'svetla', '', 'https://aktualnitenovini.com/wp-content/uploads/2026/03/svetla.png'),
            $this->event('9004', 'Приключило', $yesterday, $yesterday->copy()->setTime(23, 59, 0), 'CLUB FOMO, София, ул. „Позитано“ 1000', null, 'https://aktualnitenovini.com/events/ended/', 'ended', '', null),
            $this->event('9005', 'Твърде напред', $edge, $edge->copy()->addDay(), 'CLUB FOMO, София, ул. „Позитано“ 1000', null, 'https://aktualnitenovini.com/events/later/', 'later', '', null),
            $this->event('9006', 'Алисия', $inside, $inside->copy()->addHours(23), 'ALLEGRO PREMIUM CLUB, Габрово, ул. „Съзаклятие“ 2 / тел. 0894 004 040', null, 'https://aktualnitenovini.com/events/alisiya/', 'alisiya', '', null),
            $this->event('9007', 'Бар', $tomorrow, $tomorrow->copy()->setTime(23, 59, 0), 'BAR HEY, Варна, ул. „Ян Палах“ 10 / тел. 0885 099 580', null, 'https://evil.example/events/bar-hey/', 'bar-hey', '', 'https://evil.example/photo.jpg'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $id, string $title, \Illuminate\Support\Carbon $start, \Illuminate\Support\Carbon $end, ?string $venue, ?string $address, string $url, string $slug, string $description, ?string $image): array
    {
        return [
            'id' => (int) $id,
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'utc_start_date' => $start->copy()->utc()->format('Y-m-d H:i:s'),
            'utc_end_date' => $end->copy()->utc()->format('Y-m-d H:i:s'),
            'url' => $url,
            'venue' => [
                'venue' => $venue,
                'address' => $address,
            ],
            'image' => $image === null ? null : ['url' => $image],
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
