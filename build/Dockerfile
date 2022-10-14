FROM composer:2.4

FROM php:7.4-cli

RUN apt-get update && \
    docker-php-ext-install mysqli && \
    apt-get install -y unzip python3-pip pre-commit git && \
    apt-get clean && \
    rm -rf /var/lib/{apt,cache,log}/

WORKDIR /app
COPY --from=0 /usr/bin/composer /usr/bin/composer
CMD ["sleep", "infinity"]