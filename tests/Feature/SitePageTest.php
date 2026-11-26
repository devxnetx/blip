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

    public function test_history_is_a_placeholder_page(): void
    {
        $this->get('/history')
            ->assertOk()
            ->assertSee('История')
            ->assertSee('Миналите събития ще са тук.')
            ->assertSee('href="'.route('history').'"', false);
    }

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
            ->assertSee('<details class="block" open>', false)
            ->assertDontSee('Пин тук');

        $html = $this->get('/how')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<details class="block" open>'));
        $this->assertSame(10, substr_count($html, '<details class="block"'));
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
            ->assertSee('contact-grid', false)
            ->assertSee('social-instagram', false)
            ->assertSee('https://instagram.com/kostaa.test', false)
            ->assertSee('Още няма връзка.')
            ->assertSee('Колко е', false)
            ->assertSee('Причина');

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

        $this->get('/contact')->assertOk();
        $check = session('contact_check');
        $this->assertIsArray($check);

        $this->from('/contact')->post('/contact', [
            'name' => '  ',
            'email' => 'not-an-email',
            'message' => '',
            'check' => $check['answer'],
            'check_token' => $check['token'],
        ])->assertRedirect('/contact')
            ->assertSessionHasErrors(['name', 'email', 'reason', 'message']);

        $this->assertSame(0, ContactMessage::query()->count());

        $this->post('/contact', [
            'name' => 'Ива',
            'email' => 'iva@example.com',
            'reason' => 'map',
            'message' => 'Картата не зарежда този пин.',
            'check' => $check['answer'],
            'check_token' => $check['token'],
            'company' => 'spam-bot',
        ])->assertRedirect('/contact');

        $this->assertSame(0, ContactMessage::query()->count());

        $this->from('/contact')->post('/contact', [
            'name' => 'Ива',
            'email' => 'iva@example.com',
            'reason' => 'map',
            'message' => 'Картата не зарежда този пин.',
            'check' => '0',
            'check_token' => $check['token'],
        ])->assertRedirect('/contact')
            ->assertSessionHasErrors('check');

        $this->assertSame(0, ContactMessage::query()->count());

        $this->post('/contact', [
            'name' => 'Ива',
            'email' => 'iva@example.com',
            'reason' => 'map',
            'message' => '<script>alert(1)</script> Картата не зарежда този пин.',
            'check' => $check['answer'],
            'check_token' => $check['token'],
        ])->assertRedirect('/contact')
            ->assertSessionHas('status');

        $stored = ContactMessage::query()->firstOrFail();
        $this->assertSame('Ива', $stored->name);
        $this->assertSame('map', $stored->reason);
        $this->assertNull($stored->read_at);

        $admin = User::query()->where('name', 'ZenSorcerer')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/contacts')
            ->assertOk()
            ->assertSee('Съобщения')
            ->assertSee('Ива')
            ->assertSee('iva@example.com')
            ->assertSee('Нещо на картата')
            ->assertSee('Ново')
            ->assertSee('class="nav-count">1', false);

        $this->actingAs($admin)
            ->get('/admin/contacts/'.$stored->id)
            ->assertOk()
            ->assertSee('Нещо на картата')
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
