# syntax=docker/dockerfile:1
#
# Metiwiki の配布用 zip を作る多段ビルド。ビルドコンテキストはリポジトリのルート。
# zip は PHP サーバーにも Docker にも使える兼用の配布物で、ビルド済みのアプリ（vendor・public/build 入り）に、
# Docker で動かすためのファイル（docker/runtime/ の中身）を加えたもの。
#
#   配布用 zip を作る（通常は scripts/build-dist.sh から実行する）:
#     docker build -f docker/Dockerfile --target dist --output type=local,dest=dist .
#   配布物の中身を展開した状態で取り出す:
#     docker build -f docker/Dockerfile --target app --output type=local,dest=<出力先> .
#
# 段の構成:
#   build   … PHP と Node で依存の導入とフロントエンドのビルドを行い、zip の中身を /dist に組み立てる
#   app     … /dist だけを取り出すための段
#   package … app を zip にまとめる
#   dist    … zip だけを取り出すための段（--target を省いたときはこれ）
#
# docker/runtime/ から zip への配置:
#   Dockerfile・Dockerfile.dockerignore・apache.conf・php.ini・entrypoint.sh → docker/
#   compose.yaml → compose.yaml（ルート）
#   docker.sh    → scripts/docker.sh

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

FROM composer:2 AS composer

FROM node:${NODE_VERSION}-trixie-slim AS node

# ---------------------------------------------------------------------------
# build: 依存の導入とフロントエンドのビルド
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS build

# Composer が配布アーカイブを展開するために unzip を使う
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# Wayfinder の Vite プラグインがビルド中に php artisan を呼ぶため、PHP のイメージに Node を載せる
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /app

# 依存の定義だけを先に入れ、ソースの変更で依存の導入をやり直さないようにする
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-progress

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .

# 本番用の依存だけでオートローダーとパッケージ一覧（bootstrap/cache）を作る。
# ビルド中の php artisan（Wayfinder）が storage に残すコンパイル済みビューなどは配布物に含めない
RUN composer dump-autoload --no-dev --optimize \
    && npm run build \
    && rm -rf storage/inertia-devtools \
    && find storage -type f ! -name .gitignore -delete

# zip の中身を /dist に組み立てる。
# 権限はディレクトリ 755・ファイル 644・artisan と scripts/docker.sh は 755 にそろえる
# （作業ツリーの権限をそのまま持ち込まない。書き込み可のままだと Web サーバーがコードを書き換えられる）。
# GNU chmod は 755 ではディレクトリの setgid を消さないため、ディレクトリは 00755 で指定する
RUN set -eu; \
    mkdir -p /dist/resources /dist/docker /dist/scripts; \
    cp -r app bootstrap config database lang public routes storage vendor /dist/; \
    cp -r resources/views resources/markdown /dist/resources/; \
    cp artisan composer.json composer.lock .env.example LICENSE README.md /dist/; \
    cp docker/runtime/Dockerfile docker/runtime/Dockerfile.dockerignore \
        docker/runtime/apache.conf docker/runtime/php.ini docker/runtime/entrypoint.sh /dist/docker/; \
    cp docker/runtime/compose.yaml /dist/compose.yaml; \
    cp docker/runtime/docker.sh /dist/scripts/docker.sh; \
    find /dist -type d -exec chmod 00755 {} +; \
    find /dist -type f -exec chmod 644 {} +; \
    chmod 755 /dist/artisan /dist/scripts/docker.sh

# ---------------------------------------------------------------------------
# app: zip の中身（/dist）だけを取り出す
# ---------------------------------------------------------------------------
FROM scratch AS app

COPY --from=build /dist /app

# ---------------------------------------------------------------------------
# package: 配布物を zip にまとめる
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS package

RUN apt-get update \
    && apt-get install -y --no-install-recommends zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=app /app /src

# zip の名前と最上位フォルダは composer.json の name（スラッシュより後ろ）、
# バージョンは config/app.php の version から取る。権限は build 段でそろえ済み
RUN set -eu; \
    cd /src; \
    name="$(php -r 'echo explode("/", json_decode(file_get_contents("composer.json"), true)["name"])[1];')"; \
    version="$(php -r 'require "vendor/autoload.php"; echo (require "config/app.php")["version"];')"; \
    test -n "$name"; \
    test -n "$version"; \
    mkdir -p /stage /out; \
    mv /src "/stage/$name"; \
    cd /stage; \
    zip -r -q -X "/out/$name-$version.zip" "$name"; \
    echo "created: $name-$version.zip"

# ---------------------------------------------------------------------------
# dist: zip だけを --output で取り出す
# ---------------------------------------------------------------------------
FROM scratch AS dist

COPY --from=package /out/ /
