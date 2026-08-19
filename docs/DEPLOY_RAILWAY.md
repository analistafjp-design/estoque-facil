# Deploy no Railway

> Este guia assume que você tem uma conta no [Railway](https://railway.app/)
> e que o repositório `estoque-facil` já está no GitHub. Diferente do
> Render, o Railway tem **MySQL gerenciado nativo** — dá pra ter banco e
> app no mesmo projeto, sem depender de um terceiro provedor.

## 1. Criar o projeto e o banco MySQL

1. No painel do Railway, **New Project** → **Provision MySQL** (ou
   **Deploy MySQL**, dependendo da versão da interface — procure por
   "Database" → "MySQL" na lista de templates prontos).
2. O Railway sobe um MySQL 8 gerenciado dentro do projeto e gera
   variáveis de conexão automaticamente nesse serviço (algo como
   `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`,
   `MYSQLDATABASE` — os nomes exatos podem variar um pouco conforme a
   versão da plataforma; confira a aba **Variables** do serviço MySQL
   recém-criado para ver os nomes reais gerados no seu projeto).

Não precisa copiar esses valores à mão — na seção 3 vamos referenciá-los
diretamente de dentro do serviço do app, sem hardcoded.

## 2. Criar o serviço do app

1. No mesmo projeto, **New** → **GitHub Repo** → selecione
   `analistafjp-design/estoque-facil`.
2. O Railway detecta automaticamente o `Dockerfile` na raiz do
   repositório e usa ele para o build (não precisa configurar Nixpacks
   nem builder manualmente — a presença de um `Dockerfile` na raiz já é
   suficiente para o Railway priorizar esse caminho de build).
3. Ainda não clique em deploy — configure as variáveis de ambiente
   (seção 3) antes, para o primeiro deploy já subir correto.

## 3. Variáveis de ambiente do serviço do app

Na aba **Variables** do serviço do app (não do MySQL), adicione:

| Variável | Valor |
|---|---|
| `APP_NAME` | `Estoque Fácil PRO` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | gere localmente (veja abaixo) e cole aqui |
| `APP_LOCALE` | `pt_BR` |
| `LOG_CHANNEL` | `stderr` — para os logs aparecerem na aba **Deploy Logs**/**Observability** do Railway |
| `LOG_LEVEL` | `error` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | referencie a variável do serviço MySQL — no Railway isso se faz digitando `${{` no campo de valor e escolhendo a variável do serviço MySQL na lista que aparece (ex. `${{MySQL.MYSQLHOST}}`); assim o valor fica sempre sincronizado se o Railway trocar host/porta internamente |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` (mesma lógica de referência) |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` | credenciais do provedor de e-mail transacional |
| `MAIL_FROM_ADDRESS` | e-mail de remetente verificado |
| `MAIL_FROM_NAME` | `Estoque Fácil PRO` |

Gerar o `APP_KEY` (mesmo comando de qualquer plataforma):

```bash
php artisan key:generate --show
```

Copie o valor completo (`base64:...`) para a variável `APP_KEY`. Não é
preciso configurar `APP_URL` nem `PORT` manualmente:
- `PORT` é injetada automaticamente pelo Railway, e o `entrypoint.sh` já
  lê essa variável para configurar o Nginx.
- Depois de gerar o domínio público (próxima seção), volte e defina
  `APP_URL` com ele — o Laravel usa isso para montar links absolutos
  (ex. no e-mail de convite).

## 4. Gerar o domínio público

Por padrão, um serviço novo no Railway não tem URL pública. Na aba
**Settings** do serviço do app, em **Networking** → **Public Networking**,
clique em **Generate Domain**. O Railway cria uma URL do tipo
`estoque-facil-production.up.railway.app` e já expõe a porta que o
container está escutando (a mesma `$PORT` injetada).

Copie essa URL e volte em **Variables** para preencher `APP_URL` com ela
(com `https://` na frente, sem barra no final).

## 5. Deploy

1. Clique em **Deploy** (ou, se já configurou tudo antes do primeiro
   deploy automático disparar, ele já deve estar rodando).
2. Acompanhe **Deploy Logs**: build (composer + npm + imagem final) e
   depois o boot do container, onde o `entrypoint.sh` roda `php artisan
   migrate --force` — confirme que passou sem erro antes de considerar o
   deploy bem-sucedido.
3. Acesse a URL gerada e confirme que a tela de login/cadastro carrega.

## 6. Testando o fluxo completo

Mesmos passos de qualquer ambiente novo:

1. `/register` — cria a primeira empresa + usuário admin.
2. Login → cadastrar produto → lançar entrada de estoque.
3. **Convites** → convidar um segundo usuário e confirmar que o e-mail
   sai (com `MAIL_MAILER=smtp` configurado corretamente).

## 7. Deploys automáticos

Por padrão o Railway redeploya a cada push na branch conectada (`main`).
Migrations já aplicadas não são reaplicadas — seguro rodar
`migrate --force` a cada boot, como o `entrypoint.sh` já faz.

## Troubleshooting

- **App não conecta no MySQL** — confira se as variáveis `DB_*` do
  serviço do app estão realmente referenciando o serviço MySQL
  (`${{MySQL.VARIAVEL}}`) e não um valor colado à mão desatualizado; abra
  a aba Variables do serviço MySQL para conferir os nomes reais das
  variáveis geradas no seu projeto.
- **502/build falha sem log claro** — confira **Deploy Logs** desde o
  início do build; erro de `composer install` ou `npm run build`
  aparece ali antes mesmo do container subir.
- **Convite não chega por e-mail** — mesma causa comum de qualquer
  ambiente: `MAIL_MAILER` ainda em `log` (valor de desenvolvimento) em
  vez de `smtp`.
