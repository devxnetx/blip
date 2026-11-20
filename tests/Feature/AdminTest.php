<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Pin;
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
        $this->postJson('/admin/pins/1/ban')->assertUnauthorized();

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
            ->assertSee('Изтекъл')
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
            ->assertSee('Безплатно');
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
}
