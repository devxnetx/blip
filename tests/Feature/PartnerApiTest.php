<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_docs_explain_the_bearer_token_and_the_filters(): void
    {
        $this->get('/api')
            ->assertOk()
            ->assertSee('Authorization: Bearer', false)
            ->assertSee('пиши ни', false)
            ->assertSee(route('contact'), false)
            ->assertSee('/api/v1/events', false)
            ->assertSee('city')
            ->assertSee('type')
            ->assertSee('time=now', false)
            ->assertSee('sofiia')
            ->assertSee('Търся')
            ->assertSee('>API</a>', false)
            ->assertDontSee('>Начало</a>', false);
    }

    public function test_events_require_a_bearer_token_and_honor_the_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00:00', 'Europe/Sofia'));

        try {
            $this->getJson('/api/v1/events')->assertUnauthorized();
            $this->withToken('kst_not-a-real-key')->getJson('/api/v1/events')->assertUnauthorized();

            [$key, $token] = ApiKey::issue('Радио');

            $running = $this->event('Вече свири', 42.4258, 25.6345, 'other', now()->subHour(), now()->addHours(2));
            $soon = $this->event('Довечера', 42.426, 25.635, 'other', now()->addHours(2), now()->addHours(5));
            $later = $this->event('След шест дни', 42.427, 25.636, 'free', now()->addDays(6), now()->addDays(6)->addHours(3));
            $sofia = $this->event('София довечера', 42.6977, 23.3219, 'other', now()->addHours(3), now()->addHours(6));
            $this->event('Бележка', 42.4258, 25.6345, 'other', null, now()->addHour());

            $this->withToken($token)->getJson('/api/v1/cities')
                ->assertOk()
                ->assertJsonFragment(['slug' => 'stara-zagora', 'name' => 'Стара Загора']);

            $this->withToken($token)->getJson('/api/v1/types')
                ->assertOk()
                ->assertJsonFragment(['slug' => 'other', 'name' => 'Друго']);

            $this->withToken($token)->getJson('/api/v1/times')
                ->assertOk()
                ->assertJsonFragment(['id' => '4d', 'name' => 'Тия дни']);

            $now = $this->withToken($token)->getJson('/api/v1/events?time=now&city=stara-zagora')
                ->assertOk()
                ->json('data');
            $this->assertSame([$running->id], array_column($now, 'id'));

            $four = $this->withToken($token)->getJson('/api/v1/events?time=4d&city=stara-zagora&type=other')
                ->assertOk()
                ->json('data');
            $this->assertEqualsCanonicalizing([$running->id, $soon->id], array_column($four, 'id'));

            $ten = $this->withToken($token)->getJson('/api/v1/events?time=10d&city=stara-zagora&type=free')
                ->assertOk()
                ->json('data');
            $this->assertSame([$later->id], array_column($ten, 'id'));

            $all = $this->withToken($token)->getJson('/api/v1/events?city=sofiia')
                ->assertOk()
                ->json('data');
            $this->assertSame([$sofia->id], array_column($all, 'id'));
            $this->assertSame('София довечера', $all[0]['title']);
            $this->assertSame('sofiia', $all[0]['city']);

            $this->withToken($token)->getJson('/api/v1/events?city=nowhere')->assertStatus(422);
            $this->withToken($token)->getJson('/api/v1/events?type=nope')->assertStatus(422);

            $key->revoke();
            $this->withToken($token)->getJson('/api/v1/events')->assertUnauthorized();

            $this->getJson('/api/pins/latest')->assertOk();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_an_admin_can_issue_a_key_once(): void
    {
        $admin = User::query()->where('name', 'devxnetx')->firstOrFail();

        $this->get('/admin/api-keys')->assertRedirect('/admin/login');

        $created = $this->actingAs($admin)->post('/admin/api-keys', ['name' => 'Радио']);
        $created->assertRedirect('/admin/api-keys');
        $token = session('api_token');
        $this->assertIsString($token);
        $this->assertStringStartsWith('kst_', $token);

        $this->actingAs($admin)->get('/admin/api-keys')
            ->assertOk()
            ->assertSee($token, false)
            ->assertSee('Радио');

        $this->actingAs($admin)->get('/admin/api-keys')
            ->assertOk()
            ->assertSee(substr($token, 0, 12), false)
            ->assertDontSee($token, false);

        $id = ApiKey::query()->where('name', 'Радио')->value('id');
        $this->actingAs($admin)->post('/admin/api-keys/'.$id.'/revoke')->assertRedirect('/admin/api-keys');
        $this->withToken($token)->getJson('/api/v1/cities')->assertUnauthorized();
    }

    private function event(string $title, float $latitude, float $longitude, string $type, ?Carbon $starts, Carbon $ends): Pin
    {
        return Pin::query()->create([
            'device_hash' => hash('sha256', $title),
            'message' => $title,
            'category' => $type,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'kind' => $starts === null ? Pin::KIND_NORMAL : Pin::KIND_UPCOMING,
            'starts_at' => $starts,
            'expires_at' => $ends,
        ]);
    }
}
