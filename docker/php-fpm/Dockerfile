#######################################
# composer
#######################################
FROM composer:2.3 as composer

#######################################
# php-fpm
#######################################
FROM php:8.1-fpm-alpine3.16

# Environments

ENV DEBIAN_FRONTEND noninteractive
ENV TZ=Asia/Tokyo

# Install packages

# hadolint ignore=DL3018
RUN set -x && \
    apk add -U --no-cache \
        freetype-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libzip-dev \
        build-base \
        autoconf \
        openssh \
        git && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql gd zip bcmath && \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    sed -i -e "s/pm.max_children = 5/pm.max_children = 20/" /usr/local/etc/php-fpm.d/www.conf && \
    sed -i \
        -e "s/post_max_size = 8M/post_max_size = 20M/" \
        -e "s/upload_max_filesize = 2M/upload_max_filesize = 20M/" \
        -e "s/memory_limit = 128M/memory_limit = 2048M/" \
        "$PHP_INI_DIR/php.ini"
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Xdebug

# hadolint ignore=DL3018
# RUN set -x && \
#     if [ ${IS_DEV} = true ]; then \
#     pecl install xdebug && \
#     docker-php-ext-enable xdebug && \
#     printf "xdebug.mode=debug\n\
#         xdebug.start_with_request=yes\n\
#         xdebug.client_host=host.docker.internal\n\
#         xdebug.client_port=9003\n\
#         xdebug.log=/tmp/xdebug.log\n\
#         xdebug.log_level=0" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini" \
#     ;fi

# Set up Laravel

ENV APPDIR=/usr/local/laravel
ENV PATH=$PATH:/usr/local/laravel/vendor/bin
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . $APPDIR
WORKDIR $APPDIR
RUN set -x && \
    chmod 777 storage && \
    touch storage/logs/laravel.log && \
    chmod 666 storage/logs/laravel.log && \
    mkdir -p /var/log/laravel && \
    chmod 777 /var/log/laravel && \
    echo "* * * * * cd /usr/local/laravel && php artisan schedule:run >> /dev/null 2>&1" > /var/spool/cron/crontabs/root && \
    mkdir -m 777 "/var/log/supervisor"

# For development

ENV LESSCHARSET=utf-8
RUN echo 'set encoding=utf8' >> ~/.vimrc


COPY docker/php-fpm/init.sh /init.sh

EXPOSE 9000
CMD [ "/init.sh" ]
