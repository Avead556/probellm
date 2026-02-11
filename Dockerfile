FROM php:8.4-cli-alpine

RUN apk add --no-cache git unzip curl-dev && docker-php-ext-install curl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./

RUN composer install --prefer-dist --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize

ENTRYPOINT ["./vendor/bin/phpunit"]
