# Imagem de produção do Estoque Fácil PRO — PHP-FPM + Nginx no mesmo
# container (via supervisord), pensada para plataformas de deploy por
# Docker sem suporte nativo a runtime PHP (Render) e também funciona no
# Railway (que aceita Dockerfile em vez do Nixpacks automático).
#
# Build multi-stage: dependências PHP e assets do frontend são
# resolvidos em estágios separados, e só o resultado (vendor/ pronto,
# public/build/ compilado) entra na imagem final — a imagem não carrega
# Composer, Node nem código-fonte de teste.

# ---------- Estágio 1: dependências PHP (Composer) ----------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------- Estágio 2: assets do frontend (Vite) ----------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ---------- Estágio 3: imagem final ----------
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        supervisor \
        gettext \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip gd intl opcache \
    && apk del --no-cache icu-dev libzip-dev oniguruma-dev libpng-dev freetype-dev libjpeg-turbo-dev

WORKDIR /var/www/html

# Código + vendor/ já resolvido (estágio 1)
COPY --from=vendor /app /var/www/html
# Assets compilados (estágio 2) — só o resultado do build, não o node_modules
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php-prod.ini /usr/local/etc/php/conf.d/99-estoque-facil.ini

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Render/Railway injetam a porta real via $PORT em runtime; 8080 é só o
# valor padrão local/documentacional.
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
