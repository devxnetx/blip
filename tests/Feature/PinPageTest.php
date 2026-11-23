<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\Reply;
use App\Support\PinPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $sport = $this->pin('Мач в парка', 42.43, 25.64, ['category' => 'sport']);
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
            ->assertSee('href="'.$path, false);

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
            ->assertSee('Предстоящо')
            ->assertSee('публичен отговор')
            ->assertDontSee($secret->message)
            ->assertDontSee($sofia->message)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type": "Event"', false)
            ->assertSee('OfflineEventAttendanceMode', false)
            ->assertSee('data-marked="1"', false)
            ->assertSee('Отвори в картата');

        $this->get('/pins/stara-zagora/wrong-name-'.$here->id)
            ->assertRedirect($path);

        $this->get('/pins/sofia/'.PinPage::token($here))
            ->assertRedirect($path);

        $this->get(PinPage::path($far))
            ->assertOk()
            ->assertSee('"@type": "Place"', false)
            ->assertDontSee('"@type": "Event"', false);
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
            ->assertSee(PinPage::path($live), false)
            ->assertDontSee(PinPage::token($expired), false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap:', false)
            ->assertSee('/sitemap.xml', false);
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
