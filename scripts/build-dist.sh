#!/usr/bin/env bash
#
# Metiwiki の配布用 zip を作る。必要なのは Docker（BuildKit）と bash だけ。
# Builds the Metiwiki distribution zip. Requires only Docker (with BuildKit) and bash.
#
# 使い方 / Usage: scripts/build-dist.sh [--output DIR]

set -euo pipefail

usage() {
    cat <<'EOF'
使い方 / Usage: scripts/build-dist.sh [--output DIR]

配布用 zip（<名前>-<バージョン>.zip）を作ります。名前は composer.json の name、
バージョンは config/app.php の version から決まります。
Builds the distribution zip (<name>-<version>.zip). The name comes from "name"
in composer.json and the version from "version" in config/app.php.

オプション / Options:
  -o, --output DIR  zip の出力先（既定: dist）/ Output directory (default: dist)
  -h, --help        この説明を表示 / Show this help
EOF
}

error() {
    printf '%s\n' "$@" >&2
    exit 1
}

output_dir="dist"

while [ $# -gt 0 ]; do
    case "$1" in
        -o | --output)
            [ $# -ge 2 ] || error "エラー: $1 には出力先を指定してください。" "Error: $1 requires a directory."
            output_dir="$2"
            shift 2
            ;;
        -h | --help)
            usage
            exit 0
            ;;
        *)
            usage >&2
            error "" "エラー: 不明な引数です: $1" "Error: unknown argument: $1"
            ;;
    esac
done

# どこから実行してもリポジトリのルートで動かす（出力先の相対パスは実行した場所から解釈する）
case "$output_dir" in
    /*) ;;
    *) output_dir="$PWD/$output_dir" ;;
esac
cd "$(dirname "$0")/.."

command -v docker >/dev/null 2>&1 \
    || error "エラー: docker コマンドが見つかりません。Docker をインストールしてください。" \
        "Error: the docker command was not found. Please install Docker."
docker info >/dev/null 2>&1 \
    || error "エラー: Docker に接続できません。Docker が起動しているか確認してください。" \
        "Error: cannot connect to Docker. Make sure Docker is running."
docker buildx version >/dev/null 2>&1 \
    || error "エラー: BuildKit（docker buildx）が使えません。Docker 23 以降、または buildx プラグインが必要です。" \
        "Error: BuildKit (docker buildx) is not available. Docker 23+ or the buildx plugin is required."

# 未コミットの変更は zip に入るため知らせる（手元の修正を試す場合もあるので止めない）
if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    if [ -n "$(git status --porcelain)" ]; then
        echo "警告: 未コミットの変更があります。作業ツリーの内容がそのまま zip に入ります。" >&2
        echo "Warning: there are uncommitted changes. The working tree will be packaged as is." >&2
    fi
fi

work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

DOCKER_BUILDKIT=1 docker build \
    --file docker/Dockerfile \
    --target dist \
    --output "type=local,dest=$work_dir" \
    .

mkdir -p "$output_dir"

for zip_file in "$work_dir"/*.zip; do
    [ -e "$zip_file" ] || error "エラー: zip が作られませんでした。" "Error: no zip file was produced."
    target="$output_dir/$(basename "$zip_file")"
    if [ -e "$target" ]; then
        echo "上書きします / Overwriting: $target"
    fi
    mv "$zip_file" "$target"
    echo "作成しました / Created: $target"
done
