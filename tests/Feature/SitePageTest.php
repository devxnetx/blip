<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_guide_explains_pins_upcoming_events_and_filters(): void
    {
        $this->get('/how')
            ->assertOk()
            ->assertSee('Пин само там, където си')
            ->assertSee('Този пин ползва мястото, където стоиш.')
            ->assertSee('Предстоящо')
            ->assertSee('ДД.ММ.ГГГГ ЧЧ:ММ')
            ->assertSee('5 мин')
            ->assertSee('1 до 30 км')
            ->assertSee('Всички')
            ->assertSee('(2)')
            ->assertSee('около 40 метра')
            ->assertSee('Вече имаш пин на това място.')
            ->assertSee('Видимо само за автора на пина')
            ->assertSee('Пет доклада')
            ->assertSee('class="map-link"', false)
            ->assertSee('brand/icon-192.png', false)
            ->assertDontSee('Пин тук');
    }

    public function test_support_hides_donation_details_until_they_are_saved(): void
    {
        $this->get('/support')
            ->assertOk()
            ->assertSee('Revolut')
            ->assertSee('Ethereum')
            ->assertSee('Solana')
            ->assertSee('Още няма адрес.')
            ->assertDontSee('revolut.me', false)
            ->assertDontSee('0x', false);

        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();
        $eth = '0x1111111111111111111111111111111111111111';
        $sol = '11111111111111111111111111111111';

        $this->actingAs($admin)
            ->from('/admin/settings')
            ->put('/admin/settings', [
                'import_days_ahead' => 40,
                'discord_live_every_hours' => 3,
                'discord_live_ahead_hours' => 3,
                'donate_revolut' => 'https://revolut.me/kostaa-test',
                'donate_eth' => $eth,
                'donate_sol' => $sol,
                'social_instagram' => 'https://instagram.com/kostaa.test',
            ])
            ->assertRedirect('/admin/settings');

        $this->get('/support')
            ->assertOk()
            ->assertSee('https://revolut.me/kostaa-test', false)
            ->assertSee($eth, false)
            ->assertSee($sol, false)
            ->assertDontSee('Още няма адрес.');

        $this->get('/contact')
            ->assertOk()
            ->assertSee('https://instagram.com/kostaa.test', false)
            ->assertSee('Още няма връзка.');

        $this->actingAs($admin)
            ->from('/admin/settings')
            ->put('/admin/settings', [
                'import_days_ahead' => 40,
                'donate_eth' => '0x123',
            ])
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('donate_eth');

        $this->assertSame($eth, Setting::get('donate_eth'));
    }

    public function test_a_contact_message_is_stored_for_the_admin_to_read(): void
    {
        $this->get('/admin/contacts')->assertRedirect('/admin/login');

        $this->from('/contact')->post('/contact', [
            'name' => '  ',
            'email' => 'not-an-email',
            'message' => '',
        ])->assertRedirect('/contact')
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertSame(0, ContactMessage::query()->count());

        $this->post('/contact', [
            'name' => 'Ива',
            'email' => 'iva@example.com',
            'message' => 'Картата не зарежда този пин.',
            'company' => 'spam-bot',
        ])->assertRedirect('/contact');

        $this->assertSame(0, ContactMessage::query()->count());

        $this->post('/contact', [
            'name' => 'Ива',
            'email' => 'iva@example.com',
            'message' => '<script>alert(1)</script> Картата не зарежда този пин.',
        ])->assertRedirect('/contact')
            ->assertSessionHas('status');

        $stored = ContactMessage::query()->firstOrFail();
        $this->assertSame('Ива', $stored->name);
        $this->assertNull($stored->read_at);

        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/contacts')
            ->assertOk()
            ->assertSee('Съобщения')
            ->assertSee('Ива')
            ->assertSee('iva@example.com')
            ->assertSee('Ново')
            ->assertSee('class="nav-count">1', false);

        $this->actingAs($admin)
            ->get('/admin/contacts/'.$stored->id)
            ->assertOk()
            ->assertSee('Картата не зарежда този пин.')
            ->assertDontSee('<script>', false);

        $stored->refresh();
        $this->assertNotNull($stored->read_at);

        $this->actingAs($admin)
            ->get('/admin/contacts')
            ->assertOk()
            ->assertSee('Прочетено')
            ->assertDontSee('class="nav-count"', false);

        $this->actingAs($admin)
            ->delete('/admin/contacts/'.$stored->id)
            ->assertRedirect('/admin/contacts');

        $this->assertSame(0, ContactMessage::query()->count());
    }
}
