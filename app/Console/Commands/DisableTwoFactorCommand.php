<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAdministration;
use Illuminate\Console\Command;

/**
 * 2 段階認証の解除（認証アプリの端末をなくし、リカバリーコードも無いときの救済）
 */
class DisableTwoFactorCommand extends Command
{
    protected $signature = 'wiki:disable-2fa {login_id : 対象ユーザーのログイン ID}';

    protected $description = 'ユーザーの 2 段階認証を解除する';

    public function handle(UserAdministration $users): int
    {
        $user = User::where('login_id', mb_strtolower((string) $this->argument('login_id')))->first();

        if (! $user) {
            $this->error('そのログイン ID のユーザーはいません。');

            return self::FAILURE;
        }

        $users->disableTwoFactor($user);

        $this->info("「{$user->name}」（{$user->login_id}）の 2 段階認証を解除しました。");

        return self::SUCCESS;
    }
}
