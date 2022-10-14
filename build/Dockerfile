# This file is part of the sshilko/php-sql-mydb package.
#
# (c) Sergei Shilko <contact@sshilko.com>
#
# MIT License
#
# For the full copyright and license information, please view the LICENSE
# file that was distributed with this source code.
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