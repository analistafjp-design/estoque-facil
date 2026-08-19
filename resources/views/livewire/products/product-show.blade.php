<div>
    <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('products.index') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-500">&larr; Voltar para produtos</a>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">{{ $product->nome }}</h1>
                <p class="text-sm text-gray-500">{{ $product->sku }} @if($product->categoria) · {{ $product->categoria }} @endif</p>
            </div>
            <span @class([
                'self-start inline-flex items-center rounded-full px-3 py-1 text-sm font-medium',
                'bg-red-100 text-red-800' => $product->estaComEstoqueBaixo(),
                'bg-green-100 text-green-800' => ! $product->estaComEstoqueBaixo(),
            ])>
                {{ $product->quantidade_atual }} em estoque (mínimo {{ $product->estoque_minimo }})
            </span>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Lançar movimentação --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Lançar movimentação</h2>

            <form wire:submit="lancar" class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                <div class="sm:col-span-1">
                    <x-input-label for="tipo" value="Tipo" />
                    <select wire:model="tipo" id="tipo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="entrada">Entrada</option>
                        <option value="saida">Saída</option>
                    </select>
                </div>

                <div class="sm:col-span-1">
                    <x-input-label for="quantidade" value="Quantidade" />
                    <x-text-input wire:model="quantidade" id="quantidade" type="number" min="1" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('quantidade')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="observacao" value="Observação (opcional)" />
                    <x-text-input wire:model="observacao" id="observacao" type="text" class="mt-1 block w-full" placeholder="Ex.: recebimento do fornecedor X" />
                </div>

                <div class="sm:col-span-4">
                    <x-primary-button type="submit">Registrar</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Histórico --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <h2 class="text-base font-semibold text-gray-900 p-5 pb-0">Histórico de movimentações</h2>

            <div class="hidden sm:block mt-4">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quem lançou</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Observação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($movimentacoes as $mov)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-500 whitespace-nowrap">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-800' => $mov->tipo === \App\Enums\MovementType::Entrada,
                                        'bg-amber-100 text-amber-800' => $mov->tipo === \App\Enums\MovementType::Saida,
                                    ])>{{ $mov->tipo->label() }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm text-right font-medium">
                                    {{ $mov->tipo === \App\Enums\MovementType::Entrada ? '+' : '-' }}{{ $mov->quantidade }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $mov->user?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $mov->observacao ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Nenhuma movimentação ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Cartões no celular --}}
            <div class="sm:hidden divide-y divide-gray-100 mt-4">
                @forelse ($movimentacoes as $mov)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                'bg-green-100 text-green-800' => $mov->tipo === \App\Enums\MovementType::Entrada,
                                'bg-amber-100 text-amber-800' => $mov->tipo === \App\Enums\MovementType::Saida,
                            ])>{{ $mov->tipo->label() }}</span>
                            <span class="text-sm font-medium">
                                {{ $mov->tipo === \App\Enums\MovementType::Entrada ? '+' : '-' }}{{ $mov->quantidade }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $mov->created_at->format('d/m/Y H:i') }} · {{ $mov->user?->name ?? '—' }}</p>
                        @if ($mov->observacao)
                            <p class="mt-1 text-sm text-gray-700">{{ $mov->observacao }}</p>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-center text-sm text-gray-500">Nenhuma movimentação ainda.</p>
                @endforelse
            </div>

            <div class="p-4">{{ $movimentacoes->links() }}</div>
        </div>
    </div>
</div>
