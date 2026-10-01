#!/usr/bin/env bash
#
# MetiwikiをDockerで起動・停止する（compose.yamlを使う）。必要なのはDocker（Compose v2）とbashだけ。
# Starts and stops Metiwiki with Docker (uses compose.yaml). Requires only Docker (Compose v2) and bash.
#
# 使い方 / Usage: scripts/docker.sh [up|down|logs|artisan <command>...]
#
# 配布用zipにscripts/docker.shとして入る（リポジトリではdocker/runtime/に置く）。

set -euo pipefail

usage() {
    cat <<'EOF'
使い方 / Usage: scripts/docker.sh [command]

コマンド / Commands:
  up                 イメージをビルドして起動し、準備ができたらURLを表示します（既定）
                     Build the image, start Metiwiki and print the URL when ready (default)
  down               停止します。データ（ボリュームmetiwiki-app_data）は残ります
                     Stop Metiwiki. Data (volume metiwiki-app_data) is kept
  logs               ログを表示し続けます（Ctrl+Cで終了）
                     Follow the logs (Ctrl+C to quit)
  artisan <args>...  コンテナ内でphp artisanを実行します
                     Run php artisan inside the container
                     例 / e.g. scripts/docker.sh artisan wiki:reset-password admin
  -h, --help         この説明を表示 / Show this help

設定は、展開したフォルダの直下のmetiwiki.envに書きます（任意。compose.yamlの冒頭を参照）。
Settings go in metiwiki.env next to compose.yaml (optional; see the top of compose.yaml).
EOF
}

error() {
    printf '%s\n' "$@" >&2
    exit 1
}

command_name="${1:-up}"
[ $# -gt 0 ] && shift

case "$command_name" in
    -h | --help)
        usage
        exit 0
        ;;
    up | down | logs | artisan) ;;
    *)
        usage >&2
        error "" "エラー: 不明なコマンドです: $command_name" "Error: unknown command: $command_name"
        ;;
esac

# どこから実行しても、展開したフォルダ（compose.yamlのある場所）で動かす
cd "$(dirname "$0")/.."
[ -f compose.yaml ] \
    || error "エラー: compose.yamlが見つかりません。配布用zipを展開したフォルダのscripts/docker.shを実行してください。" \
        "Error: compose.yaml was not found. Run scripts/docker.sh from the folder extracted from the distribution zip."

command -v docker >/dev/null 2>&1 \
    || error "エラー: dockerコマンドが見つかりません。Dockerをインストールしてください。" \
        "Error: the docker command was not found. Please install Docker."
docker info >/dev/null 2>&1 \
    || error "エラー: Dockerに接続できません。Dockerが起動しているか確認してください。" \
        "Error: cannot connect to Docker. Make sure Docker is running."
docker compose version >/dev/null 2>&1 \
    || error "エラー: docker compose（Compose v2）が使えません。Docker Compose v2.24以降が必要です。" \
        "Error: docker compose (Compose v2) is not available. Docker Compose v2.24 or later is required."

compose=(docker compose --file compose.yaml)
# metiwiki.envがあればcomposeの変数（METIWIKI_PORTなど）としても読む。
# 無いときに.env（PHPサーバーとして使ったときの設定）を読まないよう、空のファイルを渡す
if [ -f metiwiki.env ]; then
    compose+=(--env-file metiwiki.env)
else
    compose+=(--env-file /dev/null)
fi

case "$command_name" in
    up)
        "${compose[@]}" up --detach --build

        container="$("${compose[@]}" ps --quiet app)"
        [ -n "$container" ] || error "エラー: コンテナが起動していません。" "Error: the container is not running."

        echo "起動を待っています / Waiting for Metiwiki to become ready..."
        for _ in $(seq 1 90); do
            status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$container" 2>/dev/null || true)"
            case "$status" in
                healthy)
                    port="$("${compose[@]}" port app 80 | head -n 1)"
                    echo "起動しました / Metiwiki is ready: http://localhost:${port##*:}/"
                    exit 0
                    ;;
                unhealthy)
                    break
                    ;;
            esac
            if [ "$(docker inspect --format '{{.State.Running}}' "$container" 2>/dev/null)" != "true" ]; then
                break
            fi
            sleep 2
        done

        "${compose[@]}" logs --tail 50 app >&2 || true
        error "" "エラー: 起動できませんでした。上のログを確認してください。" \
            "Error: Metiwiki did not become ready. Check the logs above."
        ;;
    down)
        "${compose[@]}" down
        echo "停止しました。データはボリュームに残っています。"
        echo "Stopped. Data is kept in the volume."
        ;;
    logs)
        "${compose[@]}" logs --follow app
        ;;
    artisan)
        [ $# -gt 0 ] || error "エラー: artisanのコマンドを指定してください。" "Error: specify an artisan command."
        "${compose[@]}" exec --user www-data app php artisan "$@"
        ;;
esac
