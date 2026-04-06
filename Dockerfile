FROM php:8.2-apache

# 1. Configura a raiz do Apache para /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 2. Instala o Composer diretamente na imagem final (mais leve que multi-stage em VPS pequena)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

# 3. Copia os arquivos
COPY . .

# 4. Instala dependências (se o erro persistir aqui, precisaremos aumentar o SWAP da VPS)
RUN composer install --no-dev --optimize-autoloader

# 5. Permissões e Rewrite
RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80