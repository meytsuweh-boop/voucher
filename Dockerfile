FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor \
    freetype-dev libjpeg-turbo-dev libpng-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install mysqli pdo pdo_mysql gd

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/qrcodes \
  && chown -R www-data:www-data /var/www/html/qrcodes \
  && chmod 775 /var/www/html/qrcodes

RUN rm /etc/nginx/http.d/default.conf
COPY render/nginx.conf /etc/nginx/http.d/default.conf
COPY render/supervisord.conf /etc/supervisord.conf

EXPOSE 10000
CMD ["/usr/bin/supervisord","-c","/etc/supervisord.conf"]
