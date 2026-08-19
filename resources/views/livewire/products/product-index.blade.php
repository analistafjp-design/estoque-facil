<div>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold text-gray-900">Produtos</h1>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('products.import-template') }}" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    Modelo de planilha
                </a>
                <a href="{{ route('products.export') }}" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    Exportar Excel
                </a>
                <button wire:click="$set('showImport', true)" type="button" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    Importar Excel
                </button>
                @if (auth()->user()->isAdmin())
                    <button wire:click="$set('showExcluirTodos', true)" type="button"
                            class="inline-flex items-center px-3 py-2 bg-white border border-red-300 rounded-md text-xs font-semibold uppercase tracking-widest text-red-700 hover:bg-red-50">
                        Excluir todos
                    </button>
                @endif
                <button wire:click="create" type="button" class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                    Novo produto
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <input
                type="search"
                wire:model.live.debounce.300ms="busca"
                placeholder="Buscar por nome ou SKU..."
                class="w-full sm:max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            >

            <div class="flex gap-2 text-sm">
                <button
                    wire:click="$set('filtro', '')"
                    type="button"
                    @class([
                        'px-3 py-1.5 rounded-full border',
                        'bg-gray-900 text-white border-gray-900' => $filtro === '',
                        'bg-white text-gray-700 border-gray-300' => $filtro !== '',
                    ])
                >Todos</button>
                <button
                    wire:click="$set('filtro', 'baixo')"
                    type="button"
                    @class([
                        'px-3 py-1.5 rounded-full border',
                        'bg-red-600 text-white border-red-600' => $filtro === 'baixo',
                        'bg-white text-gray-700 border-gray-300' => $filtro !== 'baixo',
                    ])
                >Estoque baixo</button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Tabela — a partir de sm. Em telas menores vira lista de cartões,
             porque uma tabela de 6 colunas não cabe em 320px sem rolar o
             app inteiro pro lado, o que a spec pede pra evitar. --}}
        <div class="hidden sm:block bg-white shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Estoque</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($produtos as $produto)
                        <tr class="{{ $produto->estaComEstoqueBaixo() ? 'bg-red-50/60' : '' }}">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                <a href="{{ route('products.show', $produto) }}" wire:navigate class="hover:underline">{{ $produto->nome }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $produto->sku }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $produto->categoria ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-red-100 text-red-800' => $produto->estaComEstoqueBaixo(),
                                    'bg-gray-100 text-gray-800' => ! $produto->estaComEstoqueBaixo(),
                                ])>
                                    {{ $produto->quantidade_atual }} / mín. {{ $produto->estoque_minimo }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                <button wire:click="edit({{ $produto->id }})" type="button" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                <button
                                    wire:click="desativar({{ $produto->id }})"
                                    wire:confirm="Desativar {{ $produto->nome }}? Ele some da lista, mas o histórico de movimentações continua."
                                    type="button"
                                    class="text-red-600 hover:text-red-900"
                                >Desativar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                Nenhum produto encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Cartões — só no celular. --}}
        <div class="sm:hidden space-y-3">
            @forelse ($produtos as $produto)
                <div @class([
                    'bg-white rounded-lg shadow-sm p-4 border',
                    'border-red-200 bg-red-50/60' => $produto->estaComEstoqueBaixo(),
                    'border-transparent' => ! $produto->estaComEstoqueBaixo(),
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('products.show', $produto) }}" wire:navigate class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $produto->nome }}</p>
                            <p class="text-xs text-gray-500">{{ $produto->sku }} @if($produto->categoria) · {{ $produto->categoria }} @endif</p>
                        </a>
                        <span @class([
                            'shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-red-100 text-red-800' => $produto->estaComEstoqueBaixo(),
                            'bg-gray-100 text-gray-800' => ! $produto->estaComEstoqueBaixo(),
                        ])>
                            {{ $produto->quantidade_atual }} / mín. {{ $produto->estoque_minimo }}
                        </span>
                    </div>
                    <div class="mt-3 flex gap-4 text-sm">
                        <button wire:click="edit({{ $produto->id }})" type="button" class="text-indigo-600">Editar</button>
                        <button
                            wire:click="desativar({{ $produto->id }})"
                            wire:confirm="Desativar {{ $produto->nome }}?"
                            type="button"
                            class="text-red-600"
                        >Desativar</button>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-gray-500 py-8">Nenhum produto encontrado.</p>
            @endforelse
        </div>

        <div>{{ $produtos->links() }}</div>
    </div>

    {{-- Modal: novo produto / editar produto --}}
    <x-wire-modal wireShow="$wire.showForm">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ $editingId ? 'Editar produto' : 'Novo produto' }}
            </h2>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="nome" value="Nome" />
                    <x-text-input wire:model="nome" id="nome" class="mt-1 block w-full" type="text" required autofocus />
                    <x-input-error :messages="$errors->get('nome')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="sku" value="SKU / código" />
                    <x-text-input wire:model="sku" id="sku" class="mt-1 block w-full" type="text" required />
                    <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="categoria" value="Categoria" />
                    <x-text-input wire:model="categoria" id="categoria" class="mt-1 block w-full" type="text" />
                    <x-input-error :messages="$errors->get('categoria')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="quantidade_atual" value="Quantidade atual" />
                    <x-text-input wire:model="quantidade_atual" id="quantidade_atual" class="mt-1 block w-full" type="number" min="0" required />
                    <x-input-error :messages="$errors->get('quantidade_atual')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="estoque_minimo" value="Estoque mínimo" />
                    <x-text-input wire:model="estoque_minimo" id="estoque_minimo" class="mt-1 block w-full" type="number" min="0" required />
                    <x-input-error :messages="$errors->get('estoque_minimo')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-500">Abaixo (ou igual a) este número, o produto entra no alerta de estoque baixo.</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$wire.showForm = false">Cancelar</x-secondary-button>
                <x-primary-button type="submit">Salvar</x-primary-button>
            </div>
        </form>
    </x-wire-modal>

    {{-- Modal: importar planilha --}}
    <x-wire-modal wireShow="$wire.showImport">
        <form wire:submit="importar" class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Importar produtos de uma planilha</h2>
            <p class="mt-1 text-sm text-gray-500">
                Pode enviar a planilha que você já tem — do sistema antigo, do ERP,
                do fornecedor. As colunas são reconhecidas automaticamente pelo
                cabeçalho (ex.: "Produto", "Descrição" ou "Item" viram o nome;
                "Código", "Ref." ou "SKU" viram o código), em qualquer ordem, e
                colunas que não servem são ignoradas. Só o nome do produto é
                obrigatório. Produto já cadastrado é atualizado, não duplicado.
            </p>

            <div class="mt-4">
                <input type="file" wire:model="arquivoImportacao" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-gray-700">

                {{-- O arquivo sobe ao ser escolhido, ANTES de clicar em Importar.
                     Numa conexão lenta isso leva alguns segundos, e sem este
                     aviso (mais o botão desabilitado abaixo) dá pra clicar em
                     Importar antes do envio terminar — o servidor ainda não
                     teria o arquivo e responderia "campo obrigatório", que não
                     explica nada pra quem acabou de escolher o arquivo. --}}
                <div wire:loading wire:target="arquivoImportacao" class="mt-2 text-xs text-gray-600">
                    Enviando arquivo... aguarde para importar.
                </div>

                <div wire:loading.remove wire:target="arquivoImportacao">
                    @if ($arquivoImportacao)
                        <div class="mt-2 text-xs text-green-700">Arquivo pronto para importar.</div>
                    @endif
                </div>

                <x-input-error :messages="$errors->get('arquivoImportacao')" class="mt-2" />
            </div>

            {{-- Conferência do mapeamento: o passo que impede importar a
                 coluna errada. A detecção automática vem pré-selecionada,
                 mas quem decide é quem está vendo a planilha. --}}
            @if ($conferindo)
                <div class="mt-5 border-t border-gray-200 pt-5">
                    <h3 class="text-sm font-semibold text-gray-900">Confira as colunas</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Encontramos {{ $totalLinhasPlanilha }}
                        {{ $totalLinhasPlanilha === 1 ? 'linha' : 'linhas' }} de dados.
                        Ajuste abaixo se alguma coluna tiver sido lida errado.
                    </p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'nome' => 'Nome do produto',
                            'sku' => 'Código / SKU',
                            'categoria' => 'Categoria',
                            'quantidade_atual' => 'Quantidade',
                            'estoque_minimo' => 'Estoque mínimo',
                        ] as $campo => $rotulo)
                            <div>
                                <label class="block text-xs font-medium text-gray-700">
                                    {{ $rotulo }}
                                    @if ($campo === 'nome')
                                        <span class="text-red-600">*</span>
                                    @endif
                                </label>
                                <select wire:model.live="mapa.{{ $campo }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— não importar —</option>
                                    @foreach ($colunasDaPlanilha as $indice => $rotuloColuna)
                                        <option value="{{ $indice }}">{{ $rotuloColuna }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>

                    <x-input-error :messages="$errors->get('mapa.nome')" class="mt-2" />

                    {{-- Prévia com o dado real: ver "1000013" na coluna Nome
                         denuncia o mapeamento errado antes de importar, não
                         depois de sujar o cadastro. --}}
                    @if ($amostra)
                        <div class="mt-4">
                            <p class="text-xs font-medium text-gray-700">Prévia</p>
                            <div class="mt-1 overflow-x-auto rounded-md border border-gray-200">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-600">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium">Nome</th>
                                            <th class="px-3 py-2 text-left font-medium">SKU</th>
                                            <th class="px-3 py-2 text-left font-medium">Categoria</th>
                                            <th class="px-3 py-2 text-right font-medium">Qtd.</th>
                                            <th class="px-3 py-2 text-right font-medium">Mín.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach ($amostra as $linha)
                                            <tr>
                                                @foreach (['nome', 'sku', 'categoria', 'quantidade_atual', 'estoque_minimo'] as $campo)
                                                    @php
                                                        $indice = $mapa[$campo] ?? '';
                                                        $valor = $indice !== '' ? ($linha[(int) $indice] ?? null) : null;
                                                        $alinha = in_array($campo, ['quantidade_atual', 'estoque_minimo'], true) ? 'text-right' : '';
                                                    @endphp
                                                    <td class="px-3 py-2 {{ $alinha }} {{ $campo === 'nome' ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                                        {{ is_scalar($valor) ? \Illuminate\Support\Str::limit((string) $valor, 40) : '—' }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$wire.showImport = false">Cancelar</x-secondary-button>
                {{-- :disabled (atributo bound), não @disabled: dentro de uma
                     tag de componente Blade a diretiva não compila. --}}
                <x-primary-button type="submit" wire:loading.attr="disabled"
                                  wire:target="importar, arquivoImportacao"
                                  :disabled="! $conferindo">
                    Importar
                </x-primary-button>
            </div>
        </form>
    </x-wire-modal>

    {{-- Exclusão em massa: destrutiva de verdade (não é o "desativar" do
         CRUD, que preserva histórico). Existe para desfazer importação que
         entrou errada sem ter que mexer item por item. --}}
    <x-wire-modal wireShow="$wire.showExcluirTodos">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Excluir todos os produtos</h2>

            <div class="mt-3 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                <p class="font-semibold">Esta ação não pode ser desfeita.</p>
                <p class="mt-1">
                    Todos os produtos desta empresa serão apagados de vez, junto com
                    <strong>todo o histórico de movimentações</strong> — entradas e saídas
                    lançadas também somem. Use para recomeçar um cadastro que entrou errado.
                </p>
            </div>

            <div class="mt-4">
                <label for="confirmacaoExclusao" class="block text-sm text-gray-700">
                    Para confirmar, digite <strong>EXCLUIR</strong>:
                </label>
                <input id="confirmacaoExclusao" type="text" wire:model="confirmacaoExclusao"
                       autocomplete="off"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                <x-input-error :messages="$errors->get('confirmacaoExclusao')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$wire.showExcluirTodos = false">Cancelar</x-secondary-button>
                <button wire:click="excluirTodos" type="button" wire:loading.attr="disabled" wire:target="excluirTodos"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-700 disabled:opacity-50">
                    Excluir tudo
                </button>
            </div>
        </div>
    </x-wire-modal>
</div>
