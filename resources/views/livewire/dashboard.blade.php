<div>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>

        {{-- Cards de resumo — empilham em 1 coluna no celular, 3 a partir do sm. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <p class="text-sm font-medium text-gray-500">Total de produtos</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalProdutos }}</p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5 {{ $totalEstoqueBaixo > 0 ? 'ring-1 ring-red-200' : '' }}">
                <p class="text-sm font-medium text-gray-500">Produtos com estoque baixo</p>
                <p class="mt-1 text-3xl font-semibold {{ $totalEstoqueBaixo > 0 ? 'text-red-600' : 'text-gray-900' }}">
                    {{ $totalEstoqueBaixo }}
                </p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <p class="text-sm font-medium text-gray-500">Movimentações recentes</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $movimentacoesRecentes->count() }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Estoque baixo --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Estoque baixo</h2>

                @if ($estoqueBaixo->isEmpty())
                    <p class="text-sm text-gray-500">Nenhum produto abaixo do estoque mínimo.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($estoqueBaixo as $produto)
                            <li class="py-3 flex items-center justify-between gap-3">
                                <a href="{{ route('products.show', $produto) }}" wire:navigate class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $produto->nome }}</p>
                                    <p class="text-xs text-gray-500">{{ $produto->sku }}</p>
                                </a>
                                <span class="shrink-0 inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                    {{ $produto->quantidade_atual }} / mín. {{ $produto->estoque_minimo }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('products.index', ['filtro' => 'baixo']) }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        Ver todos &rarr;
                    </a>
                @endif
            </div>

            {{-- Movimentações recentes --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Movimentações recentes</h2>

                @if ($movimentacoesRecentes->isEmpty())
                    <p class="text-sm text-gray-500">Nenhuma movimentação lançada ainda.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($movimentacoesRecentes as $mov)
                            <li class="py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $mov->product?->nome ?? 'Produto removido' }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $mov->user?->name ?? 'Usuário removido' }} · {{ $mov->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                <span @class([
                                    'shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $mov->tipo === \App\Enums\MovementType::Entrada,
                                    'bg-amber-100 text-amber-800' => $mov->tipo === \App\Enums\MovementType::Saida,
                                ])>
                                    {{ $mov->tipo === \App\Enums\MovementType::Entrada ? '+' : '-' }}{{ $mov->quantidade }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
