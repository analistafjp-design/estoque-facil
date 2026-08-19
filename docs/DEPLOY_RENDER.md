# Deploy no Render

> Este guia assume que você tem uma conta no [Render](https://render.com/) e
> que o repositório `estoque-facil` já está no GitHub. A imagem Docker
> usada aqui (`Dockerfile` na raiz do repo) já vem pronta no projeto — não
> precisa escrever nada, só seguir os passos.

## 0. Aviso importante: Render não tem MySQL gerenciado

O Render oferece banco gerenciado só para **PostgreSQL** (e um serviço
chave-valor compatível com Redis). Não existe "Render MySQL". Como este
projeto usa MySQL (requisito da spec), você precisa de um MySQL gerenciado
de outro provedor e apontar o app pra ele — o app não roda no mesmo host
do banco, conecta por rede, então isso não é um problema real, só um passo
a mais.

Qualquer MySQL 8 acessível pela internet serve (o app só precisa de
host/porta/usuário/senha/nome do banco). Exemplos com camada gratuita ou
bem barata: [Aiven for MySQL](https://aiven.io/mysql),
[Railway MySQL](https://railway.app/) (dá pra usar só o banco de lá e o
app aqui no Render), ou um MySQL gerenciado de qualquer cloud
(DigitalOcean, AWS RDS, etc.). Os passos abaixo usam o Aiven como
exemplo — a mecânica é a mesma em qualquer provedor.

### 0.1. Criando o MySQL (exemplo com Aiven)

1. Crie uma conta em https://aiven.io/, crie um novo serviço **MySQL**
   (plano gratuito/hobby serve para começar).
2. Espere o serviço ficar "Running" e abra a aba **Overview**/**Connection
   information**. Anote: `Host`, `Port`, `User`, `Password`, e o nome do
   banco (geralmente `defaultdb`).
3. **Importante:** a maioria desses provedores exige SSL na conexão. O
   driver `pdo_mysql` do PHP já suporta isso; se o provedor pedir, baixe o
   certificado CA dele — trataremos isso na seção de variáveis de
   ambiente abaixo (`MYSQL_ATTR_SSL_CA`, se necessário; muitos provedores
   aceitam conexão SSL sem exigir o certificado explícito no lado do
   cliente — confira a documentação do provedor escolhido).

Guarde essas cinco informações — você vai usá-las nas variáveis de
ambiente do serviço web no Render.

## 1. Criar o Web Service no Render

1. No painel do Render, **New +** → **Web Service**.
2. Conecte a conta do GitHub (se ainda não conectou) e selecione o
   repositório `analistafjp-design/estoque-facil`.
3. Em **Language/Runtime**, escolha **Docker** — o Render vai detectar o
   `Dockerfile` na raiz do repo sozinho (não precisa apontar caminho
   customizado, ele já está em `/Dockerfile`).
4. **Region**: escolha a mais próxima dos seus usuários.
5. **Instance Type**: o plano gratuito serve para testar; para uso real
   contínuo, o menor plano pago já resolve (o app é leve — Laravel +
   Nginx + PHP-FPM num único container).
6. **Não clique em "Create Web Service" ainda** — role até "Environment
   Variables" e configure a seção 2 abaixo primeiro, ou adicione depois
   em Settings → Environment. Qualquer ordem funciona, o Render só faz o
   primeiro deploy de fato quando você confirmar a criação.

## 2. Variáveis de ambiente

Vá em **Environment** (na criação do serviço ou depois em
Settings → Environment) e adicione, uma por uma:

| Variável | Valor |
|---|---|
| `APP_NAME` | `Estoque Fácil PRO` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — **crítico**, nunca `true` em produção |
| `APP_KEY` | gere localmente (veja abaixo) e cole aqui |
| `APP_URL` | a URL que o Render vai te dar, ex. `https://estoque-facil.onrender.com` (dá pra criar o serviço primeiro, pegar a URL gerada, e voltar aqui pra preencher) |
| `APP_LOCALE` | `pt_BR` |
| `LOG_CHANNEL` | `stderr` — assim os logs aparecem na aba **Logs** do Render em vez de sumirem num arquivo dentro do container efêmero |
| `LOG_LEVEL` | `error` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | host do MySQL (seção 0.1) |
| `DB_PORT` | porta do MySQL (geralmente `3306` ou uma porta customizada do provedor) |
| `DB_DATABASE` | nome do banco |
| `DB_USERNAME` | usuário do MySQL |
| `DB_PASSWORD` | senha do MySQL |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `MAIL_MAILER` | `smtp` (sem isso, o convite de usuário não sai de verdade) |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` | credenciais do seu provedor de e-mail transacional |
| `MAIL_FROM_ADDRESS` | e-mail de remetente verificado no provedor |
| `MAIL_FROM_NAME` | `Estoque Fácil PRO` |

Gerar o `APP_KEY` localmente antes de colar no Render:

```bash
php artisan key:generate --show
```

Isso imprime algo como `base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX=`
— copie o valor completo (com o prefixo `base64:`) para a variável
`APP_KEY`. Gere uma chave nova só para produção; nunca reaproveite a do
seu `.env` local.

Não é preciso configurar `PORT` — o Render injeta essa variável sozinho, e
o `entrypoint.sh` da imagem já lê `$PORT` para configurar o Nginx
dinamicamente.

## 3. Primeiro deploy

1. Clique em **Create Web Service** (ou **Manual Deploy** → **Deploy
   latest commit**, se o serviço já existia).
2. Acompanhe a aba **Logs** — o build roda os três estágios do Dockerfile
   (composer install, npm build, imagem final), o que leva alguns
   minutos no primeiro deploy.
3. No boot do container, o `entrypoint.sh` roda `php artisan migrate
   --force` automaticamente — acompanhe o log para confirmar que as
   migrations rodaram sem erro (se o MySQL externo não estiver acessível
   ou as credenciais estiverem erradas, é aqui que vai falhar, com
   mensagem clara).
4. Quando o log mostrar o Nginx/PHP-FPM subindo e o Render marcar o
   serviço como **Live**, acesse a URL gerada (ex.
   `https://estoque-facil.onrender.com`) e confirme que a tela de
   login/cadastro carrega.
5. Volte em Environment e confirme `APP_URL` com o valor real dessa URL,
   se ainda não tinha feito — salvar a variável dispara um redeploy
   automático.

## 4. Testando o fluxo completo

1. Acesse `/register`, crie a primeira empresa + usuário admin.
2. Faça login, cadastre um produto, lance uma entrada de estoque.
3. Vá em **Convites** e convide um segundo usuário — se `MAIL_MAILER`
   estiver configurado corretamente, o e-mail deve chegar de verdade
   (senão, ele fica só no destino configurado, sem erro visível pro
   usuário final — confira a aba Logs se o convite parecer não ter
   saído).

## 5. Domínio próprio (opcional)

Em Settings → **Custom Domain**, adicione seu domínio e siga as
instruções de DNS que o Render mostra (um registro `CNAME` apontando pro
domínio `.onrender.com`, geralmente). O certificado HTTPS é emitido e
renovado automaticamente pelo Render. Depois de configurar, atualize
`APP_URL` para o domínio novo.

## 6. Deploys automáticos

Por padrão, todo push na branch conectada (`main`) dispara um novo
deploy automaticamente. Isso inclui rodar `migrate --force` de novo no
boot — migrations que já rodaram não são reaplicadas (o Laravel controla
isso pela tabela `migrations`), então é seguro.

## Troubleshooting

- **Deploy falha em "Rodando migrations..."** — geralmente é o MySQL
  externo recusando a conexão. Confira: (a) se o provedor exige
  liberar IP/allowlist e o Render não está nela (a maioria dos serviços
  gerenciados modernos aceita conexão de qualquer IP com usuário/senha
  corretos, mas alguns exigem allowlist — confira a documentação do seu
  provedor específico); (b) se `DB_HOST`/`DB_PORT`/`DB_PASSWORD` batem
  exatamente com o painel do provedor.
- **Página carrega sem CSS/JS** — o build do Vite (estágio 2 do
  Dockerfile) falhou silenciosamente ou os assets não foram copiados;
  confira o log do build por erros do `npm run build`.
- **Convite não chega por e-mail** — confira `MAIL_*`; com
  `MAIL_MAILER=log` (valor de desenvolvimento) o e-mail nunca sai de
  verdade, só grava no log — não use isso em produção.
