#!/bin/sh
#
# Metiwikiの実行用イメージの起動処理。データはすべて/data（ボリューム）に置く。
#
#   1. APP_KEYを用意する（環境変数に無ければ/data/app.keyを使い、無ければ作る）
#   2. /dataの下のディレクトリを用意し、所有者をwww-dataにそろえる
#   3. マイグレーションを実行する（イメージを新しい版に入れ替えたときのDB更新を兼ねる）
#   4. 設定・ルート・ビューをキャッシュする
#   5. 渡されたコマンド（既定はApache）を起動する

set -eu

app_dir=/var/www/metiwiki
data_dir=/data
key_file="$data_dir/app.key"

cd "$app_dir"

artisan() {
    runuser -u www-data -- php artisan "$@"
}

# APP_KEY: 2段階認証の秘密などの暗号化に使うため、失うと2段階認証を使っている人がログインできなくなる。
# /dataに保存し、コンテナを作り直しても同じ値を使う
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$key_file" ]; then
        (umask 077 && php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;' > "$key_file")
        echo "metiwiki: APP_KEYを生成し${key_file}に保存しました（バックアップの対象に含めてください）"
    fi
    APP_KEY="$(cat "$key_file")"
    export APP_KEY
fi

for dir in \
    "$data_dir/storage/app/private" \
    "$data_dir/storage/app/public" \
    "$data_dir/storage/framework/cache/data" \
    "$data_dir/storage/framework/sessions" \
    "$data_dir/storage/framework/views" \
    "$data_dir/storage/logs"; do
    mkdir -p "$dir"
done

# バックアップから戻したファイルなどの所有者を直す（すでにwww-dataのものは触らない）
find "$data_dir" ! -user www-data -exec chown www-data:www-data {} +

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -e "${DB_DATABASE:-$data_dir/database.sqlite}" ]; then
    runuser -u www-data -- touch "${DB_DATABASE:-$data_dir/database.sqlite}"
fi

artisan migrate --force
artisan optimize

exec "$@"
