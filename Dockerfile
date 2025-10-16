FROM php:8.2-apache

# Устанавливаем зависимости для PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Копируем проект
COPY . /var/www/html/

# Устанавливаем рабочую директорию
WORKDIR /var/www/html/

# Открываем порт
EXPOSE 80
