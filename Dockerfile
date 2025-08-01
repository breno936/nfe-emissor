# Imagem base com PHP e Apache
FROM php:8.2-apache

# Instalar extensões necessárias para o NFePHP
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    unzip \
    git \
    && docker-php-ext-install soap bcmath

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Definir diretório de trabalho
WORKDIR /var/www/html

# Copiar os arquivos do projeto
COPY . .

# Instalar dependências do Composer
RUN composer install --no-dev --optimize-autoloader

# Dar permissão para a pasta certs (para o PHP conseguir ler o .pfx)
RUN chmod -R 755 certs

# Apache expõe a porta 80 por padrão
EXPOSE 80

# Iniciar Apache
CMD ["apache2-foreground"]
