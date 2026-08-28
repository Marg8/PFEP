FROM php:8.3-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql && a2enmod rewrite

# Configure Apache to listen on 8080 (Cloud Run default PORT)
RUN sed -i 's/Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . .

# Ensure uploads dir exists and is writable
RUN mkdir -p uploads && chown -R www-data:www-data uploads && chmod 775 uploads

# Remove default Apache index
RUN rm -f /var/www/html/index.html 2>/dev/null || true

EXPOSE 8080

# Only PORT is baked in. DB_* must come from the platform (Render env vars, Cloud Run, etc.)
ENV PORT=8080
