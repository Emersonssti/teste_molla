# Estágio 1: Instalação das dependências (Composer)
FROM composer:2 AS build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Estágio 2: Configuração do Servidor Apache
FROM php:8.2-apache

# Ajusta a raiz do Apache para a pasta /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copia os arquivos do projeto
COPY . .

# Copia a pasta vendor gerada no Estágio 1
COPY --from=build /app/vendor ./vendor

# Permissões e configurações finais
RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80