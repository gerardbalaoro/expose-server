# syntax=docker/dockerfile:1.6
FROM php:8.2-cli

RUN apt-get update
RUN apt-get install -y git libzip-dev zip

RUN docker-php-ext-install zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /src
WORKDIR /src

# install the dependencies
RUN --mount=type=secret,id=auth \
    composer install -o --prefer-dist --no-interaction --no-progress

ENV PORT=8080
ENV DOMAIN=localhost

COPY ./docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
