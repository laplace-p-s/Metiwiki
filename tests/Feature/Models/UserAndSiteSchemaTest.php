<?php

namespace Tests\Feature\Models;

use App\Models\Invitation;
use App\Models\Setting;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAndSiteSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_email_is_optional()
    {
        $user = User::factory()->withoutEmail()->create();

        $this->assertNull($user->fresh()->email);
    }

    public function test_login_id_is_unique()
    {
        User::factory()->create(['login_id' => 'alice']);

        $this->expectException(QueryException::class);

        User::factory()->create(['login_id' => 'alice']);
    }

    public function test_is_admin_defaults_to_false_and_is_not_mass_assignable()
    {
        $user = User::create([
            'name' => 'Alice',
            'login_id' => 'alice',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertTrue(User::factory()->admin()->create()->is_admin);
    }

    public function test_setting_uses_key_as_primary_key()
    {
        Setting::create(['key' => 'site_name', 'value' => 'My Wiki']);

        $this->assertSame('My Wiki', Setting::find('site_name')->value);
    }

    public function test_deleting_user_keeps_uploads()
    {
        $upload = Upload::factory()->create();

        $upload->user->delete();

        $this->assertNull($upload->fresh()->user_id);
    }

    public function test_invitation_usability()
    {
        $usable = Invitation::factory()->withToken('plain-token')->create();
        $expired = Invitation::factory()->expired()->create();
        $used = Invitation::factory()->used()->create();

        $this->assertTrue($usable->isUsable());
        $this->assertFalse($expired->isUsable());
        $this->assertFalse($used->isUsable());
        $this->assertSame([$usable->id], Invitation::usable()->pluck('id')->all());
        $this->assertSame($usable->id, Invitation::where('token_hash', Invitation::hashToken('plain-token'))->value('id'));
    }

    public function test_deleting_issuer_keeps_invitation()
    {
        $invitation = Invitation::factory()->create();

        $invitation->creator->delete();

        $this->assertNull($invitation->fresh()->created_by);
    }
}
