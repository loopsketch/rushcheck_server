FROM nginx:1.19.2-alpine

# Set WORKDIR
ENV APPDIR /usr/local/laravel/public
WORKDIR $APPDIR
COPY public $APPDIR

COPY ./docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/nginx/conf.d /etc/nginx/conf.d
COPY ./docker/nginx/htpasswd /etc/nginx/htpasswd
RUN rm /etc/nginx/conf.d/default.conf

EXPOSE 80