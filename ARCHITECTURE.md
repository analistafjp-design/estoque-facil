# Arquitetura — Estoque Fácil PRO

Este documento explica as três coisas que mais importam pra quem for mexer
neste código depois: como o isolamento entre empresas funciona, onde ficam
as credenciais, e como o fluxo de autenticação/convite está montado. Para
como instalar e rodar, ver o [README](README.md).

## Multi-tenancy: como uma empresa nunca vê dado de outra

O modelo é multi-tenant por coluna: toda tabela de dado de negócio
(`products`, `stock_movements`, `invitations`) tem uma coluna `company_id`.
`users` também tem `company_id` — cada usuário pertence a exatamente uma
empresa.

O que faz o isolamento valer sozinho, sem depender de ninguém lembrar de
filtrar, são duas peças em `app/Models`:

- **`Scopes/CompanyScope`** — um [global scope][scope] do Eloquent. Toda
  vez que `Product`, `StockMovement` ou `Invitation` são consultados
  (`all()`, `find()`, `where()`, o que for), o Eloquent injeta sozinho um
  `WHERE company_id = <empresa do usuário logado>` na query, antes dela
  rodar. Ninguém escreve esse `where` à mão em controller nenhum — é
  literalmente impossível esquecer, porque o filtro não está no
  controller, está no model.
- **`Concerns/BelongsToCompany`** — a trait que registra o `CompanyScope`
  e também preenche `company_id` sozinho quando um registro novo é criado
  (a partir do usuário autenticado), pelo mesmo motivo: tirar do
  desenvolvedor a responsabilidade de lembrar.

```php
class Product extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;
    // ...
}
```

Isso cobre `SELECT`, `INSERT`, e também `UPDATE`/`DELETE` feitos através
do model (`Product::findOrFail($id)->update(...)`) — porque o `findOrFail`
já passa pelo scope. É por isso que "editar produto de outra empresa
forjando o ID na URL" não retorna "acesso negado": retorna **404**, porque
o registro genuinely não é encontrado pela query com o filtro aplicado.
Quem manipula a URL não sabe se o ID não existe ou se existe em outra
empresa — a resposta é a mesma, o que também evita vazar essa informação.

`User` **não** usa `BelongsToCompany` de propósito — filtrar o próprio
model de autenticação pelo scope da empresa logada criaria comportamento
estranho durante o próprio processo de login. Onde faz sentido restringir
usuários por empresa (ex.: uma futura tela de "gerenciar equipe"), isso é
feito explicitamente via `$company->users`, não pelo scope automático.

[scope]: https://laravel.com/docs/eloquent#global-scopes

### A prova disso: `tests/Feature/Tenancy/`

`CompanyIsolationTest.php` é o teste mais importante do projeto. Ele cria
duas empresas, dois usuários, produtos/movimentações/convites para cada
uma, loga como o usuário da Empresa A e comprova, em HTTP de verdade (não
só chamando o model direto):

- a listagem de produtos da Empresa A não mostra nada da Empresa B;
- abrir a ficha de um produto da Empresa B pela URL devolve 404;
- editar/desativar um produto da Empresa B por ID forjado lança
  `ModelNotFoundException` (a mesma exceção que vira 404 numa request
  real) — nada é alterado;
- o mesmo vale para convites e para o histórico de movimentações;
- a exportação de Excel só inclui produtos da empresa de quem exportou.

`RolePermissionTest.php` cobre a segunda dimensão de permissão: papel
(`admin` vs. `operador`) dentro da mesma empresa — só admin acessa
`/convites`, e o teste confere isso tanto pela ausência do link na
navegação quanto pelo 403 ao forçar a URL.

## Onde ficam as credenciais

Nenhuma credencial (senha de banco, chave de API, segredo de sessão) fica
escrita no código-fonte. Tudo vem de variáveis de ambiente, lidas pelos
arquivos em `config/*.php` via `env('ALGUMA_COISA')`, e o valor real mora
só no `.env` local — que está no `.gitignore` e nunca é commitado.

`.env.example` documenta toda variável necessária, com comentário
explicando o que ela faz, mas **sem nenhum valor real** — é o roteiro para
alguém criar o próprio `.env`, não uma cópia dele. Ver a seção
"Instalação" do README para o passo a passo.

`APP_KEY` merece nota à parte: não é uma senha de serviço externo, é a
chave simétrica que o Laravel usa para assinar sessão/cookies e criptografar
dado sensível internamente. Cada ambiente (local, produção) deve ter a
própria, gerada com `php artisan key:generate` — nunca reaproveitada entre
ambientes.

## Fluxo de autenticação

Autenticação em si (login, hash de senha, "esqueci minha senha",
verificação de e-mail) é o Laravel Breeze com Livewire/Volt — não foi
reimplementado do zero, conforme a spec pede. O que é específico deste
projeto é como uma **conta** se torna uma **empresa com gente dentro**:

### 1. Cadastro (`/register`) — cria empresa + primeiro usuário

O formulário de cadastro pede nome da empresa, nome de quem está
cadastrando, e-mail e senha. `Company` e `User` são criados na mesma
transação (`DB::transaction`, em
`resources/views/livewire/pages/auth/register.blade.php`): se a criação do
usuário falhasse por qualquer razão, a empresa não ficaria órfã no banco.
Quem se cadastra assim sempre vira **admin** da empresa nova — é o dono da
conta.

### 2. Convite (`/convites`, só admin) — traz gente pro time

Um admin convida por e-mail, escolhendo o papel (`admin` ou `operador`).
Isso cria um registro em `invitations` com um token aleatório de 64
caracteres e validade de 7 dias, e dispara um e-mail
(`App\Mail\InvitationMail`) com o link `/convite/{token}`.

Convite tem rate limiting manual (10 por admin por minuto) — o mesmo
princípio do rate limiting de login que o Breeze já traz, só que aqui o
abuso seria martelar caixa de entrada de terceiros em vez de forçar senha.

### 3. Aceitar convite (`/convite/{token}`) — vira usuário da empresa

Rota pública (`guest`), mas o token é quem autentica esse fluxo específico.
`InvitationAccept::mount()` busca o convite pelo token; se não existir, já
tiver sido usado, tiver sido cancelado ou estiver expirado, a tela mostra
"convite inválido" em vez de formulário. Quem aceita só escolhe nome e
senha — **não** escolhe empresa nem papel, os dois já vêm do convite. O
e-mail também vem do convite (não é um campo editável no formulário),
então não tem como usar um link de convite pra criar conta com outro
e-mail.

### Papéis

- **admin** — tudo que operador faz, mais: convidar gente, cancelar
  convite, gerenciar dados da empresa.
- **operador** — CRUD de produto, lançar entrada/saída, ver dashboard,
  importar/exportar planilha. Não acessa `/convites` (middleware `admin`
  na rota, mais uma checagem redundante dentro do próprio componente
  Livewire — ver `InvitationIndex::mount()`).

## Por que Livewire "de dois jeitos" (Volt e componente de classe)

As telas de autenticação do Breeze (login, cadastro, esqueci senha) usam
[Volt][volt] — componentes de arquivo único, o padrão que o
`breeze:install livewire` já escreve. As telas de negócio (produtos,
movimentação, convites, dashboard) usam componentes de classe tradicionais
em `app/Livewire/`. Não é inconsistência: é manter o que o Breeze já
gera do jeito que ele gera, e usar o padrão de classe (mais fácil de
testar com `Livewire::test()`, mais natural para lógica com vários métodos)
no resto. Os dois são Livewire de verdade, spec não pede um estilo único.

[volt]: https://livewire.laravel.com/docs/volt
