<?php

namespace Tests\Feature\Admin;

use App\Models\Invitation;
use App\Models\User;
use App\Services\UserAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<string, string> */
    private function registration(array $overrides = []): array
    {
        return [
            'name' => '招待された人',
            'login_id' => 'Guest1',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }

    public function test_admin_can_issue_invitation_and_see_url_once()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.invitations.store'), ['days' => 3])
            ->assertRedirect(route('admin.invitations.index'));

        $invitation = Invitation::sole();
        $this->assertSame($this->admin->id, $invitation->created_by);
        $this->assertTrue($invitation->expires_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));

        $response->assertInertiaFlash('invitationUrl');

        // 平文のトークンは URL の中にしか無く、DB にはハッシュだけが残る
        $url = session()->get(SessionKey::FLASH_DATA)['invitationUrl'];
        $this->assertStringStartsWith(url('/-/invite/'), $url);
        $this->assertSame($invitation->token_hash, Invitation::hashToken(basename($url)));
        $this->assertStringNotContainsString($invitation->token_hash, $url);
    }

    public function test_invitation_list_does_not_expose_tokens()
    {
        app(UserAdministration::class)->createInvitation($this->admin);

        $this->actingAs($this->admin)
            ->get(route('admin.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Invitations')
                ->where('invitations.data.0.status', 'active')
                ->missing('invitations.data.0.token_hash'),
            );
    }

    public function test_invitation_days_are_validated()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.invitations.store'), ['days' => 0])
            ->assertSessionHasErrors('days');
    }

    public function test_guest_can_register_with_invitation_even_when_registration_is_closed()
    {
        [$invitation, $token] = app(UserAdministration::class)->createInvitation($this->admin);

        $this->get(route('invitation.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/AcceptInvitation')->where('valid', true));

        $this->post(route('invitation.store', $token), $this->registration())->assertRedirect('/');

        $user = User::where('login_id', 'guest1')->sole();
        $this->assertFalse($user->is_admin);
        $this->assertAuthenticatedAs($user);

        $invitation->refresh();
        $this->assertNotNull($invitation->used_at);
        $this->assertSame($user->id, $invitation->used_by);
    }

    public function test_invitation_can_be_used_only_once()
    {
        [, $token] = app(UserAdministration::class)->createInvitation($this->admin);

        $this->post(route('invitation.store', $token), $this->registration());
        auth()->logout();

        $this->get(route('invitation.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('valid', false));
        $this->post(route('invitation.store', $token), $this->registration(['login_id' => 'second']))
            ->assertSessionHasErrors('invitation');

        $this->assertSame(0, User::where('login_id', 'second')->count());
    }

    public function test_expired_or_unknown_invitation_is_rejected()
    {
        $token = 'expired-token';
        Invitation::factory()->withToken($token)->expired()->create();

        $this->post(route('invitation.store', $token), $this->registration())->assertSessionHasErrors('invitation');
        $this->post(route('invitation.store', 'unknown'), $this->registration())->assertSessionHasErrors('invitation');

        $this->assertSame(0, User::where('login_id', 'guest1')->count());
    }

    public function test_invitation_registration_is_validated()
    {
        [, $token] = app(UserAdministration::class)->createInvitation($this->admin);

        $this->post(route('invitation.store', $token), $this->registration(['login_id' => $this->admin->login_id]))
            ->assertSessionHasErrors('login_id');

        $this->assertNull(Invitation::sole()->used_at);
    }

    public function test_logged_in_users_cannot_open_invitation()
    {
        [, $token] = app(UserAdministration::class)->createInvitation($this->admin);

        $this->actingAs($this->admin)->get(route('invitation.show', $token))->assertRedirect('/');
    }

    public function test_admin_can_revoke_unused_invitation()
    {
        [$invitation, $token] = app(UserAdministration::class)->createInvitation($this->admin);

        $this->actingAs($this->admin)
            ->delete(route('admin.invitations.destroy', $invitation))
            ->assertRedirect(route('admin.invitations.index'));

        $this->assertSame(0, Invitation::count());

        auth()->logout();
        $this->post(route('invitation.store', $token), $this->registration())->assertSessionHasErrors('invitation');
    }

    public function test_used_invitation_cannot_be_revoked()
    {
        $invitation = Invitation::factory()->used()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.invitations.destroy', $invitation))
            ->assertNotFound();
    }
}
