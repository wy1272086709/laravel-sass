FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

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
    && yes no | pecl install swoole \
    && docker-php-ext-enable swoole \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libssl-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -ms /bin/bash laravel

USER laravel

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
