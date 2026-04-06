FROM php:8.2-apache

# Define o diretório de trabalho
WORKDIR /var/www/html

# Copia apenas o necessário do seu projeto
COPY . .

# Habilita o mod_rewrite do Apache para rotas amigáveis
RUN a2enmod rewrite

# Dá permissão para o Apache ler seus arquivos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80