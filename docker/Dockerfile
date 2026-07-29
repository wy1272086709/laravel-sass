FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

# Pin swoole 版本以保证镜像可复现（6.2.2 支持 PHP 8.4；升级前到 pecl.php.net/package/swoole 确认最新稳定版）
ARG SWOOLE_VERSION=6.2.2

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        ca-certificates \
        curl \
        default-mysql-client \
        git \
        libicu-dev \
        libssl-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_mysql pdo_sqlite zip \
    && export MAKEFLAGS="-j$(nproc)" \
    && yes no | pecl install swoole-${SWOOLE_VERSION} \
    && docker-php-ext-enable swoole \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libssl-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -ms /bin/bash laravel

# 1. 提前创建composer缓存目录并赋予laravel权限
RUN mkdir -p /home/laravel/.composer/cache \
    && mkdir -p /var/www/html/vendor \
    && chown -R laravel:laravel /home/laravel \
    && chown -R laravel:laravel /var/www/html

EXPOSE 8000

USER laravel

COPY composer.json composer.lock ./

RUN attempts=0; \
    until composer install --no-dev --no-scripts --no-progress --optimize-autoloader; do \
        attempts=$((attempts + 1)); \
        if [ "$attempts" -ge 3 ]; then exit 1; fi; \
        sleep 3; \
    done

USER root

RUN mkdir -p /opt/vendor \
    && cp -a /var/www/html/vendor/. /opt/vendor/

COPY docker/entrypoint.sh /usr/local/bin/laravel-entrypoint
COPY docker/start-app.sh /usr/local/bin/laravel-start-app

RUN chmod +x /usr/local/bin/laravel-entrypoint /usr/local/bin/laravel-start-app

ENTRYPOINT ["laravel-entrypoint"]

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
