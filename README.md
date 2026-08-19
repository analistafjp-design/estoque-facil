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
- Importação de produtos via planilha Excel (upsert por SKU: SKU
  existente atualiza, SKU novo cria) — modelo de planilha disponível
  para download dentro do app (tela Produtos → "Modelo de planilha", ou
  `GET /produtos-modelo-planilha` autenticado)
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
comportamento é o mesmo. 69 testes, cobrindo:

- **isolamento multi-tenant** (`tests/Feature/Tenancy/`) — o requisito
  não-negociável da spec: Empresa A nunca lê, edita ou apaga dado da
  Empresa B, nem manipulando ID na URL;
- **papéis** admin/operador;
- **CRUD de produto**, incluindo SKU duplicado/único por empresa e soft
  delete;
- **movimentação de estoque** — entrada, saída, bloqueio de saída maior
  que o estoque, transação atômica;
- **convite** — envio, aceite, expiração, rate limiting;
- **import/export** de planilha.

## Deploy

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
├── Imports/              Maatwebsite: importação de produtos (upsert por SKU)
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
```

## Licença

Ver [LICENSE.md](LICENSE.md) — software proprietário, uso privado.
