# Estoque Fácil PRO

Sistema web de controle de estoque para pequenas e médias empresas, com
suporte a múltiplas empresas (multi-tenant) e múltiplos usuários por
empresa.

> **Projeto novo e independente.** Criado do zero (`laravel new`), sem
> herdar código, banco, credencial ou configuração de nenhum outro projeto
> já existente nesta conta.

O problema que resolve: pequena empresa que controla estoque em planilha
solta ou de cabeça perde venda por falta de produto, ou trava capital em
excesso de estoque parado. Este app dá visibilidade — quanto tem de cada
produto, o que está no ou abaixo do mínimo, quem mexeu no estoque e quando.

Para como o isolamento entre empresas funciona por baixo dos panos, onde
ficam as credenciais e como o fluxo de autenticação/convite está montado,
ver [ARCHITECTURE.md](ARCHITECTURE.md).

## Stack

- **Backend:** Laravel 13, PHP 8.4+
- **Banco de dados:** MySQL 8
- **Frontend:** Blade + Livewire (+ [Volt][volt] nas telas de auth do
  Breeze) + Tailwind CSS — sem SPA separada
- **Autenticação:** Laravel Breeze
- **Planilha:** [Maatwebsite/Laravel-Excel][excel] (import/export)
- **Testes:** [Pest][pest]
- **PWA:** manifest + service worker próprios (`public/manifest.webmanifest`,
  `public/sw.js`) — instalável em desktop, Android e iOS

[volt]: https://livewire.laravel.com/docs/volt
[excel]: https://docs.laravel-excel.com/
[pest]: https://pestphp.com/

## Funcionalidades (v1)

- Cadastro de empresa (conta principal) na criação da conta
- Convite de novos usuários por e-mail, com papel (`admin`/`operador`)
- Login com controle de sessão e permissão por papel
- CRUD de produto — criar, editar, listar, **desativar** (soft delete,
  nunca apaga de verdade: o histórico de movimentação continua íntegro)
- Lançamento de entrada/saída de estoque, com atualização automática da
  quantidade na mesma transação
- Alerta visual quando produto atinge o estoque mínimo
- Importação de produtos via planilha Excel **de formato livre**, com
  conferência antes de gravar:
  - as colunas são reconhecidas pelo cabeçalho, por sinônimos e em
    qualquer ordem ("Produto"/"Descrição"/"Item" → nome;
    "Código"/"Ref."/"SKU" → código; "Estoque"/"Saldo"/"Qtd" →
    quantidade), ignorando acento, caixa e pontuação;
  - o **conteúdo** das colunas desempata cabeçalho ambíguo: em
    exportação de ERP, "Material" tanto pode ser o código quanto a
    descrição — se a coluna só tem número, ela vira SKU e a coluna de
    texto vira o nome;
  - antes de importar, uma tela mostra o mapeamento detectado, deixa
    trocar qualquer coluna e exibe uma prévia com o dado real — o
    palpite automático nunca acerta 100% dos formatos, e ver
    "1000013" na coluna Nome denuncia o erro antes de sujar o cadastro;
  - aceita linhas de título antes do cabeçalho (relatório de ERP),
    colunas extras, rodapé e número em formato brasileiro;
  - **soma linhas repetidas do mesmo material**: relatório de ERP traz
    uma linha por depósito/filial, e como a v1 não tem filiais o saldo
    do produto é a soma — testado com um relatório real de 16.777
    linhas, que virou 3.537 produtos em ~26s;
  - só a **primeira aba** é lida — abas de apoio (instruções, resumo,
    glossário) não viram produto;
  - só o nome é obrigatório; sem coluna de SKU o produto é identificado
    pelo nome e recebe um código gerado. Reimportar atualiza em vez de
    duplicar
