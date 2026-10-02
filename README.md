<p align="center"><img src="public/logo.svg" width="120" alt=""></p>

# Metiwiki

気軽に使えるシンプルなWikiアプリです。PHPが動くサーバーに配置するか、Dockerで起動するだけで使えます。データベースはSQLiteをベースとしているので、DBサーバーやキューワーカーなどの常駐プロセスのセットアップ無しで始められます。

- [主な機能](#主な機能)
- [デモサイト](#デモサイト)
- [動作要件](#動作要件)
- [入手](#入手)
- [Dockerでの導入](#dockerでの導入)
- [PHPサーバーへの導入](#phpサーバーへの導入)
- [設定](#設定)
- [バックアップ](#バックアップ)
- [更新](#更新)
- [困ったとき](#困ったとき)
- [開発](#開発)
- [ライセンス](#ライセンス)

## 主な機能

- **Markdown書式での記述**: 表・タスクリスト・打ち消し線など、CommonMarkとGitHub風の拡張に対応しています。編集中は、プレビューを横に並べて確認できます
- **Wikiリンク**: `[[ページ名]]`でページ同士をつなげます。まだ無いページへのリンクは赤く表示され、そこからページを作成できます
- **履歴**: 版の表示、ソースと描画結果での差分の比較、過去の版への復元ができます
- **改名と転送**: ページを改名しても、古いタイトルへのリンクは新しいページへ転送されます
- **画像**: 貼り付けやドラッグ＆ドロップでアップロードできます。JPEGの位置情報などのメタデータは、保存時に取り除きます
- **検索・一覧**: 全文検索、最近の更新、全ページ一覧に対応
- **ユーザー**: ログインIDとパスワードでログインします。2段階認証（TOTP）に対応しています。ユーザーの登録は、管理者による作成・招待リンク・自己登録（既定は無効）から選べます
- **閲覧はログイン不要**: 編集できるのはログインしたユーザーだけです
- **管理画面**: ユーザー管理、削除済みページの復元、画像の一覧と削除、Wiki名・テーマ色の設定ができます
- **見た目**: 13色のテーマ色に対応しています。個人設定でライト／ダークテーマも切り替えられます

画面の表示は日本語のみに対応しています。

## デモサイト

https://metiwiki-demo.wllab.dev/ にて動作を確認出来る環境を用意しています。編集を試すときは下記ユーザーでログインしてください。

| ログインID | パスワード |
| ---------- | ---------- |
| `demo`     | `demo`     |

書き込んだ内容やアップロードした画像は、しばらくすると消えて初期状態に戻ります。

誰でも閲覧・編集できる共有の環境です。個人情報や公開できない情報は書き込まないでください。

## 動作要件

### Dockerで動かす場合

- DockerとDocker Compose v2.24以降
- bash: 付属のスクリプトで使います。WindowsではGit BashかWSLを使ってください

### PHPサーバーで動かす場合

- PHP 8.3以上
    - 拡張: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, pdo_sqlite, session, tokenizer, xml
- Webサーバー: mod_rewriteを有効にしたApache、またはnginx＋PHP-FPM
- `https://wiki.example.com/`のようにサイトのルートで動かす前提です。`https://example.com/wiki/`のようなサブディレクトリへの設置には対応していません

### データベース

現時点では**SQLiteのみ**サポート。MySQLは動作を確認していないためサポート対象外です。

## 入手

[Releases](https://github.com/laplace-p-s/Metiwiki/releases)から`metiwiki-<バージョン>.zip`をダウンロードして展開します。展開すると`metiwiki/`フォルダができます。

このzipはDocker構成でもPHPサーバー構成でも使用できます。PHPの依存ライブラリとビルド済みの画面ファイルを含んでいるため、ComposerやNode.jsは不要です。Dockerで動かすための`compose.yaml`・`docker/`・`scripts/docker.sh`も同梱しています。これらは公開ディレクトリ`public/`の外にあるので、PHPサーバーで使う場合もWebからは見えません。

## Dockerでの導入

展開した`metiwiki/`フォルダで次を実行することでビルドを行ないます。

```bash
./scripts/docker.sh
```

イメージのビルドと起動が終わるとURLが表示されます。既定は`http://localhost:8080/`です。アクセスすると初回設定の画面が開くので、Wiki名と管理者のアカウントを入力してください。

### 操作

| コマンド                                 | 内容                                                            |
| ---------------------------------------- | --------------------------------------------------------------- |
| `./scripts/docker.sh`                    | ビルドして起動し、準備ができたらURLを表示します。`up`と同じです |
| `./scripts/docker.sh down`               | 停止します。データは残ります                                    |
| `./scripts/docker.sh logs`               | ログを表示し続けます。Ctrl+Cで終了します                        |
| `./scripts/docker.sh artisan <コマンド>` | コンテナ内で`php artisan`を実行します                           |

データベース・画像・ログ・暗号化キーはDockerのボリューム`metiwiki-app_data`に保存され、コンテナを作り直しても消えません。

bashを使えない環境では、`metiwiki/`フォルダで次を実行します。`metiwiki.env`を作った場合は、`--env-file metiwiki.env`も付けてください。

```bash
docker compose up -d --build
```

### 設定ファイル

`metiwiki/`フォルダの直下に`metiwiki.env`というファイルを作って設定します。次の例のように、1行に1項目ずつ`項目名=値`の形で書きます。`#`で始まる行はコメントです。変更後は、`./scripts/docker.sh`を実行し直すと反映されます。

```ini
# 公開するポート。既定は8080
METIWIKI_PORT=8080
# 利用者がアクセスするURL
APP_URL=https://wiki.example.com
```

使える項目は[設定](#設定)を参照してください。

### HTTPSで公開する

コンテナ自体はHTTPで起動します。HTTPSで公開する場合は証明書を扱うCaddyやnginxなどのリバースプロキシを前に配置し、`metiwiki.env`に以下の内容で設定します。

```ini
# コンテナのポートは、このマシンの中からだけ受け付けます。外から直接アクセスさせないためです
METIWIKI_PORT=127.0.0.1:8080
APP_URL=https://wiki.example.com
# リバースプロキシからのX-Forwarded-*ヘッダーを信頼します
TRUSTED_PROXIES=*
```

Caddyの例:

```
wiki.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

`TRUSTED_PROXIES=*`は、コンテナにリバースプロキシ経由でしか届かない場合にだけ使用してください。外から直接届く状態で指定するとヘッダーから接続元IPを偽装することが可能になるため、ログインの試行回数制限などが正しく働かなくなります。

## PHPサーバーへの導入

1. 展開した`metiwiki/`フォルダを、`/var/www/metiwiki`などサーバー上の場所に配置します
2. Webサーバーのドキュメントルートを`metiwiki/public`にします。設定例は[Webサーバーの設定](#webサーバーの設定)を参照してください
3. Webサーバーを動かすユーザー（例: `www-data`）が、次の場所に書き込めるようにします

    ```bash
    cd /var/www/metiwiki
    chown -R www-data storage bootstrap/cache database
    ```

4. 初期設定をします。次のどちらかの方法を選んでください
    - **ブラウザから**: サイトを開くと暗号化キーの生成・`.env`の作成・データベースの作成が自動で行われ、初回設定の画面になります。このためには`metiwiki/`直下にもWebサーバーから書き込める必要があります。`chown www-data /var/www/metiwiki`などで設定してください。書き込めない場合は作成すべき`.env`の内容が画面に表示されるので、それを`metiwiki/.env`として保存してから開き直してください
    - **コマンドから**: Webサーバーのユーザーで次を実行します。`.env`の作成・データベースの作成・管理者の作成までを対話で行います。使い方のサンプルページを作成するかも尋ねられます

        ```bash
        sudo -u www-data php artisan wiki:install
        ```

5. `.env`の`APP_URL`を利用者がアクセスするURLにします

### Webサーバーの設定

次の例は`metiwiki/`フォルダを`/var/www/metiwiki`に配置した場合の設定です。別の場所に配置した場合はパスを読み替えてください。

**Apache**: `public/.htaccess`を使用するのでmod_rewriteを有効にし、ドキュメントルートに`AllowOverride All`を設定します。

```apache
<VirtualHost *:80>
    ServerName wiki.example.com
    DocumentRoot /var/www/metiwiki/public

    <Directory /var/www/metiwiki/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**nginx**:

```nginx
server {
    listen 80;
    server_name wiki.example.com;
    root /var/www/metiwiki/public;

    index index.php;
    charset utf-8;
    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`fastcgi_pass`のソケットは環境に合わせて設定してください。

### 画像のアップロード上限

画像1枚の上限は`.env`の`WIKI_UPLOAD_MAX_KB`（既定5MB）、PHPの`upload_max_filesize`、`post_max_size`のうち、最も小さい値になります。実際の上限は編集画面に表示されます。例えば5MBの画像を扱う場合はPHP側を以下のように設定します。

```ini
upload_max_filesize = 8M
post_max_size = 10M
```

nginxでは`client_max_body_size`も合わせてください。

## 設定

Dockerの場合は`metiwiki.env`、PHPサーバーの場合は`.env`に設定します。Wiki名・テーマ色・自己登録の可否は管理画面の「サイト設定」から変更出来ます。

| 項目                      | 内容                                                                                                                                                                                       | 既定値                                                                     |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| `APP_URL`                 | 利用者がアクセスするURL                                                                                                                                                                    | Dockerは`http://localhost`、PHPサーバーはブラウザから初期設定したときのURL |
| `TRUSTED_PROXIES`         | 信頼するリバースプロキシのIPです。カンマ区切りで複数書くことができ、CIDRも使用できます。すべて信頼する場合は`*`と指定します。詳しくは[HTTPSで公開する](#httpsで公開する)を参照してください | なし                                                                       |
| `WIKI_UPLOAD_MAX_KB`      | 画像1枚の上限をKBで指定します                                                                                                                                                              | `5120`                                                                     |
| `MAIL_MAILER`ほか`MAIL_*` | メールの送信設定です。`log`以外にするとメールによるパスワードリセットが使えるようになります。書き方は[Laravelのドキュメント](https://laravel.com/docs/mail)を参照してください              | `log`                                                                      |
| `METIWIKI_PORT`           | Dockerでだけ使用します。公開するポートで`127.0.0.1:8080`のように待ち受けるアドレスも指定できます                                                                                           | `8080`                                                                     |

パスワードリセットのメールはユーザーにメールアドレスが登録されている場合だけ送付できます。メールを設定しない場合は管理者が管理画面からパスワードを再設定します。

## バックアップ

### 失ってはいけないもの

|              | Docker                                       | PHPサーバー                                        |
| ------------ | -------------------------------------------- | -------------------------------------------------- |
| データベース | ボリューム内の`database.sqlite`              | `database/database.sqlite`と、あれば`-wal`・`-shm` |
| 画像         | ボリューム内の`storage/app/private/uploads/` | `storage/app/private/uploads/`                     |
| 暗号化キー   | ボリューム内の`app.key`                      | `.env`の`APP_KEY`                                  |

**暗号化キーを失うと2段階認証を使っているユーザーがログインできなくなります**。2段階認証の秘密鍵をこのキーで暗号化しているためです。データベースと必ず一緒に保管してください。

### Dockerの場合

停止してからボリュームの中身をtarファイルにします。

```bash
./scripts/docker.sh down
docker run --rm -v metiwiki-app_data:/data:ro -v "$PWD":/backup alpine \
    tar czf /backup/metiwiki-backup.tar.gz -C /data .
./scripts/docker.sh
```

戻す場合は停止した状態で空のボリュームに展開します。

```bash
docker run --rm -v metiwiki-app_data:/data -v "$PWD":/backup alpine \
    tar xzf /backup/metiwiki-backup.tar.gz -C /data
```

### PHPサーバーの場合

Webサーバーを止めるか書き込みが無い時間帯に、`.env`・`database/`・`storage/app/`をコピーします。

## 更新

更新の前に必ず[バックアップ](#バックアップ)を取ってください。どちらの場合も新しい版のzipを[入手](#入手)と同じ方法でダウンロードし、今のフォルダとは別の場所に展開します。

### Dockerの場合

1. `metiwiki.env`を作っている場合は新しいフォルダへコピーします
2. 今のフォルダで`./scripts/docker.sh down`を、新しいフォルダで`./scripts/docker.sh`を実行します

データはボリュームに保存されているのでフォルダを入れ替えてもそのまま引き継がれます。データベースの更新（マイグレーション）も起動時に自動で行われます。

### PHPサーバーの場合

1. 今使っている`metiwiki/`から次を新しいフォルダへコピーします
    - `.env`
    - `database/database.sqlite`と、あれば`-wal`・`-shm`
    - `storage/app/`
2. 新しいフォルダに[PHPサーバーへの導入](#phpサーバーへの導入)の手順3と同じ書き込み権限を付与します
3. 古いフォルダと新しいフォルダを入れ替えます
4. データベースを更新します

    ```bash
    cd /var/www/metiwiki
    sudo -u www-data php artisan migrate --force
    ```

## 困ったとき

管理者のパスワードを忘れた、2段階認証の端末を失くした、といったときは、サーバー上のコマンドで救済できます。

| 内容               | Docker                                                         | PHPサーバー                                                     |
| ------------------ | -------------------------------------------------------------- | --------------------------------------------------------------- |
| パスワードの再設定 | `./scripts/docker.sh artisan wiki:reset-password <ログインID>` | `sudo -u www-data php artisan wiki:reset-password <ログインID>` |
| 2段階認証の解除    | `./scripts/docker.sh artisan wiki:disable-2fa <ログインID>`    | `sudo -u www-data php artisan wiki:disable-2fa <ログインID>`    |

ほかのユーザーについては管理者が管理画面からパスワードの再設定や2段階認証の解除をできます。

エラーの記録はDockerでは`./scripts/docker.sh logs`、PHPサーバーでは`storage/logs/laravel.log`で確認できます。

## 開発

Laravel 13、Vue 3、Inertia.js、Tailwind CSSを使用。Markdownはサーバー側で[league/commonmark](https://commonmark.thephpleague.com/)を使って描画します。

### 開発環境

Docker上の開発環境[Laravel Sail](https://laravel.com/docs/sail)を使います。ソースコードのルートで下記を実行します。

```bash
# PHPの依存を入れます。手元にPHP・Composerが無くても構いません
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 \
    composer install --ignore-platform-reqs

# .envを作成し、APP_ENV=local・APP_DEBUG=trueに変えます
cp .env.example .env

./vendor/bin/sail up -d
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

`http://localhost/`を開くと初回設定の画面になります。ポートは`.env`の`APP_PORT`で変えられます。

### テストとチェック

```bash
./vendor/bin/sail artisan test          # テスト
./vendor/bin/sail composer test         # 整形チェック・静的解析・テスト
./vendor/bin/sail npm run check         # フロントエンドの整形・lint
./vendor/bin/sail npm run types:check   # TypeScriptの型チェック
```

### 配布用zipを作る

```bash
./scripts/build-dist.sh
# → dist/metiwiki-<バージョン>.zip
```

必要なのはDockerとbashだけです。PHP・Composer・Node.jsはコンテナの中で動くため、インストールする必要はありません。ファイル名のバージョンは`config/app.php`の`version`、名前は`composer.json`の`name`から決定されます。bashを使えない環境では、次のコマンドで同じzipができます。

```bash
docker build -f docker/Dockerfile --target dist --output type=local,dest=dist .
```

zipに入るDocker用のファイルは`docker/runtime/`に配置されます。Docker版を試す場合はzipを作って展開したフォルダで`./scripts/docker.sh`を実行します。

## ライセンス

[MIT License](LICENSE)
