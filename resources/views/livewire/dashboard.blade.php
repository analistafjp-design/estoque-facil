<div>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('products.index') }}" wire:navigate
                   class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    Ver produtos
                </a>
                <a href="{{ route('products.export') }}"
                   class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    Exportar Excel
                </a>
            </div>
        </div>

        {{-- Indicadores. "Sem estoque" e "Abaixo do mínimo" são contas
             separadas de propósito: com mínimo zero (o padrão de quem
             importou planilha sem essa coluna) os dois seriam o mesmo
             conjunto, e o mesmo produto apareceria como dois problemas. --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <p class="text-sm font-medium text-gray-500">Total de produtos</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ number_format($totalProdutos, 0, ',', '.') }}</p>
            </div>

            <a href="{{ route('products.index', ['filtro' => 'sem-estoque']) }}" wire:navigate
               class="block bg-white shadow-sm sm:rounded-lg p-5 hover:bg-gray-50 {{ $semEstoque > 0 ? 'ring-1 ring-red-200' : '' }}">
                <p class="text-sm font-medium text-gray-500">Sem estoque</p>
                <p class="mt-1 text-3xl font-semibold {{ $semEstoque > 0 ? 'text-red-600' : 'text-gray-900' }}">
                    {{ number_format($semEstoque, 0, ',', '.') }}
                </p>
            </a>

            <a href="{{ route('products.index', ['filtro' => 'baixo']) }}" wire:navigate
               class="block bg-white shadow-sm sm:rounded-lg p-5 hover:bg-gray-50 {{ $abaixoDoMinimo > 0 ? 'ring-1 ring-amber-200' : '' }}">
                <p class="text-sm font-medium text-gray-500">Abaixo do mínimo</p>
                <p class="mt-1 text-3xl font-semibold {{ $abaixoDoMinimo > 0 ? 'text-amber-600' : 'text-gray-900' }}">
                    {{ number_format($abaixoDoMinimo, 0, ',', '.') }}
                </p>
            </a>

            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <p class="text-sm font-medium text-gray-500">Itens em estoque</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ number_format($totalEmEstoque, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-gray-500">soma de todas as quantidades</p>
            </div>
        </div>

        @if ($totalProdutos === 0)
            {{-- Cadastro vazio: em vez de quatro zeros sem explicação, dizer
                 qual é o primeiro passo. --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center">
                <h2 class="text-base font-semibold text-gray-900">Comece cadastrando seus produtos</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Importe uma planilha que você já tem (do sistema antigo, do ERP ou do fornecedor)
                    ou cadastre o primeiro produto à mão.
                </p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('products.index') }}" wire:navigate
                       class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
                        Ir para Produtos
                    </a>
                    <a href="{{ route('products.import-template') }}"
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                        Baixar modelo de planilha
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Distribuição do saldo: com o alerta de mínimo desligado
                     (mínimo zero), é isto que mostra a real situação do
                     estoque — onde está concentrado e quanto está no limite. --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h2 class="text-base font-semibold text-gray-900">Distribuição do estoque</h2>
                    <p class="mt-1 text-xs text-gray-500">Quantos produtos há em cada faixa de saldo.</p>

                    <div class="mt-4 space-y-3">
                        @foreach ($faixas as $rotulo => $quantidade)
                            @php
                                $porcentagem = $totalProdutos > 0 ? round($quantidade / $totalProdutos * 100) : 0;
                                $cor = $rotulo === 'Sem estoque' ? 'bg-red-500'
                                    : ($rotulo === '1 a 9' ? 'bg-amber-500'
                                    : ($rotulo === '10 a 99' ? 'bg-sky-500' : 'bg-emerald-500'));
                            @endphp
                            <div>
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="text-gray-700">{{ $rotulo }}</span>
                                    <span class="text-gray-900 font-medium">
                                        {{ number_format($quantidade, 0, ',', '.') }}
                                        <span class="text-xs text-gray-500">({{ $porcentagem }}%)</span>
                                    </span>
                                </div>
                                <div class="mt-1 h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-2 rounded-full {{ $cor }}" style="width: {{ max($porcentagem, $quantidade > 0 ? 1 : 0) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Categorias --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h2 class="text-base font-semibold text-gray-900">Principais categorias</h2>
                    <p class="mt-1 text-xs text-gray-500">Categorias com mais produtos cadastrados.</p>

                    @if ($categorias['itens']->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">
                            Nenhum produto tem categoria preenchida. A categoria é opcional, mas ajuda
                            a filtrar a listagem quando o cadastro cresce.
                        </p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @foreach ($categorias['itens'] as $categoria)
                                @php
                                    $maior = $categorias['itens']->max('total') ?: 1;
                                    $largura = round($categoria->total / $maior * 100);
                                @endphp
                                <li>
                                    <div class="flex items-baseline justify-between text-sm gap-3">
                                        <span class="text-gray-700 truncate">{{ $categoria->categoria }}</span>
                                        <span class="text-gray-900 font-medium shrink-0">{{ number_format($categoria->total, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="mt-1 h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ max($largura, 1) }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($categorias['semCategoria'] > 0)
                        <p class="mt-4 text-xs text-gray-500">
                            {{ number_format($categorias['semCategoria'], 0, ',', '.') }}
                            {{ $categorias['semCategoria'] === 1 ? 'produto está' : 'produtos estão' }} sem categoria.
                        </p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Precisa de atenção --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h2 class="text-base font-semibold text-gray-900">Precisa de atenção</h2>

                    @if ($semMinimoDefinido > 0 && $semMinimoDefinido >= $totalProdutos / 2)
                        <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 p-3">
                            <p class="text-sm text-amber-900">
                                <strong>{{ number_format($semMinimoDefinido, 0, ',', '.') }}</strong>
                                {{ $semMinimoDefinido === 1 ? 'produto está' : 'produtos estão' }}
                                sem estoque mínimo definido.
                            </p>
                            <p class="mt-1 text-xs text-amber-800">
                                O alerta compara a quantidade com o mínimo — enquanto o mínimo for zero,
                                só produto zerado aparece aqui. Defina o mínimo ao editar o produto, ou
                                reimporte a planilha com uma coluna de estoque mínimo.
                            </p>
                        </div>
                    @endif

                    @if ($precisaAtencao->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">Nenhum produto zerado ou abaixo do mínimo.</p>
                    @else
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($precisaAtencao as $produto)
                                <li class="py-3 flex items-center justify-between gap-3">
                                    <a href="{{ route('products.show', $produto) }}" wire:navigate class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $produto->nome }}</p>
                                        <p class="text-xs text-gray-500">{{ $produto->sku }}</p>
                                    </a>
                                    <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $produto->quantidade_atual <= 0 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ number_format($produto->quantidade_atual, 0, ',', '.') }} / mín. {{ number_format($produto->estoque_minimo, 0, ',', '.') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('products.index', ['filtro' => 'sem-estoque']) }}" wire:navigate
                           class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-500">
                            Ver todos &rarr;
                        </a>
                    @endif
                </div>

                {{-- Movimentações --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h2 class="text-base font-semibold text-gray-900">Movimentações recentes</h2>

                    @if ($movimentacoesRecentes->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">
                            Nenhuma movimentação lançada ainda. Entradas e saídas são lançadas
                            na ficha de cada produto.
                        </p>

                        @if ($recemCadastrados->isNotEmpty())
                            <p class="mt-5 text-xs font-medium text-gray-700">Cadastrados recentemente</p>
                            <ul class="mt-2 divide-y divide-gray-100">
                                @foreach ($recemCadastrados as $produto)
                                    <li class="py-2.5 flex items-center justify-between gap-3">
                                        <a href="{{ route('products.show', $produto) }}" wire:navigate class="min-w-0">
                                            <p class="text-sm text-gray-900 truncate">{{ $produto->nome }}</p>
                                            <p class="text-xs text-gray-500">{{ $produto->sku }}</p>
                                        </a>
                                        <span class="shrink-0 text-xs text-gray-500">
                                            {{ number_format($produto->quantidade_atual, 0, ',', '.') }} un.
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($movimentacoesRecentes as $movimentacao)
                                <li class="py-3 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ $movimentacao->product?->nome ?? 'Produto removido' }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $movimentacao->user?->name ?? 'Usuário removido' }}
                                            · {{ $movimentacao->created_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $movimentacao->tipo->value === 'entrada' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $movimentacao->tipo->value === 'entrada' ? '+' : '−' }}{{ number_format($movimentacao->quantidade, 0, ',', '.') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