- **Modelo de planilha** para download (tela Produtos → "Modelo de
  planilha"): formato oficial com cabeçalho legível (`Nome do produto`,
  `Código (SKU)`, `Categoria`, `Quantidade atual`, `Estoque mínimo`),
  linha de cabeçalho congelada, exemplos preenchidos e uma aba de
  instruções explicando cada coluna. Usá-lo é opcional — o importador
  aceita outros formatos — mas é o caminho sem surpresa
- **Excluir todos os produtos** (só admin, com confirmação digitada) —
  para recomeçar um cadastro que entrou errado sem desativar item a item;
  apaga também o histórico de movimentações, por isso o aviso explícito
- Exportação do estoque atual para Excel
- Histórico de movimentações por produto (quem, quando, tipo, quantidade)
- Dashboard: total de produtos, produtos com estoque baixo, movimentações
  recentes

**Fora de escopo por decisão, não por esquecimento:** integração com
marketplace, app mobile nativo, múltiplas filiais, relatórios com
gráficos. Ver o prompt original do projeto para o racional completo.

## Instalação local

Pré-requisitos: PHP 8.4+, Composer, Node 20+, MySQL 8 (ou MariaDB
compatível) rodando localmente ou acessível pela rede.

```bash
git clone <url-do-repositorio> estoque-facil
cd estoque-facil

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Abra o `.env` e preencha `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` com
as credenciais do seu MySQL local (o `.env.example` documenta cada
variável). Crie o banco antes de migrar — o Laravel não cria o database
sozinho:

```sql
CREATE DATABASE estoque_facil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Migrations

```bash
php artisan migrate
```

Não tem seeder de dados de exemplo na v1 — o primeiro registro
(`/register`) já cria a primeira empresa e o primeiro usuário (admin).

### Rodando em desenvolvimento

```bash
composer dev
```

Isso sobe o servidor PHP (`php artisan serve`), o worker de fila, o
`pail` (log em tempo real) e o Vite em modo watch, tudo junto — ver o
script `dev` em `composer.json` se quiser rodar as peças separadas.
Acesse `http://localhost:8000`.

### Testes

```bash
php artisan test
```

A suíte roda contra SQLite em memória (configurado em `phpunit.xml`, sem
precisar de banco à parte) — decisão comum na comunidade Laravel para
suíte rápida; nenhuma migration usa recurso específico de MySQL, então o
comportamento é o mesmo. 111 testes, cobrindo:

- **isolamento multi-tenant** (`tests/Feature/Tenancy/`) — o requisito
  não-negociável da spec: Empresa A nunca lê, edita ou apaga dado da
  Empresa B, nem manipulando ID na URL;
- **papéis** admin/operador;
- **CRUD de produto**, incluindo SKU duplicado/único por empresa e soft
  delete;
- **movimentação de estoque** — entrada, saída, bloqueio de saída maior
  que o estoque, transação atômica;
- **convite** — envio, aceite, expiração, rate limiting;
- **import/export** de planilha, incluindo detecção de coluna por
  sinônimo, cabeçalho fora da primeira linha e formato numérico variado;
- **modelo de planilha** — ida e volta: baixar o modelo e reimportá-lo
  funciona sem ajuste, e a aba de instruções não vira produto;
- **exclusão em massa** — confirmação obrigatória, restrita a admin e
  incapaz de tocar em dado de outra empresa;
- **proxy/HTTPS** — garante que atrás do balanceador da hospedagem a URL
  dos assets sai em `https` (senão o navegador bloqueia o CSS).

## Deploy

Guias passo a passo prontos para duas plataformas específicas:
[Render](docs/DEPLOY_RENDER.md) e [Railway](docs/DEPLOY_RAILWAY.md) —
ambos usam o `Dockerfile` já incluído no repositório, não precisa
escrever configuração de deploy do zero. A seção abaixo é o passo a
passo genérico, host-agnóstico, para quem for usar outra plataforma.

