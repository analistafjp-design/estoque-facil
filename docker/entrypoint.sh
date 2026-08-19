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

# Espera o banco aceitar conexão antes de migrar. Sem isso, se o MySQL
# ainda estiver subindo (ou DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME
# estiverem errados), "migrate --force" falha, o "set -eu" aborta o
# script imediatamente, e o nginx NUNCA chega a subir — o container
# fica sem responder em porta nenhuma (é isso que aparece como
# "Application failed to respond" no Railway/Render: não é o app
# devolvendo erro, é ninguém escutando na porta).
echo "==> Aguardando o banco de dados aceitar conexão (DB_HOST=${DB_HOST:-<vazio>}, DB_PORT=${DB_PORT:-<vazio>}, DB_DATABASE=${DB_DATABASE:-<vazio>})..."
DB_WAIT_ATTEMPTS=0
DB_WAIT_MAX=15
until php -r '
$h = getenv("DB_HOST"); $p = getenv("DB_PORT") ?: "3306";
$d = getenv("DB_DATABASE"); $u = getenv("DB_USERNAME"); $pw = getenv("DB_PASSWORD");
try {
    new PDO("mysql:host={$h};port={$p};dbname={$d}", $u, $pw);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
'; do
    DB_WAIT_ATTEMPTS=$((DB_WAIT_ATTEMPTS + 1))
    if [ "$DB_WAIT_ATTEMPTS" -ge "$DB_WAIT_MAX" ]; then
        echo "ERRO: não foi possível conectar ao banco depois de ${DB_WAIT_MAX} tentativas. Confira DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD — se algum estiver com valor literal (ex. 127.0.0.1) em vez de referência ao serviço MySQL, é essa a causa." >&2
        exit 1
    fi
    echo "   ainda não respondeu (tentativa ${DB_WAIT_ATTEMPTS}/${DB_WAIT_MAX}), aguardando 2s..."
    sleep 2
done
echo "==> Banco de dados disponível."

echo "==> Rodando migrations..."
php artisan migrate --force

echo "==> Cacheando config/rotas/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Subindo nginx + php-fpm (porta ${PORT})..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
