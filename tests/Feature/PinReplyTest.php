<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\Reply;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PinReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reply_is_one_message_and_only_the_pin_author_can_read_it(): void
    {
        $author = (string) Str::uuid();
        $helper = (string) Str::uuid();
        $pinId = $this->dropPin($author);

        $this->withHeader('X-Device-Id', $author)->postJson('/api/pins/'.$pinId.'/replies', [
            'message' => 'I have them',
        ])->assertUnprocessable()->assertJsonValidationErrors('reply');

        $this->withHeader('X-Device-Id', $helper)->postJson('/api/pins/'.$pinId.'/replies', [
            'message' => '   ',
        ])->assertUnprocessable()->assertJsonValidationErrors('message');

        $this->withHeader('X-Device-Id', $helper)->postJson('/api/pins/'.$pinId.'/replies', [
            'message' => '  yo its mine call this number 089238997  ',
            'author_only' => true,
        ])->assertCreated()
            ->assertJsonPath('message', 'yo its mine call this number 089238997')
            ->assertJsonPath('author_only', true)
            ->assertJsonMissingPath('device_hash');

        $this->withHeader('X-Device-Id', $helper)->postJson('/api/pins/'.$pinId.'/replies', [
            'message' => 'Second try',
        ])->assertUnprocessable()->assertJsonValidationErrors('reply');

        $this->assertSame(1, Reply::query()->count());

        $stranger = (string) Str::uuid();

        $this->withHeader('X-Device-Id', $stranger)->getJson('/api/pins/'.$pinId.'/replies')
            ->assertOk()
            ->assertJsonCount(0, 'replies')
            ->assertDontSee('089238997');

        $this->withHeader('X-Device-Id', $helper)->getJson('/api/pins/'.$pinId.'/replies')
            ->assertOk()
            ->assertJsonCount(1, 'replies')
            ->assertJsonPath('replies.0.message', 'yo its mine call this number 089238997')
            ->assertJsonPath('replies.0.mine', true);

        $this->withHeader('X-Device-Id', $author)->getJson('/api/pins/'.$pinId.'/replies')
            ->assertOk()
            ->assertJsonCount(1, 'replies')
            ->assertJsonPath('replies.0.message', 'yo its mine call this number 089238997')
            ->assertJsonPath('replies.0.author_only', true)
            ->assertJsonPath('replies.0.mine', false)
            ->assertJsonMissingPath('replies.0.device_hash');

        $public = (string) Str::uuid();
        $this->withHeader('X-Device-Id', $public)->postJson('/api/pins/'.$pinId.'/replies', [
            'message' => 'Left them at the cafe',
        ])->assertCreated()
            ->assertJsonPath('author_only', false);

        $this->withHeader('X-Device-Id', $stranger)->getJson('/api/pins/'.$pinId.'/replies')
            ->assertOk()
            ->assertJsonCount(1, 'replies')
            ->assertJsonPath('replies.0.message', 'Left them at the cafe')
            ->assertDontSee('089238997');

        $this->withHeader('X-Device-Id', $helper)->getJson('/api/pins?'.http_build_query([
            'north' => 42.8,
            'south' => 42.6,
            'east' => 23.5,
            'west' => 23.2,
        ]))
            ->assertOk()
            ->assertJsonPath('pins.0.replied', true)
            ->assertDontSee('089238997')
            ->assertDontSee('Left them at the cafe');
    }

    public function test_five_different_reports_delete_the_pin(): void
    {
        Storage::fake('public');
        $author = (string) Str::uuid();
        $pinId = $this->dropPin($author, withImage: true);
        $image = Pin::query()->findOrFail($pinId)->image_path;

        Reply::query()->create([
            'pin_id' => $pinId,
            'device_hash' => hash('sha256', (string) Str::uuid()),
            'message' => 'Call me',
        ]);

        $this->withHeader('X-Device-Id', $author)
            ->postJson('/api/pins/'.$pinId.'/report')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('report');

        $repeat = (string) Str::uuid();

        for ($i = 0; $i < Report::DELETES_AT - 1; $i++) {
            $device = $i === 0 ? $repeat : (string) Str::uuid();
            $this->withHeader('X-Device-Id', $device)
                ->postJson('/api/pins/'.$pinId.'/report')
                ->assertOk()
                ->assertJsonPath('deleted', false);
        }

        $this->withHeader('X-Device-Id', $repeat)
            ->postJson('/api/pins/'.$pinId.'/report')
            ->assertOk()
            ->assertJsonPath('deleted', false);

        $this->assertNotNull(Pin::query()->find($pinId));
        $this->assertSame(Report::DELETES_AT - 1, Report::query()->count());

        $this->withHeader('X-Device-Id', (string) Str::uuid())
            ->postJson('/api/pins/'.$pinId.'/report')
            ->assertOk()
            ->assertJsonPath('reported', true)
            ->assertJsonPath('deleted', true);

        $this->assertNull(Pin::query()->find($pinId));
        $this->assertSame(0, Reply::query()->count());
        $this->assertSame(0, Report::query()->count());
        Storage::disk('public')->assertMissing($image);
    }

    public function test_an_imported_pin_has_no_replies_or_reports(): void
    {
        $pin = Pin::query()->create([
            'device_hash' => hash('sha256', 'kostaa-import:eventibg'),
            'message' => 'Събор',
            'description' => 'Цяла седмица на площада.',
            'category' => 'event',
            'source' => 'eventibg',
            'external_id' => 'sabor',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'expires_at' => now()->addHour(),
        ]);

        $device = (string) Str::uuid();

        $this->getJson('/api/pins/'.$pin->id)
            ->assertOk()
            ->assertJsonPath('pin.imported', true)
            ->assertJsonPath('pin.description', 'Цяла седмица на площада.')
            ->assertJsonPath('pin.category', 'event');

        $this->withHeader('X-Device-Id', $device)
            ->getJson('/api/pins/'.$pin->id.'/replies')
            ->assertOk()
            ->assertJsonCount(0, 'replies');

        $this->withHeader('X-Device-Id', $device)
            ->postJson('/api/pins/'.$pin->id.'/replies', ['message' => 'Идвам'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reply');

        $this->withHeader('X-Device-Id', $device)
            ->postJson('/api/pins/'.$pin->id.'/report')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('report');

        $this->assertSame(0, Reply::query()->count());
        $this->assertSame(0, Report::query()->count());
    }

    public function test_an_expired_pin_cannot_be_replied_to_or_reported(): void
    {
        $pin = Pin::query()->create([
            'device_hash' => hash('sha256', (string) Str::uuid()),
            'message' => 'Gone',
            'latitude' => 42.7,
            'longitude' => 23.3,
            'expires_at' => now()->subMinute(),
        ]);

        $device = (string) Str::uuid();

        $this->withHeader('X-Device-Id', $device)
            ->postJson('/api/pins/'.$pin->id.'/replies', ['message' => 'Hello'])
            ->assertNotFound();

        $this->withHeader('X-Device-Id', $device)
            ->postJson('/api/pins/'.$pin->id.'/report')
            ->assertNotFound();
    }

    private function dropPin(string $device, bool $withImage = false): int
    {
        $payload = [
            'message' => 'Lost keys here',
            'latitude' => 42.6977,
            'longitude' => 23.3219,
            'duration' => '1h',
        ];

        if ($withImage) {
            $payload['image'] = UploadedFile::fake()->image('keys.jpg');
        }

        return $this->withHeader('X-Device-Id', $device)
            ->post('/api/pins', $payload)
            ->assertCreated()
            ->json('id');
    }
}