Qualquer host que rode PHP 8.4+ com extensões padrão do Laravel
(`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`,
`fileinfo`, `gd`, `zip` — para o Maatwebsite/Excel) e um MySQL 8 acessível
serve: VPS com Nginx/Apache + PHP-FPM, ou uma plataforma gerenciada de
PHP. Passos, independente de onde:

1. `composer install --no-dev --optimize-autoloader`
2. `npm install && npm run build` (gera `public/build/`, os assets com
   hash que o Vite referencia — sem isso a página carrega sem CSS/JS)
3. Configurar as variáveis de ambiente de produção (não versionadas —
   ver `.env.example` para a lista completa e o que cada uma faz):
   - `APP_ENV=production`, `APP_DEBUG=false` (**crítico** — com `true`,
     um erro mostra stack trace pro usuário final; a spec exige log
     configurado sem vazar isso)
   - `APP_KEY` — gerar com `php artisan key:generate` **no próprio
     ambiente de produção**, nunca reaproveitar a de outro ambiente
   - `APP_URL` — a URL pública real, sem barra no fim
   - `DB_*` — credenciais do MySQL de produção
   - `MAIL_MAILER=smtp` + `MAIL_HOST`/`PORT`/`USERNAME`/`PASSWORD` — sem
     isso, o convite (item 5 da spec) não sai de verdade, só grava log
   - `LOG_LEVEL=error` (ou `warning`) — não `debug` em produção
4. `php artisan migrate --force` (o `--force` é necessário porque
   `APP_ENV=production` bloqueia migration interativa sem ele)
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. Apontar o document root do servidor web para `public/`, não para a
   raiz do projeto — é assim que o Laravel isola o resto do código de
   acesso HTTP direto

### Backup do banco

A spec pede backup automático documentado, mesmo que a implementação
fique a cargo da hospedagem — e é exatamente essa a recomendação aqui:
não foi escrito nenhum mecanismo de backup próprio no código. A maioria
dos provedores de MySQL gerenciado (RDS, Cloud SQL, ou o MySQL que vier
junto do plano de hospedagem) já oferece backup automático diário
configurável no próprio painel — ativar isso é mais confiável do que um
`mysqldump` rodando por `cron` dentro da própria aplicação, que falha
silenciosamente se o servidor cair no horário errado. Se o provedor
escolhido não tiver backup gerenciado, a alternativa é um `cron` de
`mysqldump` para armazenamento externo (S3 ou equivalente) — fora do
escopo desta v1 implementar, mas é o próximo passo natural.

## Estrutura

```
app/
├── Enums/               UserRole, MovementType, InvitationStatus
├── Exports/              Maatwebsite: exportação de estoque e planilha modelo
├── Imports/              importação de planilha de formato livre
│   └── Support/          detecção de coluna (cabeçalho + conteúdo), leitura
│                         de número e análise para a tela de conferência
├── Http/Middleware/       EnsureUserIsAdmin (rota /convites)
├── Livewire/              componentes de tela (Dashboard, Products, Invitations)
├── Mail/                   InvitationMail
└── Models/
    ├── Concerns/BelongsToCompany.php   trait de isolamento multi-tenant
    └── Scopes/CompanyScope.php          global scope que filtra por empresa

database/migrations/    esquema — company_id em toda tabela de negócio
                         desde a primeira migration

tests/Feature/
├── Tenancy/            isolamento multi-tenant e permissão por papel
├── Products/            CRUD, import/export
├── StockMovements/      entrada/saída
├── Invitations/         convite ponta a ponta
└── Auth/                 auth do Breeze (ajustado pro cadastro criar empresa)

Dockerfile               imagem de produção (Nginx + PHP-FPM via supervisord)
docker/                  config de suporte do Dockerfile (nginx, supervisord,
                         entrypoint.sh, php.ini de produção)
docs/DEPLOY_RENDER.md    passo a passo de deploy no Render
docs/DEPLOY_RAILWAY.md   passo a passo de deploy no Railway
```

## Licença

Ver [LICENSE.md](LICENSE.md) — software proprietário, uso privado.
