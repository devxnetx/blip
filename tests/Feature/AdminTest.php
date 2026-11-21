<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\PinImport;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/devices')->assertRedirect('/admin/login');
        $this->get('/admin/users')->assertRedirect('/admin/login');
        $this->get('/admin/pins')->assertRedirect('/admin/login');
        $this->get('/admin/categories')->assertRedirect('/admin/login');
        $this->postJson('/admin/pins/1/ban')->assertUnauthorized();
        $this->getJson('/admin/pins/unassigned')->assertUnauthorized();
        $this->postJson('/admin/pins/unassigned', [])->assertUnauthorized();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Влез')
            ->assertSee('Парола');
    }

    public function test_both_admin_accounts_can_sign_in(): void
    {
        foreach (['devxnetx', 'ZenSorcerer'] as $name) {
            $this->post('/admin/logout');
            $this->post('/admin/login', [
                'name' => $name,
                'password' => '123456',
            ])->assertRedirect('/admin/devices');

            $this->get('/admin/devices')
                ->assertOk()
                ->assertSee('Устройства')
                ->assertSee($name, false);
        }
    }

    public function test_a_wrong_password_stays_on_the_login_form(): void
    {
        $this->from('/admin/login')->post('/admin/login', [
            'name' => 'devxnetx',
            'password' => 'nope',
        ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors('name');

        $this->get('/admin/devices')->assertRedirect('/admin/login');
    }

    public function test_a_device_keeps_its_pins_and_a_ban_hides_the_active_ones(): void
    {
        $deviceId = (string) Str::uuid();
        $hash = hash('sha256', $deviceId);

        $created = $this->withHeader('X-Device-Id', $deviceId)->post('/api/pins', [
            'message' => 'Active street note',
            'latitude' => 42.4258,
            'longitude' => 25.6345,
            'duration' => '1h',
            'category' => 'other',
        ])->assertCreated();

        $created->assertJsonMissingPath('device_hash');

        Pin::query()->create([
            'device_hash' => $hash,
            'message' => 'Old street note',
            'latitude' => 42.43,
            'longitude' => 25.64,
            'expires_at' => now()->subHour(),
        ]);

        $device = Device::query()->where('device_hash', $hash)->firstOrFail();
        $admin = User::query()->where('name', 'devxnetx')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/devices/'.$device->id)
            ->assertOk()
            ->assertSee('Active street note')
            ->assertSee('Old street note')
            ->assertSee('Скрит')
            ->assertSee('Преглед')
            ->assertSee('Редакция')
            ->assertSee('aside class="side"', false)
            ->assertDontSee('Изтрий')
            ->assertSee('#'.$device->id, false);

        $this->actingAs($admin)
            ->postJson('/admin/pins/'.$created->json('id').'/ban')
            ->assertOk()
            ->assertJsonPath('banned', true)
            ->assertJsonPath('hidden_pin_ids.0', $created->json('id'));

        $this->getJson('/api/pins?'.http_build_query([
            'north' => 42.5,
            'south' => 42.4,
            'east' => 25.7,
            'west' => 25.6,
        ]))->assertOk()->assertJsonCount(0, 'pins');

        $this->assertSame(2, Pin::query()->where('device_hash', $hash)->count());
        $this->assertTrue($device->fresh()->isBanned());

        $this->withHeader('X-Device-Id', $deviceId)->postJson('/api/pins', [
            'message' => 'Should not land',
            'latitude' => 42.5,
            'longitude' => 25.7,
            'duration' => '5m',
            'category' => 'warning',
        ])->assertUnprocessable()->assertJsonValidationErrors('device');

        $this->actingAs($admin)
            ->post('/admin/devices/'.$device->id.'/unban')
            ->assertRedirect();

        $this->assertFalse($device->fresh()->isBanned());

        $this->withHeader('X-Device-Id', $deviceId)->postJson('/api/pins', [
            'message' => 'Back again',
            'latitude' => 42.5,
            'longitude' => 25.7,
            'duration' => '5m',
            'category' => 'warning',
        ])->assertCreated();
    }

    public function test_an_admin_can_place_a_pin_from_the_map_form(): void
    {
        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();

        $this->actingAs($admin)
            ->get('/map')
            ->assertOk()
            ->assertSee('Нов пин')
            ->assertSee('Сега')
            ->assertSee('Категория')
            ->assertSee('Кога')
            ->assertSee('Продължителност')
            ->assertSee('id="open-composer"', false)
            ->assertSee('id="pin-end"', false)
            ->assertDontSee('id="unassigned-form"', false)
            ->assertSee('Избери на картата')
            ->assertSee('Пусни пин')
            ->assertSee('Забрани устройството')
            ->assertSee('id="pick-spot"', false);

        $created = $this->actingAs($admin)->postJson('/admin/pins', [
            'message' => 'Admin placed this',
            'latitude' => 42.4258,
            'longitude' => 25.6345,
            'duration' => '24h',
            'category' => 'free',
        ])->assertCreated()
            ->assertJsonPath('message', 'Admin placed this')
            ->assertJsonMissingPath('device_hash');

        $this->getJson('/api/pins?'.http_build_query([
            'north' => 42.5,
            'south' => 42.4,
            'east' => 25.7,
            'west' => 25.6,
        ]))->assertOk()->assertJsonPath('pins.0.id', $created->json('id'));

        $device = Device::query()->where('label', 'ZenSorcerer')->firstOrFail();
        $this->assertSame(Device::hashForAdmin($admin->id), $device->device_hash);
        $this->assertSame(1, $device->pins()->count());

        $this->actingAs($admin)
            ->get('/admin/pins')
            ->assertOk()
            ->assertSee('Admin placed this')
            ->assertSee('Безплатно')
            ->assertSee('Скрий')
            ->assertSee('Нов пин')
            ->assertSee('Действия')
            ->assertSee('Стандартен')
            ->assertSee('class="count">1</span>', false)
            ->assertSee('class="on" href="'.route('admin.pins', ['kind' => 'normal']).'"', false)
            ->assertSee('data-stat="total">1<', false)
            ->assertSee('data-stat="active">1<', false)
            ->assertSee('data-stat="unassigned">0<', false)
            ->assertSee('data-stat="passed">0<', false)
            ->assertSee('Източник')
            ->assertSee('Връзка')
            ->assertSee('Функции')
            ->assertSee('Система')
            ->assertSee('class="cell-actions"', false)
            ->assertSee('class="date"', false)
            ->assertDontSee('Изтрий');

        $this->actingAs($admin)
            ->get('/admin/imports')
            ->assertOk()
            ->assertSee('Няма импорти.')
            ->assertSee('Импорти');

        $this->actingAs($admin)
            ->post('/admin/pins/'.$created->json('id').'/hide')
            ->assertRedirect();

        $this->getJson('/api/pins?'.http_build_query([
            'north' => 42.5,
            'south' => 42.4,
            'east' => 25.7,
            'west' => 25.6,
        ]))->assertOk()->assertJsonCount(0, 'pins');

        $this->assertNotNull(Pin::query()->find($created->json('id')));
    }

    public function test_an_admin_can_rename_a_device(): void
    {
        $deviceId = (string) Str::uuid();

        $this->withHeader('X-Device-Id', $deviceId)->post('/api/pins', [
            'message' => 'Named later',
            'latitude' => 42.2,
            'longitude' => 24.7,
            'duration' => '1h',
            'category' => 'other',
        ])->assertCreated();

        $device = Device::query()->where('device_hash', hash('sha256', $deviceId))->firstOrFail();
        $admin = User::query()->where('name', 'devxnetx')->firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/devices/'.$device->id, ['label' => 'Телефонът на Борис'])
            ->assertRedirect('/admin/devices/'.$device->id);

        $this->assertSame('Телефонът на Борис', $device->fresh()->label);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Преглед')
            ->assertSee('Редакция')
            ->assertSee('Изтрий')
            ->assertSee('aside class="side"', false);
    }

    public function test_an_admin_can_add_another_admin_and_not_delete_themselves(): void
    {
        $admin = User::query()->where('name', 'devxnetx')->firstOrFail();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Nova',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ])->assertRedirect();

        $this->post('/admin/logout');
        $this->post('/admin/login', [
            'name' => 'Nova',
            'password' => 'secret1',
        ])->assertRedirect('/admin/devices');

        $nova = User::query()->where('name', 'Nova')->firstOrFail();
        $this->actingAs($nova)
            ->delete('/admin/users/'.$nova->id)
            ->assertRedirect()
            ->assertSessionHasErrors('name');

        $this->assertNotNull(User::query()->find($nova->id));

        $this->actingAs($nova)
            ->delete('/admin/users/'.$admin->id)
            ->assertRedirect();

        $this->assertNull(User::query()->find($admin->id));
    }

    public function test_an_admin_can_manage_categories_and_the_map_uses_them(): void
    {
        $admin = User::query()->where('name', 'devxnetx')->firstOrFail();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('categories.0.id', 'seeking')
            ->assertJsonPath('categories.0.label', 'Търся')
            ->assertJsonPath('categories.4.id', 'other');

        $this->actingAs($admin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Категории')
            ->assertSee('Нова категория')
            ->assertSee('Търся')
            ->assertSee('Преглед')
            ->assertSee('Редакция')
            ->assertSee('Изтрий');

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Паркинг',
        ])->assertRedirect();

        $category = Category::query()->where('name', 'Паркинг')->firstOrFail();
        $slug = $category->slug;
        $this->assertNotSame('', $slug);

        $this->actingAs($admin)
            ->get('/map')
            ->assertOk()
            ->assertSee('Паркинг')
            ->assertSee('data-category="'.$slug.'"', false);

        $this->actingAs($admin)
            ->put('/admin/categories/'.$category->id, [
                'name' => 'Свободен паркинг',
                'position' => 1,
            ])->assertRedirect('/admin/categories/'.$category->id);

        $category->refresh();
        $this->assertSame('Свободен паркинг', $category->name);
        $this->assertSame($slug, $category->slug);
        $this->assertSame(1, $category->position);

        $this->withHeader('X-Device-Id', (string) Str::uuid())
            ->post('/api/pins', [
                'message' => 'Има място',
                'latitude' => 42.7,
                'longitude' => 23.3,
                'duration' => '1h',
                'category' => $slug,
            ])->assertCreated();

        $this->actingAs($admin)
            ->get('/admin/categories/'.$category->id)
            ->assertOk()
            ->assertSee('Има място')
            ->assertSee('не може да се изтрие')
            ->assertDontSee('Изтрий');

        $this->actingAs($admin)
            ->delete('/admin/categories/'.$category->id)
            ->assertRedirect()
            ->assertSessionHasErrors('category');

        $this->assertNotNull(Category::query()->find($category->id));

        $this->actingAs($admin)
            ->get('/admin/pins')
            ->assertOk()
            ->assertSee('Свободен паркинг');

        $this->get('/map')
            ->assertOk()
            ->assertSee('Свободен паркинг');

        $this->get('/')
            ->assertOk()
            ->assertSee('Свободен паркинг', false);

        $unused = Category::query()->create([
            'slug' => 'unused-cat',
            'name' => 'Празна',
            'position' => 9,
        ]);

        $this->actingAs($admin)
            ->get('/admin/categories/'.$unused->id)
            ->assertOk()
            ->assertSee('Изтрий');

        $this->actingAs($admin)
            ->delete('/admin/categories/'.$unused->id)
            ->assertRedirect('/admin/categories');

        $this->assertNull(Category::query()->find($unused->id));
        $this->get('/map')->assertOk()->assertDontSee('Празна');
    }

    public function test_an_admin_keeps_an_event_off_the_map_until_it_has_a_place(): void
    {
        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();
        $bounds = [
            'north' => 42.6,
            'south' => 42.2,
            'east' => 25.9,
            'west' => 25.4,
        ];

        $this->actingAs($admin)
            ->get('/map')
            ->assertOk()
            ->assertSee('Без място')
            ->assertSee('Предстоящо')
            ->assertSee('id="open-unassigned"', false)
            ->assertSee('id="unassigned"', false);

        auth()->logout();

        $this->get('/map')
            ->assertOk()
            ->assertDontSee('Без място')
            ->assertDontSee('id="open-unassigned"', false);

        $start = now()->addDays(2)->startOfSecond();

        $created = $this->actingAs($admin)->postJson('/admin/pins/unassigned', [
            'message' => 'Концерт без сцена',
            'category' => 'other',
            'duration' => '24h',
            'starts_at' => $start->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('kind', 'unassigned')
            ->assertJsonPath('latitude', null)
            ->assertJsonPath('longitude', null);

        $id = $created->json('id');

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonMissing(['id' => $id]);

        $this->getJson('/api/pins/latest')
            ->assertOk()
            ->assertJsonMissing(['message' => 'Концерт без сцена']);

        $this->getJson('/api/pins/'.$id)->assertNotFound();

        $this->actingAs($admin)
            ->getJson('/admin/pins/unassigned')
            ->assertOk()
            ->assertJsonPath('pins.0.id', $id)
            ->assertJsonPath('pins.0.message', 'Концерт без сцена');

        $this->actingAs($admin)
            ->get('/admin/pins')
            ->assertOk()
            ->assertDontSee('Концерт без сцена');

        $this->actingAs($admin)
            ->get('/admin/pins?kind=unassigned')
            ->assertOk()
            ->assertSee('Концерт без сцена')
            ->assertSee('Без място')
            ->assertSee('class="count">1</span>', false)
            ->assertSee('class="good"', false)
            ->assertSee('Място')
            ->assertDontSee('/map?pin='.$id, false);

        $placed = $this->actingAs($admin)->postJson('/admin/pins/'.$id.'/place', [
            'latitude' => 42.4258,
            'longitude' => 25.6345,
        ])->assertOk()
            ->assertJsonPath('kind', 'upcoming')
            ->assertJsonPath('latitude', 42.4258);

        $pin = Pin::query()->findOrFail($id);
        $this->assertTrue($pin->expires_at->equalTo($start->copy()->addDay()));

        $this->getJson('/api/pins?'.http_build_query($bounds))
            ->assertOk()
            ->assertJsonPath('pins.0.id', $placed->json('id'));

        $this->actingAs($admin)->postJson('/admin/pins/unassigned', [
            'message' => 'Стар концерт',
            'category' => 'other',
            'duration' => '5m',
            'starts_at' => now()->subHours(2)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $started = now()->subMinutes(10)->startOfSecond();
        $open = $this->actingAs($admin)->postJson('/admin/pins/unassigned', [
            'message' => 'Вече е започнал',
            'category' => 'other',
            'duration' => '1h',
            'starts_at' => $started->toIso8601String(),
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/pins/'.$open->json('id').'/place', [
            'latitude' => 42.5,
            'longitude' => 25.7,
        ])->assertOk()->assertJsonPath('kind', 'normal');

        $plain = $this->actingAs($admin)->postJson('/admin/pins/unassigned', [
            'message' => 'Без дата',
            'category' => 'free',
            'duration' => '1h',
        ])->assertCreated()
            ->assertJsonPath('kind', 'unassigned')
            ->assertJsonPath('starts_at', null);

        $this->actingAs($admin)->postJson('/admin/pins/'.$plain->json('id').'/place', [
            'latitude' => 42.14,
            'longitude' => 24.75,
        ])->assertOk()
            ->assertJsonPath('kind', 'normal')
            ->assertJsonPath('starts_at', null);

        $this->actingAs($admin)->postJson('/admin/pins/'.$plain->json('id').'/place', [
            'latitude' => 42.2,
            'longitude' => 24.8,
        ])->assertUnprocessable()->assertJsonValidationErrors('pin');

        $blocked = $this->actingAs($admin)->postJson('/admin/pins/unassigned', [
            'message' => 'Същото място',
            'category' => 'warning',
            'duration' => '1h',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/admin/pins/'.$blocked->json('id').'/place', [
            'latitude' => 42.4258,
            'longitude' => 25.6345,
        ])->assertUnprocessable()->assertJsonValidationErrors('spot');

        $local = $this->actingAs($admin)->postJson('/admin/pins', [
            'message' => 'Вечер в София',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'duration' => '1h',
            'category' => 'other',
            'kind' => 'upcoming',
            'starts_at' => '2026-12-01T18:30',
        ])->assertCreated()->assertJsonPath('kind', 'upcoming');

        $evening = Pin::query()->findOrFail($local->json('id'));
        $this->assertSame('2026-12-01 16:30:00', $evening->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 17:30:00', $evening->expires_at->utc()->format('Y-m-d H:i:s'));

        $ended = Pin::query()->create([
            'device_hash' => Device::hashForAdmin($admin->id),
            'message' => 'Изтекло',
            'category' => 'other',
            'kind' => Pin::KIND_UNASSIGNED,
            'starts_at' => now()->subHours(3),
            'duration_minutes' => 60,
            'expires_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin)->postJson('/admin/pins/'.$ended->id.'/place', [
            'latitude' => 43.21,
            'longitude' => 27.91,
        ])->assertUnprocessable()->assertJsonValidationErrors('pin');

        $this->assertNull($ended->fresh()->latitude);

        $this->actingAs($admin)->postJson('/admin/pins', [
            'message' => 'До незакачения',
            'latitude' => 43.2141,
            'longitude' => 27.9147,
            'duration' => '1h',
            'category' => 'other',
        ])->assertCreated();
    }

    public function test_an_admin_upcoming_pin_can_span_two_datetimes(): void
    {
        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();

        $created = $this->actingAs($admin)->postJson('/admin/pins', [
            'message' => 'От събота до неделя',
            'latitude' => 42.15,
            'longitude' => 24.75,
            'category' => 'other',
            'kind' => 'upcoming',
            'starts_at' => '2026-12-05T10:00',
            'ends_at' => '2026-12-06T18:30',
        ])->assertCreated()->assertJsonPath('kind', 'upcoming');

        $pin = Pin::query()->findOrFail($created->json('id'));
        $this->assertSame('2026-12-05 08:00:00', $pin->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-06 16:30:00', $pin->expires_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1950, $pin->duration_minutes);

        $this->actingAs($admin)->postJson('/admin/pins', [
            'message' => 'Обърнат интервал',
            'latitude' => 42.16,
            'longitude' => 24.76,
            'category' => 'other',
            'kind' => 'upcoming',
            'starts_at' => '2026-12-06T18:30',
            'ends_at' => '2026-12-05T10:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_at');

        $this->actingAs($admin)->postJson('/admin/pins', [
            'message' => 'Без край',
            'latitude' => 42.17,
            'longitude' => 24.77,
            'category' => 'other',
            'kind' => 'upcoming',
            'starts_at' => '2026-12-05T10:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('duration');
    }

    public function test_an_admin_can_see_where_a_pin_was_scraped_from(): void
    {
        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();
        $pin = Pin::query()->create([
            'device_hash' => hash('sha256', 'eventibg'),
            'message' => 'Събор в Малево',
            'category' => 'other',
            'kind' => Pin::KIND_UNASSIGNED,
            'starts_at' => now()->addDay(),
            'duration_minutes' => 120,
            'expires_at' => now()->addDays(2),
            'source' => 'eventibg',
            'external_id' => 'sabor-malevo',
            'source_url' => 'https://eventibg.com/event/sabor-malevo',
        ]);
        PinImport::query()->create([
            'source' => 'eventibg',
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'duration_seconds' => 75,
            'pins_count' => 4,
            'unassigned_count' => 1,
            'successful' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/pins?kind=unassigned')
            ->assertOk()
            ->assertSee('Събор в Малево')
            ->assertSee('EventiBG')
            ->assertSee('https://eventibg.com/event/sabor-malevo', false)
            ->assertSee('class="subdate"', false)
            ->assertSee('class="good"', false)
            ->assertSee('data-stat="unassigned">1<', false);

        $this->actingAs($admin)
            ->get('/admin/imports')
            ->assertOk()
            ->assertSee('Успешен')
            ->assertSee('1 мин 15 сек')
            ->assertSee('EventiBG');

        $this->actingAs($admin)
            ->get('/admin/pins/'.$pin->id)
            ->assertOk()
            ->assertSee('Събор в Малево')
            ->assertSee('EventiBG')
            ->assertSee('https://eventibg.com/event/sabor-malevo', false)
            ->assertSee('Без място');
    }

    public function test_an_admin_can_turn_imports_off_and_choose_how_far_ahead_they_read(): void
    {
        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Спортен календар')
            ->assertSee('EventiBG')
            ->assertSee('Купи билети')
            ->assertSee('Ограничението в дни не важи тук.')
            ->assertSee('Снимката на събитието се записва.')
            ->assertDontSee('Снимките от този сайт не се взимат.')
            ->assertSeeInOrder(['Функции', 'Карта', 'Система', 'Импорти', 'Настройки'])
            ->assertSee('Дни напред')
            ->assertSee('value="14"', false);

        $this->actingAs($admin)
            ->put('/admin/settings', [
                'import_eventibg_enabled' => '1',
                'import_days_ahead' => 7,
            ])
            ->assertRedirect('/admin/settings');

        $this->assertFalse(Setting::enabled('import_sporten_enabled'));
        $this->assertTrue(Setting::enabled('import_eventibg_enabled'));
        $this->assertFalse(Setting::enabled('import_kupibileti_enabled'));
        $this->assertSame(7, Setting::daysAhead());

        $this->actingAs($admin)
            ->put('/admin/settings', [
                'import_days_ahead' => 120,
            ])
            ->assertSessionHasErrors('import_days_ahead');
    }
}
