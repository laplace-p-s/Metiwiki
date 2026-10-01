<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\UserAdministration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

/**
 * パスワードの再設定（メールを設定していない環境で、管理者がパスワードを忘れたときの救済）
 */
class ResetPasswordCommand extends Command
{
    use PasswordValidationRules;

    protected $signature = 'wiki:reset-password
        {login_id : 対象ユーザーのログイン ID}
        {--password= : 新しいパスワード（省略すると対話で入力）}';

    protected $description = 'ユーザーのパスワードを再設定する';

    public function handle(UserAdministration $users): int
    {
        $user = User::where('login_id', mb_strtolower((string) $this->argument('login_id')))->first();

        if (! $user) {
            $this->error('そのログイン ID のユーザーはいません。');

            return self::FAILURE;
        }

        $data = [
            'password' => $this->option('password') ?? password('新しいパスワード', required: true),
            'password_confirmation' => $this->option('password') ?? password('新しいパスワード（確認用）', required: true),
        ];

        $validator = Validator::make($data, ['password' => $this->passwordRules()]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $users->resetPassword($user, (string) $data['password']);

        $this->info("「{$user->name}」（{$user->login_id}）のパスワードを再設定しました。");

        return self::SUCCESS;
    }
}
