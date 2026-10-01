<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Services\UserAdministration;
use App\Support\PasswordRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 招待リンクからのアカウント登録（自己登録が OFF でも使える）
 */
class InvitationAcceptController extends Controller
{
    public function __construct(private UserAdministration $users) {}

    public function show(string $token): Response
    {
        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'valid' => $this->users->findUsableInvitation($token) !== null,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordRequirements' => PasswordRequirements::describe(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->users->findUsableInvitation($token);

        if (! $invitation) {
            return back()->withErrors(['invitation' => 'この招待リンクは使用済みか、有効期限が切れています。']);
        }

        $user = $this->users->acceptInvitation($invitation, [
            ...$request->profileData(),
            'password' => $request->string('password')->toString(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'アカウントを登録しました。']);

        return redirect('/');
    }
}
