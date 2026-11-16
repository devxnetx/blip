<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\Reply;
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
            ->assertSee('За iPhone')
            ->assertSee('За Android')
            ->assertSee('APK')
            ->assertSee('Expo')
            ->assertSee('exp://jvjzxya-devxnetx-8081.exp.direct', false)
            ->assertSee('https://expo.dev/artifacts/eas/LEjq1XFy2T8F_lM0ipt9tkKzhwKxf0Ch2bROjNcnBis.apk', false)
            ->assertDontSee('exp://u.expo.dev/update/01a1e4d4-a46f-7bac-9b48-48946b24bd2e', false)
            ->assertDontSee('https://apps.apple.com', false)
            ->assertDontSee('https://play.google.com/store', false)
            ->assertSee('Виж картата')
            ->assertSee('id="rain-left"', false)
            ->assertSee('id="rain-right"', false)
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
            ->assertDontSee('Пусни пин');
    }

    public function test_an_expired_pin_and_its_photo_are_removed_when_the_map_loads(): void
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
        $this->assertSame(0, Pin::query()->count());
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
}
