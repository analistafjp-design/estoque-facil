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
                Use o modelo (botão "Modelo de planilha") para garantir as colunas certas.
                Produto com o mesmo SKU é atualizado; SKU novo é criado.
            </p>

            <div class="mt-4">
                <input type="file" wire:model="arquivoImportacao" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-gray-700">
                <div wire:loading wire:target="arquivoImportacao" class="mt-1 text-xs text-gray-500">Enviando arquivo...</div>
                <x-input-error :messages="$errors->get('arquivoImportacao')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$wire.showImport = false">Cancelar</x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="importar">Importar</x-primary-button>
            </div>
        </form>
    </x-wire-modal>
</div>
