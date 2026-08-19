#!/bin/sh
# Entrypoint do container de produção: valida configuração mínima, roda
# migration e cache, e só depois sobe nginx+php-fpm via supervisord.
#
# Observação sobre migrate --force aqui: isso roda a cada boot do
# container. Para uma única instância (o cenário desta v1, sem
# autoscaling) é seguro e é a opção mais simples. Se um dia o app rodar
# com múltiplas instâncias escalando ao mesmo tempo, migration em todo
# boot pode colidir — nesse caso mova para um "release step"/job
# separado da plataforma em vez do entrypoint do serviço web.
set -eu

: "${PORT:=8080}"
export PORT

envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

if [ -z "${APP_KEY:-}" ]; then
    echo "ERRO: variável de ambiente APP_KEY não definida." >&2
    echo "Gere uma localmente com 'php artisan key:generate --show' e configure-a na plataforma antes de subir o container." >&2
    exit 1
fi

echo "==> Descobrindo pacotes (pulado no build, roda aqui onde as extensões PHP corretas existem)..."
php artisan package:discover --ansi

echo "==> Rodando migrations..."
php artisan migrate --force

echo "==> Cacheando config/rotas/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Subindo nginx + php-fpm (porta ${PORT})..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
