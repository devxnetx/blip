<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Support\PinPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingHorizonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_crowded_city_loads_only_the_next_twenty_days(): void
    {
        $near = $this->event('Скоро в София', 42.6977, 23.3219, now()->addDays(10));
        $far = $this->event('След пет месеца', 42.698, 23.322, now()->addMonths(5));
        $note = $this->note('Бележка на площада', 42.6977, 23.3219);
        $varna = $this->event('Далечен концерт във Варна', 43.2141, 27.9147, now()->addMonths(5));

        foreach (range(1, 50) as $index) {
            $this->event('Пълнеж '.$index, 42.6977, 23.322, now()->addDays(3));
        }

        $sofia = [
            'north' => 42.8,
            'south' => 42.6,
            'east' => 23.5,
            'west' => 23.2,
        ];

        $map = $this->getJson('/api/pins?'.http_build_query($sofia))->assertOk();
        $this->assertStringContainsString('private', (string) $map->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $map->headers->get('cache-control'));
        $map->assertJsonFragment(['message' => $near->message])
            ->assertJsonFragment(['message' => $note->message])
            ->assertJsonMissing(['message' => $far->message]);

        $this->getJson('/api/pins?'.http_build_query($sofia))
            ->assertOk()
            ->assertJsonMissing(['message' => $far->message]);

        $this->getJson('/api/pins?'.http_build_query([
            'north' => 43.4,
            'south' => 43.0,
            'east' => 28.1,
            'west' => 27.7,
        ]))
            ->assertOk()
            ->assertJsonFragment(['message' => $varna->message]);

        $this->getJson('/api/pins/'.$far->id)
            ->assertOk()
            ->assertJsonPath('pin.message', $far->message);

        $this->get(PinPage::path($near))
            ->assertOk()
            ->assertSee($near->message)
            ->assertDontSee($far->message);

        $this->get(PinPage::path($far))
            ->assertOk()
            ->assertSee($far->message)
            ->assertSee($near->message);
    }

    public function test_the_desktop_map_asks_for_events_within_ten_days(): void
    {
        $soon = $this->event('До десет дни', 42.6977, 23.3219, now()->addDays(9));
        $edge = $this->event('Точно на десетия ден', 42.698, 23.322, now()->addDays(10));
        $running = $this->event('Вече върви', 42.6975, 23.3215, now()->subDay());
        $note = $this->note('Бележка сега', 42.6976, 23.3218);
        $later = $this->event('След единадесет дни', 42.699, 23.323, now()->addDays(11));
        $bounds = [
            'north' => 42.8,
            'south' => 42.6,
            'east' => 23.5,
            'west' => 23.2,
            'ahead' => 10,
        ];

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonFragment(['message' => $soon->message])
            ->assertJsonFragment(['message' => $edge->message])
            ->assertJsonFragment(['message' => $running->message])
            ->assertJsonFragment(['message' => $note->message])
            ->assertJsonMissing(['message' => $later->message]);

        unset($bounds['ahead']);

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonFragment(['message' => $later->message]);
    }

    public function test_fifty_upcoming_events_still_show_the_later_ones(): void
    {
        $far = $this->event('Още се вижда', 42.6977, 23.3219, now()->addMonths(5));

        foreach (range(1, 49) as $index) {
            $this->event('Пълнеж '.$index, 42.6977, 23.322, now()->addDays(3));
        }

        $this->getJson('/api/pins?'.http_build_query([
            'north' => 42.8,
            'south' => 42.6,
            'east' => 23.5,
            'west' => 23.2,
        ]))
            ->assertOk()
            ->assertJsonFragment(['message' => $far->message]);
    }

    private function event(string $message, float $latitude, float $longitude, \DateTimeInterface $starts): Pin
    {
        return Pin::query()->create([
            'device_hash' => hash('sha256', $message.$latitude.$longitude.$starts->getTimestamp()),
            'message' => $message,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'kind' => Pin::KIND_UPCOMING,
            'starts_at' => $starts,
            'expires_at' => now()->addYear(),
        ]);
    }

    private function note(string $message, float $latitude, float $longitude): Pin
    {
        return Pin::query()->create([
            'device_hash' => hash('sha256', $message),
            'message' => $message,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'expires_at' => now()->addHour(),
        ]);
    }
}
