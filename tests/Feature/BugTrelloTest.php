<?php

namespace Tests\Feature;

use App\Models\TrelloBugCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BugTrelloTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $bugThread = [
        'id' => '900',
        'parent_id' => '1574990792634925096',
        'name' => 'Картата не се отваря',
        'applied_tags' => ['1575061005325705246'],
    ];

    private string $bugMessage = 'Става на телефона.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blip.discord_bot_token' => 'test-token',
            'blip.discord_guild_id' => '1574875809603133562',
            'blip.discord_bug_channel_id' => '1574990792634925096',
            'blip.trello_key' => 'test-key',
            'blip.trello_token' => 'test-token',
            'blip.trello_board' => 'f7V0aAwm',
            'blip.trello_bug_list' => 'To Do',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/threads/active')) {
                return Http::response([
                    'threads' => [$this->bugThread],
                ]);
            }

            if (str_contains($url, '/threads/archived')) {
                return Http::response(['threads' => [], 'has_more' => false]);
            }

            if (str_contains($url, '/channels/1574990792634925096')) {
                return Http::response([
                    'id' => '1574990792634925096',
                    'available_tags' => [
                        ['id' => '1575061005325705246', 'name' => 'сайт'],
                        ['id' => '1575061028385984603', 'name' => 'приложение'],
                    ],
                ]);
            }

            if (str_contains($url, '/messages/')) {
                return Http::response([
                    'id' => '900',
                    'content' => $this->bugMessage,
                ]);
            }

            if ($method === 'PATCH' && str_contains($url, '/channels/')) {
                return Http::response(['id' => '901'], 200);
            }

            if (str_contains($url, '/lists')) {
                return Http::response([
                    ['id' => 'list-todo', 'name' => 'To Do'],
                    ['id' => 'list-doing', 'name' => 'Doing'],
                ]);
            }

            if (str_contains($url, '/labels') && $method === 'GET') {
                return Http::response([
                    ['id' => 'lab-empty', 'name' => '', 'color' => 'blue'],
                ]);
            }

            if (str_contains($url, '/labels') && $method === 'POST') {
                $name = (string) $request['name'];

                return Http::response([
                    'id' => $name === 'приложение' ? 'lab-app' : 'lab-site',
                    'name' => $name,
                ], 200);
            }

            if (str_contains($url, '/cards') && $method === 'POST') {
                return Http::response(['id' => 'card-1'], 200);
            }

            if (str_contains($url, '/cards/card-1') && $method === 'PUT') {
                return Http::response(['id' => 'card-1'], 200);
            }

            return Http::response('missing '.$method.' '.$url, 404);
        });
    }

    public function test_a_bug_report_becomes_a_card_in_to_do_with_its_tag(): void
    {
        $this->artisan('bugs:trello')
            ->expectsOutput('CREATED 1 UPDATED 0 FAILED 0')
            ->assertSuccessful();

        $card = TrelloBugCard::query()->firstOrFail();
        $this->assertSame('900', $card->discord_thread_id);
        $this->assertSame('card-1', $card->trello_card_id);
        $this->assertSame(['сайт'], $card->tags);

        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/cards')) {
                return false;
            }

            return $request['idList'] === 'list-todo'
                && $request['name'] === 'Картата не се отваря'
                && $request['idLabels'] === 'lab-site'
                && $request['desc'] === "Става на телефона.\n\nhttps://discord.com/channels/1574875809603133562/900";
        });

        $this->artisan('bugs:trello')->assertSuccessful();

        $this->assertSame(1, TrelloBugCard::query()->count());
        $posts = collect(Http::recorded())->filter(
            fn ($pair) => $pair[0]->method() === 'POST' && str_contains($pair[0]->url(), '/cards')
        );
        $this->assertCount(1, $posts);
    }

    public function test_adding_a_tag_updates_the_card_labels(): void
    {
        TrelloBugCard::query()->create([
            'discord_thread_id' => '900',
            'trello_card_id' => 'card-1',
            'tags' => [],
        ]);

        $this->artisan('bugs:trello')
            ->expectsOutput('CREATED 0 UPDATED 1 FAILED 0')
            ->assertSuccessful();

        $this->assertSame(['сайт'], TrelloBugCard::query()->firstOrFail()->tags);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/cards/card-1')
                && $request['idLabels'] === 'lab-site';
        });
    }

    public function test_an_untagged_bug_is_tagged_in_discord_from_the_text(): void
    {
        $this->bugThread = [
            'id' => '901',
            'parent_id' => '1574990792634925096',
            'name' => 'Не се пуска',
            'applied_tags' => [],
        ];
        $this->bugMessage = 'Приложението спира след входа.';

        $this->artisan('bugs:trello')
            ->expectsOutput('CREATED 1 UPDATED 0 FAILED 0')
            ->assertSuccessful();

        $this->assertSame(['приложение'], TrelloBugCard::query()->firstOrFail()->tags);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/channels/901')
                && $request['applied_tags'] === ['1575061028385984603'];
        });

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/cards')
                && $request['idLabels'] === 'lab-app';
        });
    }
}
