FROM php:8.3-alpine

RUN apk update && apk add --no-cache \
    bash \
    git \
    zip \
    unzip

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli