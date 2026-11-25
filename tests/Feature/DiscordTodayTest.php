<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\User;
use App\Services\Discord\CityNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscordTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://kostaa.space',
            'blip.discord_bot_token' => 'test-token',
            'blip.discord_guild_id' => '1574875809603133562',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_ends_with($url, '/channels')) {
                return Http::response([
                    ['id' => '111', 'type' => 0, 'name' => 'стара-загора'],
                    ['id' => '222', 'type' => 0, 'name' => 'софия'],
                ]);
            }

            if (str_contains($url, '/messages')) {
                return Http::response(['id' => '999'], 200);
            }

            return Http::response([], 404);
        });
    }

    public function test_today_lists_titles_and_the_filtered_city_link(): void
    {
        $concert = $this->pin('Концерт на площада', 42.4258, 25.6345, [
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addHours(2),
        ]);
        $this->pin('Утрешен мач', 42.43, 25.64, [
            'starts_at' => now()->addDay(),
            'expires_at' => now()->addDay()->addHours(2),
        ]);
        $this->pin('Софийска бележка', 42.6977, 23.3219);
        $this->pin('Скрит концерт', 42.426, 25.635, [
            'starts_at' => now()->subMinutes(30),
            'expires_at' => now()->addHour(),
            'suppressed_at' => now(),
        ]);

        $this->artisan('pins:discord-today', ['city' => 'stara-zagora'])
            ->expectsOutputToContain('POSTED Стара Загора 1')
            ->assertSuccessful();

        Http::assertSent(function ($request) use ($concert): bool {
            if (! str_contains($request->url(), '/channels/111/messages')) {
                return false;
            }

            return $request['content'] === "**Събития днес в** [Стара Загора](<https://kostaa.space/map?city=stara-zagora&period=today>)\n"
                ."Концерт на площада · [повече информация](<https://kostaa.space/map?pin={$concert->id}>)";
        });
    }

    public function test_the_map_can_filter_pins_to_today(): void
    {
        $this->pin('Концерт на площада', 42.4258, 25.6345, [
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addHours(2),
        ]);
        $this->pin('Утрешен мач', 42.43, 25.64, [
            'starts_at' => now()->addDay(),
            'expires_at' => now()->addDay()->addHours(2),
        ]);

        $bounds = [
            'north' => 42.6,
            'south' => 42.2,
            'east' => 25.9,
            'west' => 25.4,
        ];

        $this->getJson('/api/pins?'.http_build_query($bounds + ['period' => 'today']))
            ->assertOk()
            ->assertJsonCount(1, 'pins')
            ->assertJsonPath('pins.0.message', 'Концерт на площада');

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonCount(2, 'pins');

        $this->get('/map?city=stara-zagora&period=today')
            ->assertOk()
            ->assertSee('id="period-today"', false)
            ->assertSee('stara-zagora', false);
    }

    public function test_a_device_pin_is_announced_and_an_import_is_not(): void
    {
        $created = $this->withHeader('X-Device-Id', (string) Str::uuid())->postJson('/api/pins', [
            'message' => 'Жива бележка',
            'latitude' => 42.4258,
            'longitude' => 25.6345,
            'duration' => '1h',
        ])->assertCreated();

        $id = $created->json('id');
        $when = Pin::query()->findOrFail($id)->created_at->timezone('Europe/Sofia')->format('d.m, H:i');

        Http::assertSent(function ($request) use ($id, $when): bool {
            if (! str_contains($request->url(), '/channels/111/messages')) {
                return false;
            }

            return $request['content'] === "New pin was created: Жива бележка\n"
                .$when."\n"
                .'https://kostaa.space/map?pin='.$id;
        });

        $posts = fn () => collect(Http::recorded())
            ->filter(fn (array $pair) => str_contains($pair[0]->url(), '/messages'))
            ->count();
        $before = $posts();

        $imported = $this->pin('Внесен концерт', 42.4258, 25.6345, [
            'source' => 'facebook',
            'external_id' => 'fb-1',
        ]);
        $this->assertFalse(app(CityNotifier::class)->announcePin($imported));
        $this->assertSame($before, $posts());

        $this->withHeader('X-Device-Id', (string) Str::uuid())->postJson('/api/pins', [
            'message' => 'Извън градовете',
            'latitude' => 0,
            'longitude' => 0,
            'duration' => '1h',
        ])->assertCreated();

        $this->assertSame($before, $posts());
    }

    public function test_a_pin_dropped_on_the_map_is_announced(): void
    {
        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();

        $created = $this->actingAs($admin)->postJson('/admin/pins', [
            'message' => 'Пин от картата',
            'latitude' => 42.4258,
            'longitude' => 25.6345,
            'duration' => '1h',
            'category' => 'other',
        ])->assertCreated();

        $id = $created->json('id');
        $when = Pin::query()->findOrFail($id)->created_at->timezone('Europe/Sofia')->format('d.m, H:i');

        Http::assertSent(function ($request) use ($id, $when): bool {
            if (! str_contains($request->url(), '/channels/111/messages')) {
                return false;
            }

            return $request['content'] === "New pin was created: Пин от картата\n"
                .$when."\n"
                .'https://kostaa.space/map?pin='.$id;
        });
    }

    public function test_the_city_reminder_runs_each_morning(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:discord-today')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'pins:discord-today'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Europe/Sofia', $event->timezone);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pin(string $message, float $latitude, float $longitude, array $extra = []): Pin
    {
        return Pin::query()->create([
            'device_hash' => hash('sha256', $message.$latitude),
            'message' => $message,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'expires_at' => now()->addHour(),
            ...$extra,
        ]);
    }
}
