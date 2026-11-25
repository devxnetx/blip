<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\Setting;
use App\Support\PinPage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordLiveTest extends TestCase
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

    public function test_a_city_message_lists_what_is_running_and_what_starts_soon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 16:10:00', 'Europe/Sofia'));

        try {
            $running = $this->pin('Киномания', 42.4258, 25.6345, [
                'starts_at' => now()->subDays(2),
                'expires_at' => now()->addDays(4),
            ]);
            $soon = $this->pin('Концерт довечера', 42.426, 25.635, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => now()->addHours(2),
                'expires_at' => now()->addHours(5),
            ]);
            $this->pin('Далечен мач', 42.427, 25.636, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => now()->addHours(5),
                'expires_at' => now()->addHours(8),
            ]);
            $this->pin('Обикновена бележка', 42.4258, 25.6345);
            $this->pin('Скрит концерт', 42.4262, 25.6352, [
                'starts_at' => now()->subHour(),
                'expires_at' => now()->addHours(2),
                'suppressed_at' => now(),
            ]);
            $this->pin('Софийско сега', 42.6977, 23.3219, [
                'starts_at' => now()->subHour(),
                'expires_at' => now()->addHours(2),
            ]);

            $this->artisan('pins:discord-live', ['city' => 'stara-zagora'])
                ->expectsOutputToContain('POSTED Стара Загора 2')
                ->assertSuccessful();

            $this->assertSame('още 4 дни', PinPage::whenLabel($running));
            $this->assertSame('след 2 часа', PinPage::whenLabel($soon));

            Http::assertSent(function ($request) use ($running, $soon): bool {
                if (! str_contains($request->url(), '/channels/111/messages')) {
                    return false;
                }

                return $request['content'] === "**В момента в** [Стара Загора](<https://kostaa.space/map?city=stara-zagora>)\n"
                    ."Киномания · още 4 дни · [повече информация](<https://kostaa.space/map?pin={$running->id}>)\n"
                    ."\n"
                    ."**Предстоящи до 3 часа**\n"
                    ."Концерт довечера · след 2 часа · [повече информация](<https://kostaa.space/map?pin={$soon->id}>)";
            });

            Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/channels/222/messages'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_ahead_window_comes_from_the_admin_setting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 16:10:00', 'Europe/Sofia'));
        Setting::put('discord_live_ahead_hours', '6');

        try {
            $soon = $this->pin('Мач след пет часа', 42.4258, 25.6345, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => now()->addHours(5),
                'expires_at' => now()->addHours(8),
            ]);

            $this->artisan('pins:discord-live', ['city' => 'stara-zagora'])
                ->assertSuccessful();

            Http::assertSent(function ($request) use ($soon): bool {
                if (! str_contains($request->url(), '/channels/111/messages')) {
                    return false;
                }

                return $request['content'] === "**В момента в** [Стара Загора](<https://kostaa.space/map?city=stara-zagora>)\n"
                    ."Няма събития.\n"
                    ."\n"
                    ."**Предстоящи до 6 часа**\n"
                    ."Мач след пет часа · след 5 часа · [повече информация](<https://kostaa.space/map?pin={$soon->id}>)";
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_empty_cities_are_skipped_until_the_clock_matches(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'Europe/Sofia'));

        try {
            $this->artisan('pins:discord-live')
                ->expectsOutputToContain('SKIP hour')
                ->assertSuccessful();

            Http::assertNothingSent();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_every_city_with_events_is_posted_on_the_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 15:00:00', 'Europe/Sofia'));

        try {
            $this->pin('Жив концерт', 42.4258, 25.6345, [
                'starts_at' => now()->subHour(),
                'expires_at' => now()->addHour(),
            ]);

            $this->artisan('pins:discord-live')
                ->expectsOutputToContain('POSTED Стара Загора 1')
                ->expectsOutputToContain('SKIP София')
                ->assertSuccessful();

            Http::assertSent(fn ($request): bool => str_contains($request->url(), '/channels/111/messages'));
            Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/channels/222/messages'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_live_reminder_is_checked_every_hour(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:discord-live')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'pins:discord-live'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertSame('Europe/Sofia', $event->timezone);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pin(string $message, float $latitude, float $longitude, array $extra = []): Pin
    {
        return Pin::query()->create([
            'device_hash' => hash('sha256', $message.$latitude.($extra['starts_at'] ?? '')),
            'message' => $message,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'expires_at' => now()->addHour(),
            ...$extra,
        ]);
    }
}
