<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserAdministration;
use App\Support\PasswordRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者によるユーザー管理
 */
class UserController extends Controller
{
    public function __construct(private UserAdministration $users) {}

    public function index(Request $request): Response
    {
        $users = User::query()
            ->orderBy('login_id')
            ->paginate(50)
            ->through(fn (User $user) => [
                ...$this->userSummary($user),
                'created_at' => $user->created_at?->toIso8601String(),
                'is_self' => $user->is($request->user()),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Create', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordRequirements' => PasswordRequirements::describe(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->createUser([
            ...$request->profileData(),
            'password' => $request->string('password')->toString(),
        ], $request->boolean('is_admin'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$user->name}」を作成しました。"]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('admin/users/Edit', [
            'user' => $this->userSummary($user),
            'isSelf' => $user->is($request->user()),
            'isLastAdmin' => $this->users->isLastAdmin($user),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->updateUser($user, $request->profileData(), $request->boolean('is_admin'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$user->name}」を更新しました。"]);

        return to_route('admin.users.edit', $user);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->users->resetPassword($user, $request->string('password')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$user->name}」のパスワードを再設定しました。"]);

        return to_route('admin.users.edit', $user);
    }

    public function disableTwoFactor(User $user): RedirectResponse
    {
        $this->users->disableTwoFactor($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$user->name}」の 2 段階認証を解除しました。"]);

        return to_route('admin.users.edit', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->users->deleteUser($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$user->name}」を削除しました。"]);

        return to_route('admin.users.index');
    }

    /**
     * @return array{id: int, name: string, login_id: string, email: ?string, is_admin: bool, two_factor_enabled: bool}
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'login_id' => $user->login_id,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
        ];
    }
}
