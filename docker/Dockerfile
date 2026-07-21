FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install bcmath intl pcntl pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -ms /bin/bash laravel

USER laravel

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
