<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\Reply;
use App\Support\LinkPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PinTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_explains_the_pin(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Остави бележка там, където стоиш.')
            ->assertSee('„Пин тук“ ползва местоположението ти.', false)
            ->assertSee('App Store')
            ->assertSee('Google Play')
            ->assertDontSee('Expo', false)
            ->assertDontSee('APK', false)
            ->assertSee('exp://jvjzxya-devxnetx-8081.exp.direct', false)
            ->assertSee('https://expo.dev/artifacts/eas/VqULEX8iLSXqNtbO78vAZoc_y8QMjXuBYCwaMruoIVA.apk', false)
            ->assertDontSee('exp://u.expo.dev/update/01a1e4d4-a46f-7bac-9b48-48946b24bd2e', false)
            ->assertDontSee('https://apps.apple.com', false)
            ->assertDontSee('https://play.google.com/store', false)
            ->assertSee('Пинове')
            ->assertSee('href="'.route('pins').'"', false)
            ->assertSee('class="discord-link"', false)
            ->assertSee('aria-label="Discord"', false)
            ->assertDontSee('>Discord<', false)
            ->assertSee('https://discord.com/channels/1574875809603133562', false)
            ->assertSeeInOrder(['class="discord-link"', 'class="pins-link"', 'class="view-map"'], false)
            ->assertDontSee('За нас')
            ->assertSee('Виж картата')
            ->assertSee('id="rain"', false)
            ->assertSee('бул. Цар Симеон Велики', false)
            ->assertSee('ул. Генерал Столетов', false)
            ->assertSee('бул. Руски', false)
            ->assertSee('ул. Св. Княз Борис', false)
            ->assertSee('class="hero-pin"', false)
            ->assertSee('/map?pin=', false)
            ->assertDontSee('Последни')
            ->assertSee('id="boot"', false)
            ->assertDontSee('Снимката не е задължителна')
            ->assertDontSee('Когато времето свърши');
    }

    public function test_the_latest_feed_is_the_fifteen_newest_pins_from_any_city(): void
    {
        $older = Pin::query()->create([
            'device_hash' => hash('sha256', 'older'),
            'message' => 'Older city note',
            'category' => 'lost',
            'latitude' => 42.6977,
            'longitude' => 23.3219,
            'expires_at' => now()->addHour(),
        ]);
        $older->forceFill(['created_at' => now()->subHours(2)])->save();

        $newer = Pin::query()->create([
            'device_hash' => hash('sha256', 'newer'),
            'message' => 'Newer city note',
            'category' => 'seeking',
            'latitude' => 43.2141,
            'longitude' => 27.9147,
            'expires_at' => now()->addHour(),
        ]);

        Reply::query()->create([
            'pin_id' => $newer->id,
            'device_hash' => hash('sha256', 'someone'),
            'message' => 'secret reply text',
            'author_only' => false,
        ]);

        Pin::query()->create([
            'device_hash' => hash('sha256', 'gone'),
            'message' => 'Expired city note',
            'latitude' => 42.4258,
            'longitude' => 25.6345,
            'expires_at' => now()->subMinute(),
        ]);

        foreach (range(1, 14) as $n) {
            $extra = Pin::query()->create([
                'device_hash' => hash('sha256', 'extra-'.$n),
                'message' => 'Extra '.$n,
                'latitude' => 42.1,
                'longitude' => 24.7,
                'expires_at' => now()->addHour(),
            ]);
            $extra->forceFill(['created_at' => now()->subMinutes($n)])->save();
        }

        $this->getJson('/api/pins/latest')
            ->assertOk()
            ->assertJsonCount(15, 'pins')
            ->assertJsonPath('pins.0.message', 'Newer city note')
            ->assertJsonPath('pins.1.message', 'Extra 1')
            ->assertJsonMissing(['message' => 'Older city note'])
            ->assertJsonMissing(['message' => 'Expired city note'])
            ->assertJsonMissing(['message' => 'secret reply text']);

        $this->getJson('/api/pins/latest?limit=40')
            ->assertOk()
            ->assertJsonCount(16, 'pins')
            ->assertJsonFragment(['message' => 'Older city note']);
    }

    public function test_the_changelog_page_has_the_site_nav(): void
    {
        $this->get('/changelog')
            ->assertOk()
            ->assertSee('Changelog')
            ->assertSee('API')
            ->assertDontSee('Начало')
            ->assertSee('Как работи')
            ->assertSee('Контакт')
            ->assertSee('Подкрепи ни')
            ->assertSee('Картата')
            ->assertSee('Медиа')
            ->assertSee('Карта')
            ->assertSee('Discord')
            ->assertDontSee('За нас')
            ->assertSee('class="site-nav"', false)
            ->assertSee('class="links links-place"', false)
            ->assertSeeInOrder(['Пинове', 'Картата', 'Discord', 'Changelog', 'Как работи'])
            ->assertSee('class="discord-link"', false)
            ->assertSee('https://discord.com/channels/1574875809603133562', false)
            ->assertSee('nav-icon', false)
            ->assertSee('id="theme-switch"', false)
            ->assertSee('Тъмен режим', false)
            ->assertSee('kostaa-theme', false)
            ->assertSee('class="map-link"', false)
            ->assertSee('brand/icon.svg', false);
    }

    public function test_the_desktop_map_views_pins_without_a_way_to_add_one(): void
    {
        $this->get('/map')
            ->assertOk()
            ->assertSee('Наблизо')
            ->assertSee('Изпрати отговор')
            ->assertSee('Видимо само за автора на пина')
            ->assertSee('Докладвай')
            ->assertSee('Търся')
            ->assertSee('Загубено')
            ->assertSee('Внимание')
            ->assertSee('Безплатно')
            ->assertSee('Друго')
            ->assertSee('Копирай връзката към пина')
            ->assertSee('Сподели')
            ->assertDontSee('Пин тук')
            ->assertDontSee('Пусни пин')
            ->assertDontSee('Забрани устройството')
            ->assertDontSee('Избери на картата')
            ->assertDontSee('Без място')
            ->assertDontSee('Предстоящо')
            ->assertDontSee('id="open-unassigned"', false)
            ->assertDontSee('id="unassigned"', false)
            ->assertDontSee('id="admin-cog"', false)
            ->assertDontSee('Устройства')
            ->assertSee('.maplibregl-map', false)
            ->assertSee('font-family: "Sofia Sans", sans-serif', false)
            ->assertSee('class="map-top"', false)
            ->assertSee('id="city-bar"', false)
            ->assertDontSee('id="period-today"', false)
            ->assertSee('id="map-filters"', false)
            ->assertSee('id="desktop-time"', false)
            ->assertSee('id="phone-time"', false)
            ->assertSee('Тия дни')
            ->assertSee('10 дни')
            ->assertSee('Всичко')
            ->assertSee('id="open-list"', false)
            ->assertSee('id="open-cities-mobile"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('summary_large_image', false)
            ->assertSee(LinkPreview::FALLBACK_DESCRIPTION, false)
            ->assertSee('/brand/banner-og.png', false);
    }

    public function test_a_pin_link_carries_a_preview_for_chat_apps(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pins/card.jpg', 'photo');

        $withPhoto = Pin::query()->create([
            'device_hash' => hash('sha256', 'preview'),
            'message' => 'Концерт на площада',
            'description' => 'Голям концерт в центъра.',
            'latitude' => 42.6977,
            'longitude' => 23.3219,
            'image_path' => 'pins/card.jpg',
            'expires_at' => now()->addHour(),
        ]);
        $plain = Pin::query()->create([
            'device_hash' => hash('sha256', 'plain-preview'),
            'message' => 'Бележка без снимка',
            'latitude' => 42.15,
            'longitude' => 24.75,
            'expires_at' => now()->addHour(),
        ]);
        $hidden = Pin::query()->create([
            'device_hash' => hash('sha256', 'hidden-preview'),
            'message' => 'Скрит пин',
            'latitude' => 42.5,
            'longitude' => 27.4,
            'expires_at' => now()->addHour(),
            'suppressed_at' => now(),
        ]);

        $this->get('/map?pin='.$withPhoto->id)
            ->assertOk()
            ->assertSee('Концерт на площада — Kostaa', false)
            ->assertSee('Голям концерт в центъра.', false)
            ->assertSee('/storage/pins/card.jpg', false)
            ->assertSee('content="image/jpeg"', false)
            ->assertSee('/map?pin='.$withPhoto->id, false)
            ->assertDontSee(LinkPreview::FALLBACK_DESCRIPTION, false);

        $this->get('/map?pin='.$plain->id)
            ->assertOk()
            ->assertSee('Бележка без снимка — Kostaa', false)
            ->assertSee(LinkPreview::FALLBACK_DESCRIPTION, false)
            ->assertSee('/brand/banner-og.png', false)
            ->assertSee('content="image/png"', false);

        $this->get('/map?pin='.$hidden->id)
            ->assertOk()
            ->assertDontSee('Скрит пин', false)
            ->assertSee('Карта — Kostaa', false);

        $this->get('/map?pin=999999')
            ->assertOk()
            ->assertSee('Карта — Kostaa', false)
            ->assertSee(LinkPreview::FALLBACK_DESCRIPTION, false);

        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee('/brand/banner-og.png', false)
            ->assertSee('Kostaa — бележка, която изчезва', false);
    }

    public function test_an_expired_pin_keeps_its_note_and_drops_the_photo_when_the_map_loads(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pins/old.jpg', 'photo');

        Pin::query()->create([
            'device_hash' => hash('sha256', (string) Str::uuid()),
            'message' => 'Gone',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'image_path' => 'pins/old.jpg',
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/pins?'.http_build_query([
            'north' => 42.8,
            'south' => 42.6,
            'east' => 23.5,
            'west' => 23.2,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'pins');

        Storage::disk('public')->assertMissing('pins/old.jpg');
        $this->assertSame(1, Pin::query()->count());
        $this->assertNull(Pin::query()->first()->image_path);
    }

    public function test_a_pin_shows_inside_the_map_bounds_until_it_expires(): void
    {
        $device = (string) Str::uuid();

        $created = $this->withHeader('X-Device-Id', $device)->post('/api/pins', [
            'message' => '  Lost keys here  ',
            'latitude' => 42.6977,
            'longitude' => 23.3219,
            'duration' => '1h',
        ]);

        $created->assertCreated()
            ->assertJsonPath('message', 'Lost keys here')
            ->assertJsonPath('mine', true)
            ->assertJsonPath('kind', 'normal')
            ->assertJsonPath('starts_at', null)
            ->assertJsonPath('image_url', null);

        $this->withHeader('X-Device-Id', $device)->getJson('/api/pins?'.http_build_query([
            'north' => 42.8,
            'south' => 42.6,
            'east' => 23.5,
            'west' => 23.2,
        ]))
            ->assertOk()
            ->assertJsonPath('pins.0.message', 'Lost keys here')
            ->assertJsonPath('pins.0.mine', true);

        $this->travel(2)->hours();

        $this->getJson('/api/pins?'.http_build_query([
            'north' => 42.8,
            'south' => 42.6,
            'east' => 23.5,
            'west' => 23.2,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'pins');
    }

    public function test_a_photo_is_optional_and_only_the_author_can_remove_the_pin(): void
    {
        Storage::fake('public');
        $device = (string) Str::uuid();

        $created = $this->withHeader('X-Device-Id', $device)->post('/api/pins', [
            'message' => 'Park bench',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'duration' => '5m',
            'image' => UploadedFile::fake()->image('bench.jpg'),
        ]);

        $created->assertCreated();
        $id = $created->json('id');
        $this->assertNotNull($created->json('image_url'));
        Storage::disk('public')->assertExists(Pin::query()->findOrFail($id)->image_path);

        $this->withHeader('X-Device-Id', (string) Str::uuid())
            ->deleteJson('/api/pins/'.$id)
            ->assertNotFound();

        $this->withHeader('X-Device-Id', $device)
            ->deleteJson('/api/pins/'.$id)
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertNull(Pin::query()->find($id));
    }

    public function test_the_same_person_cannot_pin_the_same_spot_twice(): void
    {
        Storage::fake('public');
        $device = (string) Str::uuid();
        $spot = [
            'latitude' => 42.6977,
            'longitude' => 23.3219,
        ];

        Pin::query()->create([
            'device_hash' => hash('sha256', $device),
            'message' => 'Expired',
            'latitude' => $spot['latitude'],
            'longitude' => $spot['longitude'],
            'expires_at' => now()->subMinute(),
        ]);

        $this->withHeader('X-Device-Id', $device)->post('/api/pins', [
            'message' => 'First',
            ...$spot,
            'duration' => '1h',
        ])->assertCreated();

        $this->withHeader('X-Device-Id', $device)->post('/api/pins', [
            'message' => 'Too close',
            'latitude' => $spot['latitude'] + 0.0002,
            'longitude' => $spot['longitude'],
            'duration' => '1h',
            'image' => UploadedFile::fake()->image('again.jpg'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('spot')
            ->assertJsonPath('errors.spot.0', 'Вече имаш пин на това място.');

        $this->assertSame(2, Pin::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->withHeader('X-Device-Id', $device)->post('/api/pins', [
            'message' => 'Across town',
            'latitude' => $spot['latitude'] + 0.01,
            'longitude' => $spot['longitude'],
            'duration' => '1h',
        ])->assertCreated();

        $this->withHeader('X-Device-Id', (string) Str::uuid())->post('/api/pins', [
            'message' => 'Someone else',
            ...$spot,
            'duration' => '1h',
        ])->assertCreated();

        $this->assertSame(4, Pin::query()->count());
    }

    public function test_a_pin_needs_a_message_and_a_device(): void
    {
        $this->postJson('/api/pins', [
            'message' => 'Hello',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'duration' => '5m',
        ])->assertUnprocessable()->assertJsonValidationErrors('device');

        $this->withHeader('X-Device-Id', (string) Str::uuid())->postJson('/api/pins', [
            'message' => '   ',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'duration' => '5m',
        ])->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_a_pin_can_carry_one_of_the_categories(): void
    {
        $device = (string) Str::uuid();

        $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'Keys by the bench',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'duration' => '1h',
            'category' => 'lost',
        ])->assertCreated()->assertJsonPath('category', 'lost');

        $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'Somewhere else',
            'latitude' => 42.2,
            'longitude' => 24.7,
            'duration' => '1h',
            'category' => 'nope',
        ])->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'No chip',
            'latitude' => 42.21,
            'longitude' => 24.71,
            'duration' => '1h',
            'category' => 'other',
        ])->assertCreated()->assertJsonPath('category', 'other');
    }

    public function test_a_pin_link_returns_that_pin_without_its_replies(): void
    {
        $owner = (string) Str::uuid();
        $stranger = (string) Str::uuid();

        $created = $this->withHeader('X-Device-Id', $owner)->postJson('/api/pins', [
            'message' => 'Bench by the gate',
            'latitude' => 42.43,
            'longitude' => 25.62,
            'duration' => '1h',
        ])->assertCreated();

        $id = $created->json('id');

        Reply::query()->create([
            'pin_id' => $id,
            'device_hash' => hash('sha256', $stranger),
            'message' => 'secret reply text',
            'author_only' => false,
        ]);

        $this->withHeader('X-Device-Id', $stranger)
            ->getJson('/api/pins/'.$id)
            ->assertOk()
            ->assertJsonPath('pin.id', $id)
            ->assertJsonPath('pin.message', 'Bench by the gate')
            ->assertJsonPath('pin.latitude', 42.43)
            ->assertJsonPath('pin.longitude', 25.62)
            ->assertJsonPath('pin.mine', false)
            ->assertJsonPath('pin.replied', true)
            ->assertDontSee('secret reply text');

        $this->withHeader('X-Device-Id', $owner)
            ->getJson('/api/pins/'.$id)
            ->assertOk()
            ->assertJsonPath('pin.mine', true)
            ->assertJsonPath('pin.replied', false);

        $this->getJson('/api/pins/999999')->assertNotFound();

        $this->travel(2)->hours();

        $this->getJson('/api/pins/'.$id)->assertNotFound();
    }

    public function test_an_upcoming_pin_is_visible_until_its_window_ends(): void
    {
        $device = (string) Str::uuid();
        $start = now()->addDays(3)->startOfSecond();

        $created = $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'Концерт в парка',
            'latitude' => 42.4258,
            'longitude' => 25.6345,
            'duration' => '1h',
            'kind' => 'upcoming',
            'starts_at' => $start->toIso8601String(),
        ]);

        $created->assertCreated()
            ->assertJsonPath('kind', 'upcoming')
            ->assertJsonPath('message', 'Концерт в парка');

        $pin = Pin::query()->first();
        $this->assertTrue($pin->starts_at->equalTo($start));
        $this->assertTrue($pin->expires_at->equalTo($start->copy()->addHour()));

        $bounds = [
            'north' => 42.5,
            'south' => 42.3,
            'east' => 25.8,
            'west' => 25.5,
        ];

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonPath('pins.0.kind', 'upcoming')
            ->assertJsonPath('pins.0.id', $pin->id);

        $this->getJson('/api/pins/latest')
            ->assertOk()
            ->assertJsonPath('pins.0.id', $pin->id);

        $this->travelTo($start->copy()->addMinutes(30));

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonCount(1, 'pins');

        $this->travelTo($start->copy()->addHours(2));

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonCount(0, 'pins');

        $this->getJson('/api/pins/'.$pin->id)->assertNotFound();
    }

    public function test_an_upcoming_pin_needs_a_future_date_within_a_year(): void
    {
        $device = (string) Str::uuid();

        $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'Без дата',
            'latitude' => 42.4,
            'longitude' => 25.6,
            'duration' => '1h',
            'kind' => 'upcoming',
        ])->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'Минало',
            'latitude' => 42.4,
            'longitude' => 25.6,
            'duration' => '1h',
            'kind' => 'upcoming',
            'starts_at' => now()->subHour()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'Далече',
            'latitude' => 42.4,
            'longitude' => 25.6,
            'duration' => '1h',
            'kind' => 'upcoming',
            'starts_at' => now()->addYears(2)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $this->withHeader('X-Device-Id', $device)->postJson('/api/pins', [
            'message' => 'Без място',
            'latitude' => 42.4,
            'longitude' => 25.6,
            'duration' => '1h',
            'kind' => 'unassigned',
        ])->assertUnprocessable()->assertJsonValidationErrors('kind');

        $this->assertSame(0, Pin::query()->count());
    }

    public function test_a_started_upcoming_pin_becomes_a_normal_pin(): void
    {
        $started = Pin::query()->create([
            'device_hash' => hash('sha256', 'started'),
            'message' => 'Вече е започнало',
            'kind' => Pin::KIND_UPCOMING,
            'latitude' => 42.42,
            'longitude' => 25.63,
            'starts_at' => now()->subMinute(),
            'duration_minutes' => 180,
            'expires_at' => now()->addHours(3),
        ]);
        $later = Pin::query()->create([
            'device_hash' => hash('sha256', 'later'),
            'message' => 'Още не',
            'kind' => Pin::KIND_UPCOMING,
            'latitude' => 42.43,
            'longitude' => 25.64,
            'starts_at' => now()->addHour(),
            'duration_minutes' => 60,
            'expires_at' => now()->addHours(2),
        ]);
        $plain = Pin::query()->create([
            'device_hash' => hash('sha256', 'plain'),
            'message' => 'Обикновен',
            'kind' => Pin::KIND_NORMAL,
            'latitude' => 42.44,
            'longitude' => 25.65,
            'expires_at' => now()->addHour(),
        ]);
        $unassigned = Pin::query()->create([
            'device_hash' => hash('sha256', 'loose'),
            'message' => 'Без място',
            'kind' => Pin::KIND_UNASSIGNED,
            'starts_at' => now()->subMinute(),
            'duration_minutes' => 60,
            'expires_at' => now()->addHour(),
        ]);
        $ends = $started->expires_at->toIso8601String();

        $this->artisan('pins:promote-started')
            ->expectsOutput('Promoted 1 pin.')
            ->assertSuccessful();

        $this->assertSame(Pin::KIND_NORMAL, $started->fresh()->kind);
        $this->assertSame($ends, $started->fresh()->expires_at->toIso8601String());
        $this->assertSame(Pin::KIND_UPCOMING, $later->fresh()->kind);
        $this->assertSame(Pin::KIND_NORMAL, $plain->fresh()->kind);
        $this->assertSame(Pin::KIND_UNASSIGNED, $unassigned->fresh()->kind);
    }

    public function test_started_pins_are_promoted_every_minute(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('pins:promote-started')
            ->assertSuccessful();
    }

    public function test_city_counts_use_the_nearest_city_and_skip_hidden_pins(): void
    {
        Pin::query()->create([
            'device_hash' => hash('sha256', 'sofia'),
            'message' => 'В София',
            'latitude' => 42.6977,
            'longitude' => 23.3219,
            'expires_at' => now()->addHour(),
        ]);
        Pin::query()->create([
            'device_hash' => hash('sha256', 'varna'),
            'message' => 'Във Варна',
            'latitude' => 43.2141,
            'longitude' => 27.9147,
            'expires_at' => now()->addHour(),
        ]);
        Pin::query()->create([
            'device_hash' => hash('sha256', 'old'),
            'message' => 'Изтекло',
            'latitude' => 42.6977,
            'longitude' => 23.3219,
            'expires_at' => now()->subMinute(),
        ]);
        Pin::query()->create([
            'device_hash' => hash('sha256', 'loose'),
            'message' => 'Без място',
            'kind' => Pin::KIND_UNASSIGNED,
            'latitude' => 42.6977,
            'longitude' => 23.3219,
            'expires_at' => now()->addHour(),
        ]);
        Pin::query()->create([
            'device_hash' => hash('sha256', 'far'),
            'message' => 'Далече',
            'latitude' => 40.1,
            'longitude' => 20.1,
            'expires_at' => now()->addHour(),
        ]);

        $citiesResponse = $this->getJson('/api/cities')->assertOk();
        $this->assertStringContainsString('public', (string) $citiesResponse->headers->get('cache-control'));
        $this->assertStringContainsString('max-age=30', (string) $citiesResponse->headers->get('cache-control'));
        $cities = collect($citiesResponse->json('cities'));

        $this->assertSame(1, $cities->firstWhere('name', 'София')['count']);
        $this->assertSame(1, $cities->firstWhere('name', 'Варна')['count']);
        $this->assertSame(0, $cities->firstWhere('name', 'Пловдив')['count']);
        $this->assertSame(2, $cities->sum('count'));
    }
}
