<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
use App\Services\EventiBg\DayClient;
use App\Services\EventiBg\DayParser;
use App\Services\EventiBg\PlaceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EventibgImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_pages_become_upcoming_current_or_unassigned_pins(): void
    {
        Http::preventStrayRequests();
        $title = str_repeat('Събор ', 20);
        Http::fake(fn (Request $request) => $this->response($request, $title));

        $this->artisan('pins:import-eventibg', ['--days' => 2])->assertSuccessful();

        $upcoming = Pin::query()->where('external_id', 'sabor-malevo')->firstOrFail();
        $this->assertSame(Pin::KIND_UPCOMING, $upcoming->kind);
        $this->assertSame(mb_substr($title, 0, 80), $upcoming->message);
        $this->assertSame(80, mb_strlen($upcoming->message));
        $this->assertEqualsWithDelta(42.15, $upcoming->latitude, 0.00001);
        $this->assertEqualsWithDelta(24.75, $upcoming->longitude, 0.00001);
        $this->assertSame('https://eventibg.com/event/sabor-malevo', $upcoming->source_url);
        $this->assertSame('event', $upcoming->category);
        $this->assertSame('note ]; still an event', $upcoming->description);
        $this->assertSame('pins/eventibg-sabor-malevo.jpg', $upcoming->image_path);
        $this->assertTrue($upcoming->isImported());
        $this->assertSame('18:00', $upcoming->starts_at->timezone('Europe/Sofia')->format('H:i'));
        $this->assertSame('22:00', $upcoming->expires_at->timezone('Europe/Sofia')->format('H:i'));
        $this->assertTrue($upcoming->isPublic());

        $sameTown = Pin::query()->where('external_id', 'koleda-plovdiv')->firstOrFail();
        $this->assertSame($upcoming->latitude, $sameTown->latitude);
        $this->assertSame($upcoming->longitude, $sameTown->longitude);
        $this->assertTrue($sameTown->isPublic());

        $running = Pin::query()->where('external_id', 'veche-zapochnalo')->firstOrFail();
        $this->assertSame(Pin::KIND_NORMAL, $running->kind);
        $this->assertTrue($running->isPublic());

        $unassigned = Pin::query()->where('external_id', 'bez-karta')->firstOrFail();
        $this->assertSame(Pin::KIND_UNASSIGNED, $unassigned->kind);
        $this->assertNull($unassigned->latitude);
        $this->assertFalse($unassigned->isPublic());

        $this->assertNull(Pin::query()->where('external_id', 'prikluchilo')->first());
        $this->assertSame(1, Pin::query()->where('external_id', 'sabor-malevo')->count());

        $device = Device::query()->where('device_hash', hash('sha256', 'kostaa-import:eventibg'))->firstOrFail();
        $this->assertSame('EventiBG', $device->label);
        $this->assertSame($device->device_hash, $upcoming->device_hash);

        $run = PinImport::query()->where('source', 'eventibg')->firstOrFail();
        $this->assertTrue($run->successful);
        $this->assertSame(4, $run->pins_count);
        $this->assertSame(1, $run->unassigned_count);
        $this->assertNotNull($run->finished_at);

        Http::assertSent(function (Request $request) {
            $agent = $request->header('User-Agent')[0] ?? '';

            return str_contains($request->url(), '/uploads/events/nope.jpg')
                && str_contains($agent, 'Mozilla')
                && ! str_contains($agent, 'KostaaBot');
        });
    }

    public function test_a_second_run_updates_the_same_event_and_keeps_admin_choices(): void
    {
        Http::preventStrayRequests();
        $title = 'Първо име';
        Http::fake(function (Request $request) use (&$title) {
            return $this->response($request, $title);
        });

        $this->artisan('pins:import-eventibg', ['--days' => 1])->assertSuccessful();

        $placed = Pin::query()->where('external_id', 'sabor-malevo')->firstOrFail();
        $placed->forceFill([
            'latitude' => 43.2,
            'longitude' => 27.9,
            'kind' => Pin::KIND_NORMAL,
            'placed_manually' => true,
        ])->save();

        $hidden = Pin::query()->where('external_id', 'bez-karta')->firstOrFail();
        $hidden->forceFill([
            'message' => 'СКРИТ',
            'expires_at' => now(),
            'suppressed_at' => now(),
        ])->save();

        $title = 'Ново име';
        $this->artisan('pins:import-eventibg', ['--days' => 1])->assertSuccessful();

        $placed->refresh();
        $this->assertSame('Ново име', $placed->message);
        $this->assertEqualsWithDelta(43.2, $placed->latitude, 0.00001);
        $this->assertSame(Pin::KIND_NORMAL, $placed->kind);
        $this->assertTrue($placed->placed_manually);

        $hidden->refresh();
        $this->assertSame('СКРИТ', $hidden->message);
        $this->assertNotNull($hidden->suppressed_at);
        $this->assertSame(1, Pin::query()->where('external_id', 'sabor-malevo')->count());
    }

    public function test_a_slow_down_stops_the_import_and_keeps_pins_already_saved(): void
    {
        Http::preventStrayRequests();
        $hits = 0;
        Http::fake(function (Request $request) use (&$hits) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            if (str_starts_with($path, '/uploads/')) {
                return Http::response('missing', 404);
            }

            $hits++;
            if ($hits > 1) {
                return Http::response('slow', 503);
            }

            $day = now('Europe/Sofia')->addDays(5)->format('Y-m-d');

            return Http::response($this->page([$this->row('sabor-malevo', 'Събор', 42.15, 24.75, $day, $day)]));
        });

        $this->artisan('pins:import-eventibg', ['--days' => 2])->assertFailed();

        $this->assertNotNull(Pin::query()->where('external_id', 'sabor-malevo')->first());
        $run = PinImport::query()->where('source', 'eventibg')->firstOrFail();
        $this->assertFalse($run->successful);
        $this->assertSame(1, $run->pins_count);
        $this->assertStringContainsString('slow down', (string) $run->error);
    }

    public function test_photos_and_other_sites_are_never_requested(): void
    {
        Http::preventStrayRequests();
        $client = app(DayClient::class);

        foreach ([
            'https://eventibg.com/uploads/events/a.jpg',
            'https://example.com/event/01/01/2026',
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

    public function test_the_day_list_survives_a_bracket_inside_the_description(): void
    {
        $html = $this->page([
            [
                'slug' => 'parvi',
                'name' => 'Първи',
                'lat' => 42.1,
                'lng' => 24.7,
                'start' => '2026-12-05T00:00:00+00:00',
                'end' => '2026-12-05T00:00:00+00:00',
                'open_time' => '20:00:00',
                'close_time' => '12:00:00',
                'description' => 'wait ]; still here',
                'image' => '/uploads/events/nope.jpg',
            ],
            [
                'slug' => 'vtori',
                'name' => 'Втори',
                'lat' => null,
                'lng' => null,
                'start' => '2026-12-06T00:00:00+00:00',
                'end' => '2026-12-06T00:00:00+00:00',
                'open_time' => null,
                'close_time' => null,
                'description' => 'plain',
            ],
        ]);

        $events = (new DayParser)->parse($html);

        $this->assertCount(2, $events);
        $this->assertSame('parvi', $events[0]->slug);
        $this->assertSame('wait ]; still here', $events[0]->description);
        $this->assertSame('https://eventibg.com/uploads/events/nope.jpg', $events[0]->imageUrl);
        $this->assertSame('2026-12-05 18:00:00', $events[0]->startsAt->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-06 10:00:00', $events[0]->endsAt->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('vtori', $events[1]->slug);
        $this->assertNull($events[1]->latitude);
        $this->assertSame('2026-12-06 21:59:00', $events[1]->endsAt->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_named_hall_replaces_the_town_coordinate(): void
    {
        $place = PlaceLine::fromDescription("Място: Кино-театър „Освобождение“, бул. „Княгиня Мария Луиза“, София\nКатегория: Фолклор");
        $this->assertSame('Кино-театър „Освобождение“', $place['venue']);
        $this->assertSame('бул. „Княгиня Мария Луиза“, София', $place['address']);

        Http::preventStrayRequests();
        $future = now('Europe/Sofia')->addDays(3)->format('Y-m-d');
        Http::fake(function (Request $request) use ($future) {
            $url = $request->url();

            if (str_contains($url, 'nominatim.openstreetmap.org')) {
                $query = (string) ($request->data()['q'] ?? '');
                if (str_contains($query, 'Освобождение') && str_contains($query, 'Мария Луиза')) {
                    return Http::response([[
                        'lat' => '42.7128141',
                        'lon' => '23.3174830',
                        'category' => 'amenity',
                        'type' => 'theatre',
                        'address' => ['amenity' => 'Театър Освобождение', 'road' => 'бул. Княгиня Мария Луиза', 'city' => 'София'],
                    ]]);
                }

                return Http::response([]);
            }

            if (! preg_match('#/event/\d{2}/\d{2}/\d{4}$#', $url)) {
                return Http::response('missing', 404);
            }

            return Http::response($this->page([
                $this->row('kanatitsa', 'Юбилеен концерт', 42.697696, 23.321744, $future, $future, '18:30:00', '21:00:00', 'Място: Кино-театър „Освобождение“, бул. „Княгиня Мария Луиза“, София'),
                $this->row('bez-miasto', 'Само град', 42.697696, 23.321744, $future, $future, '18:00:00', '22:00:00', 'Само град'),
            ]));
        });

        $this->artisan('pins:import-eventibg', ['--days' => 1])->assertSuccessful();

        $hall = Pin::query()->where('external_id', 'kanatitsa')->firstOrFail();
        $this->assertEqualsWithDelta(42.7128141, $hall->latitude, 0.00001);
        $this->assertEqualsWithDelta(23.317483, $hall->longitude, 0.00001);

        $town = Pin::query()->where('external_id', 'bez-miasto')->firstOrFail();
        $this->assertEqualsWithDelta(42.697696, $town->latitude, 0.00001);
        $this->assertEqualsWithDelta(23.321744, $town->longitude, 0.00001);
    }

    public function test_a_turned_off_import_does_nothing(): void
    {
        Http::preventStrayRequests();
        Setting::put('import_eventibg_enabled', '0');

        $this->artisan('pins:import-eventibg')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, Pin::query()->count());
        $this->assertSame(0, PinImport::query()->count());
    }

    public function test_the_day_window_follows_the_setting(): void
    {
        Http::preventStrayRequests();
        Setting::put('import_days_ahead', '1');
        $hits = 0;
        Http::fake(function (Request $request) use (&$hits) {
            $hits++;

            return Http::response('<html></html>');
        });

        $this->artisan('pins:import-eventibg')->assertSuccessful();

        $this->assertSame(1, $hits);
    }

    public function test_import_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:import-eventibg')
            ->assertSuccessful();
    }

    private function response(Request $request, string $title)
    {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

        if (str_starts_with($path, '/uploads/events/')) {
            return Http::response($this->jpegBytes(), 200, ['Content-Type' => 'image/jpeg']);
        }

        if (! preg_match('#^/event/\d{2}/\d{2}/\d{4}$#', $path)) {
            return Http::response('missing', 404);
        }

        $future = now('Europe/Sofia')->addDays(5)->format('Y-m-d');
        $after = now('Europe/Sofia')->addDays(6)->format('Y-m-d');
        $yesterday = now('Europe/Sofia')->subDay()->format('Y-m-d');
        $tomorrow = now('Europe/Sofia')->addDay()->format('Y-m-d');
        $past = now('Europe/Sofia')->subDays(4)->format('Y-m-d');

        return Http::response($this->page([
            $this->row('sabor-malevo', $title, 42.15, 24.75, $future, $after),
            $this->row('koleda-plovdiv', 'Коледа в Пловдив', 42.15, 24.75, $future, $after),
            $this->row('veche-zapochnalo', 'Вече започнало', 43.2, 27.9, $yesterday, $tomorrow, '10:00:00', '22:00:00'),
            $this->row('bez-karta', 'Без карта', null, null, $future, $after),
            $this->row('prikluchilo', 'Приключило', 42.7, 23.3, $past, $past),
        ]));
    }

    /**
     * @param  array<string, mixed>  $rows
     */
    private function page(array $rows): string
    {
        return '<html><script>const DAY_EVENTS = '.json_encode($rows, JSON_UNESCAPED_UNICODE).';</script></html>';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $slug, string $name, ?float $lat, ?float $lng, string $start, string $end, string $open = '18:00:00', string $close = '22:00:00', string $description = 'note ]; still an event'): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'city' => 'Пловдив',
            'lat' => $lat,
            'lng' => $lng,
            'open_time' => $open,
            'close_time' => $close,
            'start' => $start.'T00:00:00+00:00',
            'end' => $end.'T00:00:00+00:00',
            'url' => '/event/'.$slug,
            'description' => $description,
            'image' => $description === 'note ]; still an event' ? '/uploads/events/nope.jpg' : null,
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
