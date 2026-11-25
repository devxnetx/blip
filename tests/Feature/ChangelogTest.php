<?php

namespace Tests\Feature;

use App\Models\ChangelogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $discordChannels = [
        ['id' => '333', 'type' => 0, 'name' => 'changelog'],
        ['id' => '111', 'type' => 0, 'name' => 'стара-загора'],
    ];

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
                return Http::response($this->discordChannels);
            }

            if (str_contains($url, '/threads')) {
                return Http::response([
                    'id' => '555',
                    'message' => ['id' => '555'],
                ]);
            }

            if (str_contains($url, '/messages')) {
                return Http::response(['id' => '999'], 200);
            }

            if ($request->method() === 'DELETE') {
                return Http::response(null, 204);
            }

            return Http::response(['id' => '999'], 200);
        });
    }

    public function test_a_published_change_is_on_the_site_and_in_discord(): void
    {
        $this->actingAs(User::query()->where('name', 'ZenSorcerer')->firstOrFail())
            ->post('/admin/changelog', [
                'title' => 'Нова карта',
                'body' => "**по-бърза** е\n\n- тъмен режим",
                'publish' => '1',
            ])->assertRedirect('/admin/changelog');

        $entry = ChangelogEntry::query()->firstOrFail();

        $this->assertNotNull($entry->published_at);
        $this->assertSame('333', $entry->discord_channel_id);
        $this->assertSame('999', $entry->discord_message_id);
        $this->assertFalse($entry->discord_is_thread);

        $this->get('/changelog')
            ->assertOk()
            ->assertSee('class="release"', false)
            ->assertSee('Нова карта')
            ->assertSee('<strong>по-бърза</strong>', false)
            ->assertSee('<li>тъмен режим</li>', false)
            ->assertSee('id="'.$entry->id.'"', false);

        Http::assertSent(function ($request) use ($entry): bool {
            return str_contains($request->url(), '/channels/333/messages')
                && $request['content'] === "**[Нова карта](<https://kostaa.space/changelog#{$entry->id}>)**\n**по-бърза** е\n\n- тъмен режим";
        });
    }

    public function test_a_draft_stays_off_the_site_and_discord(): void
    {
        $this->actingAs(User::query()->where('name', 'ZenSorcerer')->firstOrFail())
            ->post('/admin/changelog', [
                'title' => 'Скрита',
                'body' => 'Още не.',
                'publish' => '0',
            ])->assertRedirect('/admin/changelog');

        $this->assertNull(ChangelogEntry::query()->firstOrFail()->published_at);
        $this->get('/changelog')->assertOk()->assertDontSee('Скрита');
        Http::assertNothingSent();
    }

    public function test_editing_a_published_change_updates_the_discord_message(): void
    {
        $entry = ChangelogEntry::query()->create([
            'title' => 'Старо',
            'body' => 'Текст',
            'published_at' => now(),
            'discord_channel_id' => '333',
            'discord_message_id' => '999',
        ]);

        $this->actingAs(User::query()->where('name', 'ZenSorcerer')->firstOrFail())
            ->put('/admin/changelog/'.$entry->id, [
                'title' => 'Ново',
                'body' => 'Сменено',
                'publish' => '1',
            ])->assertRedirect('/admin/changelog');

        Http::assertSent(function ($request) use ($entry): bool {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/channels/333/messages/999')
                && $request['content'] === "**[Ново](<https://kostaa.space/changelog#{$entry->id}>)**\nСменено";
        });
    }

    public function test_unpublishing_removes_the_discord_message(): void
    {
        $entry = ChangelogEntry::query()->create([
            'title' => 'Жива',
            'body' => 'Текст',
            'published_at' => now(),
            'discord_channel_id' => '333',
            'discord_message_id' => '999',
        ]);

        $this->actingAs(User::query()->where('name', 'ZenSorcerer')->firstOrFail())
            ->put('/admin/changelog/'.$entry->id, [
                'title' => 'Жива',
                'body' => 'Текст',
                'publish' => '0',
            ])->assertRedirect('/admin/changelog');

        $entry->refresh();
        $this->assertNull($entry->published_at);
        $this->assertNull($entry->discord_message_id);
        $this->get('/changelog')->assertDontSee('Жива');

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_contains($request->url(), '/channels/333/messages/999'));
    }

    public function test_the_misspelled_changelog_channel_still_receives_the_post(): void
    {
        $this->discordChannels = [
            ['id' => '777', 'type' => 0, 'name' => 'chanelog'],
        ];

        $this->actingAs(User::query()->where('name', 'ZenSorcerer')->firstOrFail())
            ->post('/admin/changelog', [
                'title' => 'Канал',
                'body' => 'Стига.',
                'publish' => '1',
            ])->assertRedirect('/admin/changelog');

        $this->assertSame('777', ChangelogEntry::query()->firstOrFail()->discord_channel_id);
    }

    public function test_a_forum_changelog_channel_opens_a_thread(): void
    {
        $this->discordChannels = [
            ['id' => '444', 'type' => 15, 'name' => 'changelog'],
        ];

        $this->actingAs(User::query()->where('name', 'ZenSorcerer')->firstOrFail())
            ->post('/admin/changelog', [
                'title' => 'Тема',
                'body' => 'Първи ред',
                'publish' => '1',
            ])->assertRedirect('/admin/changelog');

        $entry = ChangelogEntry::query()->firstOrFail();
        $this->assertTrue($entry->discord_is_thread);
        $this->assertSame('555', $entry->discord_channel_id);
        $this->assertSame('555', $entry->discord_message_id);
    }

    public function test_the_old_about_page_goes_to_the_changelog(): void
    {
        $this->get('/about')->assertRedirect('/changelog');
    }
}
