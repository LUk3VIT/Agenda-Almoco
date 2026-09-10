FROM php:8.2-apache

# Instala dependências do sistema e extensões necessárias para o PostgreSQL e Apache
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilita o módulo mod_rewrite do Apache
RUN a2enmod rewrite

# Configura o diretório de trabalho
WORKDIR /var/www/html

# Copia os arquivos da aplicação
COPY . /var/www/html/

# Ajusta permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
