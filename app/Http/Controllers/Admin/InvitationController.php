<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\UserAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 招待リンクの発行・失効。自己登録が OFF でも、招待リンクからは登録できる。
 */
class InvitationController extends Controller
{
    public function __construct(private UserAdministration $users) {}

    public function index(): Response
    {
        $invitations = Invitation::query()
            ->with(['creator:id,name', 'usedBy:id,name'])
            ->latest('id')
            ->paginate(50)
            ->through(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'created_at' => $invitation->created_at?->toIso8601String(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'used_at' => $invitation->used_at?->toIso8601String(),
                'creator' => $invitation->creator?->name,
                'used_by' => $invitation->usedBy?->name,
                'status' => match (true) {
                    $invitation->used_at !== null => 'used',
                    $invitation->expires_at->isPast() => 'expired',
                    default => 'active',
                },
            ]);

        return Inertia::render('admin/Invitations', [
            'invitations' => $invitations,
            'defaultDays' => UserAdministration::DEFAULT_INVITATION_DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['days' => ['required', 'integer', 'min:1', 'max:90']],
            [],
            ['days' => '有効日数'],
        );

        [, $token] = $this->users->createInvitation($request->user(), (int) $validated['days']);

        // 平文のトークンは保存しないため、発行した直後の画面でだけ URL を見せる
        Inertia::flash('invitationUrl', route('invitation.show', $token));

        return to_route('admin.invitations.index');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->used_at !== null, 404);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => '招待リンクを失効させました。']);

        return to_route('admin.invitations.index');
    }
}
