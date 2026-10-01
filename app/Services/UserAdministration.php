<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 管理者によるユーザー管理と招待。管理画面と CLI（wiki:disable-2fa など）から使う。
 *
 * 管理者がいなくなると誰もユーザー管理・サイト設定をできなくなるため、
 * 最後の管理者の降格・削除は拒否する。
 */
class UserAdministration
{
    /** 招待リンクの有効日数の既定値 */
    public const DEFAULT_INVITATION_DAYS = 7;

    /**
     * @param  array{name: string, login_id: string, email: ?string, password: string}  $data
     */
    public function createUser(array $data, bool $isAdmin = false): User
    {
        $user = new User($data);
        $user->is_admin = $isAdmin;
        $user->save();

        return $user;
    }

    /**
     * @param  array{name: string, login_id: string, email: ?string}  $data
     *
     * @throws ValidationException 最後の管理者を降格しようとしたとき
     */
    public function updateUser(User $user, array $data, bool $isAdmin): User
    {
        if ($user->is_admin && ! $isAdmin && $this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'is_admin' => '管理者が 1 人しかいないため、管理者の権限を外せません。',
            ]);
        }

        $user->fill($data);
        $user->is_admin = $isAdmin;
        $user->save();

        return $user;
    }

    /** パスワードを再設定し、「ログインしたままにする」の記憶も無効にする */
    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function disableTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * ユーザーを削除する。作成・編集したページと履歴は残る（ユーザー参照は NULL になる）。
     *
     * @throws ValidationException 自分自身、または最後の管理者を削除しようとしたとき
     */
    public function deleteUser(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'user' => '自分自身は管理画面から削除できません。個人設定から削除してください。',
            ]);
        }

        if ($this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'user' => '管理者が 1 人しかいないため、削除できません。',
            ]);
        }

        $user->delete();
    }

    /** 管理者のうち最後の 1 人か */
    public function isLastAdmin(User $user): bool
    {
        return $user->is_admin && User::where('is_admin', true)->count() <= 1;
    }

    /**
     * 招待リンクを発行する。平文のトークンは保存しないため、戻り値でだけ返す。
     *
     * @return array{0: Invitation, 1: string} 招待と平文のトークン
     */
    public function createInvitation(User $creator, int $days = self::DEFAULT_INVITATION_DAYS): array
    {
        $token = Str::random(40);

        $invitation = Invitation::create([
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => Carbon::now()->addDays($days),
            'created_by' => $creator->id,
        ]);

        return [$invitation, $token];
    }

    /** 平文のトークンから、使える招待を探す */
    public function findUsableInvitation(string $token): ?Invitation
    {
        return Invitation::usable()->where('token_hash', Invitation::hashToken($token))->first();
    }

    /**
     * 招待リンクからユーザーを登録する。同じ招待が同時に使われても 1 人しか登録されない。
     *
     * @param  array{name: string, login_id: string, email: ?string, password: string}  $data
     *
     * @throws ValidationException 招待が使用済み・期限切れになっていたとき
     */
    public function acceptInvitation(Invitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data) {
            $user = $this->createUser($data);

            // 未使用のときだけ使用済みにする（同時に使われた場合は片方だけが成功する）
            $claimed = Invitation::query()
                ->whereKey($invitation->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now(), 'used_by' => $user->id]);

            if ($claimed === 0) {
                throw ValidationException::withMessages([
                    'invitation' => 'この招待リンクは使用済みか、有効期限が切れています。',
                ]);
            }

            return $user;
        });
    }
}
