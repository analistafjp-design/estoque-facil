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
# --ignore-platform-reqs: a imagem composer:2 não tem gd/intl/pdo_mysql
# instaladas (ela só resolve e baixa pacotes, não roda o app) — sem essa
# flag o Composer barra a instalação porque phpoffice/phpspreadsheet
# exige ext-gd e o ambiente ATUAL não tem. A imagem final (estágio 3) já
# instala gd de verdade via docker-php-ext-install, então ignorar aqui é
# seguro: o requisito é satisfeito onde o código realmente roda.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --ignore-platform-reqs

COPY . .
# --no-scripts aqui também: o hook post-autoload-dump do Laravel roda
# "artisan package:discover", que depende de extensões PHP (pdo_mysql,
# gd, intl...) que só existem na imagem final (estágio 3), não nesta
# imagem composer:2. O discovery acontece sozinho, sem custo extra, no
# primeiro boot real do container (dentro do entrypoint.sh).
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

# ---------- Estágio 2: assets do frontend (Vite) ----------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ---------- Estágio 3: imagem final ----------
FROM php:8.4-fpm-alpine AS runtime

# Libs de runtime (ficam na imagem final) separadas dos headers de
# desenvolvimento + toolchain de compilação (só existem durante o build,
# removidos no fim do RUN — "virtual .build-deps" é o pacote apk
# temporário que agrupa tudo isso pra dar apk del de uma vez).
#
# $PHPIZE_DEPS é uma env var já definida pela imagem base php:*-alpine
# com a lista de pacotes (gcc, g++, make, autoconf etc.) necessários pro
# phpize/make que o docker-php-ext-install roda por baixo dos panos —
# sem isso o compilador nem existe na imagem e o RUN falha na hora de
# compilar a primeira extensão.
RUN apk add --no-cache \
        nginx \
        supervisor \
        gettext \
        icu-libs \
        libzip \
        oniguruma \
        libpng \
        freetype \
        libjpeg-turbo \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip gd intl opcache \
    && apk del --no-cache .build-deps

WORKDIR /var/www/html

# Código + vendor/ já resolvido (estágio 1)
COPY --from=vendor /app /var/www/html
# Assets compilados (estágio 2) — só o resultado do build, não o node_modules
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php-prod.ini /usr/local/etc/php/conf.d/99-estoque-facil.ini

# Sem chaves {a,b,c} de propósito: é expansão de Bash, e o shell padrão
# do RUN nessa imagem Alpine é o ash (busybox), que não expande isso —
# criaria uma única pasta chamada literalmente "{cache,sessions,views}"
# em vez de três pastas separadas.
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Render/Railway injetam a porta real via $PORT em runtime; 8080 é só o
# valor padrão local/documentacional.
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
