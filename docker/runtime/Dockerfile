# syntax=docker/dockerfile:1
#
# Metiwikiの実行用イメージ（Apache＋PHP）。配布用zipにdocker/Dockerfileとして入る。
# ビルドコンテキストはzipを展開したフォルダ（ビルド済みのvendor・public/buildを含む）で、
# 通常はscripts/docker.shからcompose.yaml経由で使う。
# データはすべてボリューム/dataに置く。
#
# リポジトリではdocker/runtime/に置き、scripts/build-dist.sh（docker/Dockerfile）がzipに配置する。

ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-apache

# pdo_sqlite・mbstring・fileinfo・OPcacheは公式イメージに含まれる。MySQL用にpdo_mysqlを足す
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-metiwiki.ini
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/metiwiki-entrypoint

# 除外するもの（Docker用のファイル・.env・データ）はDockerfile.dockerignoreを参照
COPY . /var/www/metiwiki

# 展開した環境の権限を持ち込まない（書き込み可のままだとWebサーバーがコードを書き換えられる）。
# ディレクトリ755・ファイル644・artisan 755にそろえ、書き込めるのはbootstrap/cacheだけにする
# （起動時のartisan optimizeをwww-dataで実行するため）。storageは/dataの下に置く。
# docker/は上で個別にCOPY済みの設定ファイル（ビルドコンテキストに含めるため除外していない）
RUN cd /var/www/metiwiki \
    && rm -rf storage docker \
    && chown -R root:root . \
    && find . -type d -exec chmod 00755 {} + \
    && find . -type f -exec chmod 644 {} + \
    && chmod 755 artisan \
    && chown -R www-data:www-data bootstrap/cache

# 既定値。上書きはcomposeのenv_file（metiwiki.env）で行う
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=ja \
    LOG_STACK=single,stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    LARAVEL_STORAGE_PATH=/data/storage

WORKDIR /var/www/metiwiki

VOLUME /data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/-/up || exit 1

ENTRYPOINT ["metiwiki-entrypoint"]
CMD ["apache2-foreground"]
