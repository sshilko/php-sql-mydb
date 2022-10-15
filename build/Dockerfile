# This file is part of the sshilko/php-sql-mydb package.
#
# (c) Sergei Shilko <contact@sshilko.com>
#
# MIT License
#
# For the full copyright and license information, please view the LICENSE
# file that was distributed with this source code.
FROM library/composer:2.4

FROM library/php:7.4-cli

LABEL author='Sergei Shilko <contact@sshilko.com>'
LABEL package='sshilko/php-sql-mydb'

STOPSIGNAL SIGTERM
ENV DEBIAN_FRONTEND noninteractive

# https://wiki.ubuntu.com/ReducingDiskFootprint
RUN echo '\n\
path-exclude /usr/share/doc/*                       \n\
path-include /usr/share/doc/*/copyright             \n\
path-exclude /usr/share/man/*                       \n\
path-exclude /usr/share/groff/*                     \n\
path-exclude /usr/share/info/*                      \n\
path-exclude /usr/share/locale/*                    \n\
path-exclude /usr/share/lintian/*                   \n\
path-exclude /usr/share/linda/*                     \n\
' >> /etc/dpkg/dpkg.cfg.d/01_excludes                                         && \
    cat  /etc/dpkg/dpkg.cfg.d/01_excludes                                     && \
    echo 'APT::Install-Recommends "0";' > /etc/apt/apt.conf.d/no-recommends   && \
    find /usr/share/locale -maxdepth 1 -mindepth 1 ! -name 'en' |xargs rm -rf && \
    apt-get update                                           && \
    docker-php-ext-install mysqli                            && \
    apt-get install -y --no-install-recommends                  \
    unzip                                                       \
    python3-pip                                                 \
    pre-commit                                                  \
    locales                                                     \
    git                                                      && \
    apt-get clean                                            && \
    apt-get -y autoremove                                    && \
    rm -rf /var/lib/{apt,cache,log}/                         && \
    rm -rf /tmp/* /var/tmp/*                                 && \
    echo "* hard nofile 524280" >> /etc/security/limits.conf && \
    echo "* soft nofile 262140" >> /etc/security/limits.conf && \
    echo 'en_US.UTF-8' > /etc/locale.gen                     && \
    locale-gen                                               && \
    echo "TZ=Etc/UTC" >> /etc/environment                    && \
    echo 'Done'

ENV LANG "en_US.UTF-8"
ENV LC_ALL "en_US.UTF-8"
ENV TZ "Etc/UTC"

HEALTHCHECK --interval=10s --timeout=1s --retries=2 --start-period=5s CMD /usr/local/bin/php -v || exit 1
WORKDIR /app
COPY --from=0 /usr/bin/composer /usr/bin/composer

CMD ["sleep", "infinity"]
