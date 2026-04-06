FROM php:8.2-apache

# Muda a raiz do Apache para a pasta public do seu projeto
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .

RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80