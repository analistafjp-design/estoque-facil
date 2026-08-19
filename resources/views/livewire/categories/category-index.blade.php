<div>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Categorias</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Planilha de ERP costuma trazer a categoria como código. Dê um nome
                    a cada uma — vale para todos os produtos do grupo de uma vez.
                </p>
            </div>
            <a href="{{ route('products.index') }}" wire:navigate
               class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                Ver produtos
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar categoria..."
               class="block w-full sm:max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium uppercase tracking-wider text-xs">Categoria</th>
                            <th class="px-4 py-3 text-right font-medium uppercase tracking-wider text-xs">Produtos</th>
                            <th class="px-4 py-3 text-right font-medium uppercase tracking-wider text-xs">Itens</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($categorias as $categoria)
                            <tr wire:key="cat-{{ md5($categoria->categoria) }}">
                                <td class="px-4 py-3">
                                    @if ($editando === $categoria->categoria)
                                        <form wire:submit="salvar" class="flex flex-wrap items-center gap-2">
                                            <input type="text" wire:model="novoNome" autofocus
                                                   class="block w-full sm:w-72 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 bg-gray-900 border border-transparent rounded-md text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                                                Salvar
                                            </button>
                                            <button type="button" wire:click="cancelar"
                                                    class="text-xs text-gray-600 hover:text-gray-900">Cancelar</button>
                                        </form>
                                        <x-input-error :messages="$errors->get('novoNome')" class="mt-2" />
                                    @else
                                        <a href="{{ route('products.index', ['categoria' => $categoria->categoria]) }}" wire:navigate
                                           class="font-medium text-gray-900 hover:text-indigo-600">
                                            {{ $categoria->categoria }}
                                        </a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ number_format($categoria->total, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ number_format((int) $categoria->itens, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($editando !== $categoria->categoria)
                                        <button type="button" wire:click="editar(@js($categoria->categoria))"
                                                class="text-indigo-600 hover:text-indigo-500">Renomear</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    @if ($busca !== '')
                                        Nenhuma categoria encontrada para "{{ $busca }}".
                                    @else
                                        Nenhum produto tem categoria preenchida.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categorias->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $categorias->links() }}</div>
            @endif
        </div>

        @if ($semCategoria > 0)
            <p class="text-sm text-gray-500">
                {{ number_format($semCategoria, 0, ',', '.') }}
                {{ $semCategoria === 1 ? 'produto está' : 'produtos estão' }} sem categoria.
            </p>
        @endif
    </div>
</div>
