<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Setup\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * コマンドラインでの初期設定。Web の初回設定画面（/-/setup）と同じ処理を行う。
 */
class InstallCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    protected $signature = 'wiki:install
        {--site-name= : Wiki 名}
        {--name= : 管理者の名前}
        {--login-id= : 管理者のログイン ID}
        {--email= : 管理者のメールアドレス（任意）}
        {--password= : 管理者のパスワード（省略すると対話で入力）}
        {--samples : 使い方のサンプルページを作成する}
        {--no-samples : 使い方のサンプルページを作成せず、ホームだけを作る}';

    protected $description = 'Metiwiki の初期設定（APP_KEY の生成・データベースの準備・管理者の作成）を行う';

    public function handle(Installer $installer): int
    {
        $manualEnv = $installer->ensureAppKey();

        if ($manualEnv !== null) {
            $this->error('設定ファイル（'.app()->environmentFilePath().'）に書き込めませんでした。次の内容で作成してから、もう一度実行してください。');
            $this->line($manualEnv);

            return self::FAILURE;
        }

        try {
            $installer->prepareDatabase();
        } catch (Throwable $e) {
            $this->error('データベースを準備できませんでした: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($installer->isInstalled()) {
            $this->info('初期設定は既に完了しています。');

            return self::SUCCESS;
        }

        $data = [
            'site_name' => $this->option('site-name') ?? text('Wiki 名', default: 'Metiwiki', required: true),
            'name' => $this->option('name') ?? text('管理者の名前', required: true),
            'login_id' => mb_strtolower((string) ($this->option('login-id') ?? text('管理者のログイン ID', hint: '半角英数字・ハイフン・下線', required: true))),
            'email' => $this->option('email') ?? (text('管理者のメールアドレス（任意）') ?: null),
        ];
        $samplePages = match (true) {
            (bool) $this->option('no-samples') => false,
            (bool) $this->option('samples') => true,
            default => confirm('使い方のサンプルページを作成しますか？', default: true, hint: 'ホームからリンクする「Wikiの使い方」「Markdown記法」「サンプルページ」'),
        };
        $data['password'] = $this->option('password') ?? password('管理者のパスワード', required: true);
        $data['password_confirmation'] = $this->option('password') ?? password('パスワード（確認用）', required: true);

        $validator = Validator::make($data, [
            'site_name' => ['required', 'string', 'max:100'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $admin = $installer->install([
            'site_name' => (string) $data['site_name'],
            'name' => (string) $data['name'],
            'login_id' => $data['login_id'],
            'email' => $data['email'] !== null ? (string) $data['email'] : null,
            'password' => (string) $data['password'],
            'sample_pages' => $samplePages,
        ]);

        $this->info("初期設定が完了しました。ログイン ID「{$admin->login_id}」でログインできます。");

        return self::SUCCESS;
    }
}
