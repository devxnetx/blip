<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\Reply;
use App\Support\PinPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PinPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_city_filters_the_pin_list_and_a_pin_has_its_own_page(): void
    {
        $here = $this->pin('Концерт на площада', 42.4258, 25.6345, [
            'description' => 'Голям концерт в центъра.',
            'category' => 'event',
            'kind' => Pin::KIND_UPCOMING,
            'starts_at' => now()->addDay(),
            'expires_at' => now()->addDays(2),
        ]);
        $sport = $this->pin('Мач в парка', 42.43, 25.64, [
            'category' => 'sport',
            'image_path' => 'pins/match.jpg',
        ]);
        $far = $this->pin('Далечен пин', 42.4758, 25.6345);
        $sofia = $this->pin('Софийска бележка', 42.6977, 23.3219);
        $secret = Reply::query()->create([
            'pin_id' => $here->id,
            'device_hash' => hash('sha256', 'secret'),
            'message' => 'скрит отговор',
            'author_only' => true,
        ]);
        Reply::query()->create([
            'pin_id' => $here->id,
            'device_hash' => hash('sha256', 'public'),
            'message' => 'публичен отговор',
            'author_only' => false,
        ]);

        $path = PinPage::path($here);
        $this->assertNotNull($path);
        $this->assertStringStartsWith('/pins/stara-zagora/', $path);

        $this->get('/pins')
            ->assertOk()
            ->assertSee('Градове')
            ->assertSee('Стара Загора')
            ->assertSee('Избери град, после пин.')
            ->assertSee('Пинове')
            ->assertSee('class="map-link"', false)
            ->assertSee('id="pin-map"', false);

        $this->get('/pins/stara-zagora')
            ->assertOk()
            ->assertSee($here->message)
            ->assertSee($sport->message)
            ->assertSee($far->message)
            ->assertDontSee($sofia->message)
            ->assertSee('Избери пин от списъка.')
            ->assertSee('Всички')
            ->assertSee('href="'.$path, false)
            ->assertSee('/storage/pins/match.jpg', false);

        $this->get('/pins/stara-zagora?category=sport')
            ->assertOk()
            ->assertSee($sport->message)
            ->assertDontSee($here->message)
            ->assertDontSee($far->message);

        $this->get('/pins/stara-zagora?km=1')
            ->assertOk()
            ->assertSee($here->message)
            ->assertDontSee($far->message);

        $this->get($path)
            ->assertOk()
            ->assertSee('<h1>'.$here->message.'</h1>', false)
            ->assertSee('Голям концерт в центъра.')
            ->assertSee('утре в')
            ->assertDontSee('Предстоящо')
            ->assertSee('публичен отговор')
            ->assertDontSee($secret->message)
            ->assertDontSee($sofia->message)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type": "Event"', false)
            ->assertSee('OfflineEventAttendanceMode', false)
            ->assertSee('data-marked="1"', false)
            ->assertSee('data-zoom="17"', false)
            ->assertSee('class="bubble"', false)
            ->assertSee('border-top: 8px solid #1c1b19', false)
            ->assertSee('Отвори в картата')
            ->assertSee('Копирай връзката към пина')
            ->assertSee('Сподели')
            ->assertSee('class="pin-head"', false)
            ->assertSee('class="pin-foot"', false)
            ->assertSee('grid-template-columns: 200px 460px 420px minmax(380px, 1fr)', false);

        $this->get(PinPage::path($sport))
            ->assertOk()
            ->assertSee('class="bubble"', false)
            ->assertSee('/storage/pins/match.jpg', false);

        $this->get('/pins/stara-zagora/wrong-name-'.$here->id)
            ->assertRedirect($path);

        $this->get('/pins/sofia/'.PinPage::token($here))
            ->assertRedirect($path);

        $this->get(PinPage::path($far))
            ->assertOk()
            ->assertSee('"@type": "Place"', false)
            ->assertDontSee('"@type": "Event"', false);
    }

    public function test_a_phone_is_sent_to_the_map_and_does_not_see_the_pins_button(): void
    {
        $here = $this->pin('Концерт на площада', 42.4258, 25.6345);
        $path = PinPage::path($here);
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
        $googlebot = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.71 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

        $this->get('/pins', ['User-Agent' => $iphone])->assertRedirect(route('map'));
        $this->get('/pins/stara-zagora', ['User-Agent' => $iphone])->assertRedirect(route('map'));
        $this->get($path, ['User-Agent' => $android])->assertRedirect(route('map', ['pin' => $here->id]));
        $this->get($path, ['User-Agent' => $googlebot])->assertOk();

        $this->get('/', ['User-Agent' => $iphone])
            ->assertOk()
            ->assertDontSee('class="pins-link"', false)
            ->assertSee('Виж картата');

        $this->get('/changelog', ['User-Agent' => $iphone])
            ->assertOk()
            ->assertDontSee('href="'.route('pins').'"', false);
    }

    public function test_missing_and_hidden_pins_stay_out_of_the_sitemap(): void
    {
        $live = $this->pin('Жив пин', 42.4258, 25.6345);
        $expired = $this->pin('Стар пин', 42.4258, 25.6345, ['expires_at' => now()->subMinute()]);

        $this->get('/pins/stara-zagora/'.PinPage::token($expired))->assertNotFound();
        $this->get('/pins/no-such-city')->assertNotFound();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('/</loc>', false)
            ->assertSee('/changelog</loc>', false)
            ->assertDontSee('/about</loc>', false)
            ->assertSee('/how</loc>', false)
            ->assertSee('/contact</loc>', false)
            ->assertSee('/support</loc>', false)
            ->assertSee('/map</loc>', false)
            ->assertSee('/pins</loc>', false)
            ->assertSee(PinPage::path($live), false)
            ->assertDontSee(PinPage::token($expired), false)
            ->assertDontSee('/admin', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap:', false)
            ->assertSee('/sitemap.xml', false);
    }

    public function test_upcoming_time_is_relative_and_red_inside_twenty_four_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'Europe/Sofia'));

        try {
            $soon = $this->pin('Скоро концерт', 42.4258, 25.6345, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => now()->addHours(3)->utc(),
                'expires_at' => now()->addHours(4)->utc(),
            ]);
            $later = Carbon::parse('2026-11-27 19:00:00', 'Europe/Sofia')->utc();
            $ahead = $this->pin('След два дни', 42.426, 25.635, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => $later,
                'expires_at' => $later->copy()->addDay(),
            ]);
            $within = $this->pin('Точно дванадесет', 42.427, 25.636, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => now()->addHours(12)->utc(),
                'expires_at' => now()->addHours(13)->utc(),
            ]);
            $edge = $this->pin('Точно двадесет и четири', 42.428, 25.637, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => now()->addHours(24)->utc(),
                'expires_at' => now()->addHours(25)->utc(),
            ]);

            $this->assertTrue(PinPage::startsSoon($soon));
            $this->assertTrue(PinPage::startsSoon($within));
            $this->assertFalse(PinPage::startsSoon($ahead));
            $this->assertFalse(PinPage::startsSoon($edge));
            $this->assertSame('след 3 часа', PinPage::whenLabel($soon));
            $this->assertSame('след 12 часа', PinPage::whenLabel($within));
            $this->assertSame('след 2 дни в 19:00', PinPage::whenLabel($ahead));
            $this->assertSame('утре в 10:00', PinPage::whenLabel($edge));
            $this->assertStringNotContainsString('Предстоящо', PinPage::whenLabel($soon));

            $this->get(PinPage::path($soon))
                ->assertOk()
                ->assertSee('след 3 часа', false)
                ->assertDontSee('след 3 часа в', false)
                ->assertSee('class="soon"', false)
                ->assertDontSee('Предстоящо');

            $this->get(PinPage::path($ahead))
                ->assertOk()
                ->assertSee('след 2 дни в 19:00', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_upcoming_time_follows_the_sofia_calendar_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 22:30:00', 'Europe/Sofia'));

        try {
            $tonight = $this->pin('До полунощ', 42.4258, 25.6345, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => Carbon::parse('2026-11-25 23:10:00', 'Europe/Sofia')->utc(),
                'expires_at' => Carbon::parse('2026-11-26 01:00:00', 'Europe/Sofia')->utc(),
            ]);
            $hour = $this->pin('След час', 42.426, 25.635, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => Carbon::parse('2026-11-25 23:30:00', 'Europe/Sofia')->utc(),
                'expires_at' => Carbon::parse('2026-11-26 01:00:00', 'Europe/Sofia')->utc(),
            ]);
            $tomorrow = $this->pin('След полунощ', 42.427, 25.636, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => Carbon::parse('2026-11-26 01:15:00', 'Europe/Sofia')->utc(),
                'expires_at' => Carbon::parse('2026-11-26 03:00:00', 'Europe/Sofia')->utc(),
            ]);
            $later = $this->pin('След два дни рано', 42.428, 25.637, [
                'kind' => Pin::KIND_UPCOMING,
                'starts_at' => Carbon::parse('2026-11-27 08:00:00', 'Europe/Sofia')->utc(),
                'expires_at' => Carbon::parse('2026-11-27 10:00:00', 'Europe/Sofia')->utc(),
            ]);

            $this->assertSame('след 40 мин', PinPage::whenLabel($tonight));
            $this->assertSame('след 1 час', PinPage::whenLabel($hour));
            $this->assertSame('утре в 01:15', PinPage::whenLabel($tomorrow));
            $this->assertSame('след 2 дни в 08:00', PinPage::whenLabel($later));

            $this->get('/pins/stara-zagora')
                ->assertOk()
                ->assertSee('след 40 мин', false)
                ->assertSee('утре в 01:15', false)
                ->assertSee('след 2 дни в 08:00', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_started_event_shows_the_time_left_in_red(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'Europe/Sofia'));

        try {
            $started = $this->pin('Киномания', 42.4258, 25.6345, [
                'kind' => Pin::KIND_NORMAL,
                'category' => 'event',
                'starts_at' => Carbon::parse('2026-11-17 18:30:00', 'Europe/Sofia')->utc(),
                'duration_minutes' => 12 * 24 * 60,
                'expires_at' => Carbon::parse('2026-11-29 10:55:00', 'Europe/Sofia')->utc(),
            ]);
            $note = $this->pin('Бележка без начало', 42.4262, 25.635, [
                'kind' => Pin::KIND_NORMAL,
            ]);

            $this->assertTrue(PinPage::startsSoon($started));
            $this->assertFalse(PinPage::startsSoon($note));
            $this->assertSame('още 4 дни и 55 мин', PinPage::whenLabel($started));

            $this->get(PinPage::path($started))
                ->assertOk()
                ->assertSee('още 4 дни и 55 мин', false)
                ->assertSee('class="soon"', false);
        } finally {
            Carbon::setTestNow();
        }
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
