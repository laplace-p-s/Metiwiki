<?php

namespace App\Setup;

use App\Models\User;
use App\Services\ArticleService;
use App\Support\SiteSettings;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * 初期設定。Web の初回設定画面（/-/setup）と `php artisan wiki:install` の両方から使う。
 *
 * 1. APP_KEY が無ければ生成して .env に書き込む（ensureAppKey）
 * 2. SQLite のファイルを作り、マイグレーションを実行する（prepareDatabase）
 * 3. 管理者・Wiki 名・初期ページを作り、初期設定済みにする（install）
 */
class Installer
{
    public function __construct(
        private SiteSettings $settings,
        private ArticleService $articles,
    ) {}

    public function isInstalled(): bool
    {
        return $this->settings->isInstalled();
    }

    /**
     * APP_KEY が無ければ生成し、.env に書き込む（.env が無ければ .env.example から作る）。
     *
     * 生成したキーはこのプロセスの設定にも反映する。.env に書き込めなかった場合は、
     * 利用者が自分で配置するための .env の内容を返す（書き込めたときは null）。
     */
    public function ensureAppKey(?string $appUrl = null): ?string
    {
        if (! empty(config('app.key'))) {
            return null;
        }

        $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));
        $content = $this->environmentContent($key, $appUrl);

        config(['app.key' => $key]);
        app()->forgetInstance('encrypter');
        Facade::clearResolvedInstance('encrypter');

        $path = app()->environmentFilePath();

        if (@file_put_contents($path, $content) === false) {
            return $content;
        }

        return null;
    }

    /**
     * SQLite のファイルが無ければ作り、未実行のマイグレーションを実行する。
     *
     * @throws RuntimeException データベースを用意できなかったとき
     */
    public function prepareDatabase(): void
    {
        $connection = (string) config('database.default');

        if (config("database.connections.{$connection}.driver") === 'sqlite') {
            $path = (string) config("database.connections.{$connection}.database");

            if ($path !== ':memory:' && ! is_file($path) && @touch($path) === false) {
                throw new RuntimeException(
                    "SQLite のデータベースファイル（{$path}）を作成できませんでした。".
                    'このファイルを置くディレクトリに、Web サーバーから書き込めるようにしてください。'
                );
            }
        }

        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * 管理者を作成し、Wiki 名と初期ページを用意して、初期設定済みにする。
     * sample_pages が false のときは、初期ページをホームだけにする（省略時は true）。
     *
     * @param  array{site_name: string, name: string, login_id: string, email: ?string, password: string, sample_pages?: bool}  $data
     *
     * @throws RuntimeException 既に初期設定済みのとき
     */
    public function install(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $this->settings->flush();

            if ($this->isInstalled()) {
                throw new RuntimeException('初期設定は既に完了しています。');
            }

            $admin = new User([
                'name' => $data['name'],
                'login_id' => $data['login_id'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $admin->is_admin = true;
            $admin->save();

            $this->settings->set(SiteSettings::SITE_NAME, $data['site_name']);

            // 以前の初期設定の途中などでホームが既にあれば、作り直さない
            if ($this->articles->resolve(ArticleService::HOME_TITLE)['article'] === null) {
                $this->articles->createHomePage($admin, $data['site_name'], $data['sample_pages'] ?? true);
            }

            $this->settings->set(SiteSettings::INSTALLED_AT, now()->toIso8601String());

            return $admin;
        });
    }

    /**
     * APP_KEY（と、.env を新しく作るときは APP_URL）を書き込んだ .env の内容
     */
    private function environmentContent(string $key, ?string $appUrl): string
    {
        $path = app()->environmentFilePath();
        $isNew = ! is_file($path);

        $content = match (true) {
            ! $isNew => (string) file_get_contents($path),
            is_file(base_path('.env.example')) => (string) file_get_contents(base_path('.env.example')),
            default => "APP_KEY=\n",
        };

        $content = $this->setVariable($content, 'APP_KEY', $key);

        if ($isNew && $appUrl !== null) {
            $content = $this->setVariable($content, 'APP_URL', $appUrl);
        }

        return $content;
    }

    private function setVariable(string $content, string $name, string $value): string
    {
        $pattern = '/^'.preg_quote($name, '/').'=.*$/m';

        if (preg_match($pattern, $content)) {
            return (string) preg_replace_callback($pattern, fn () => "{$name}={$value}", $content);
        }

        return "{$name}={$value}\n".$content;
    }
}
