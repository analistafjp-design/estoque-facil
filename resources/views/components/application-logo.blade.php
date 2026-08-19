{{--
    Marca do Estoque Fácil PRO: uma caixa isométrica — o objeto que o app
    controla. Sem texto de propósito: o mesmo componente é usado no topo da
    navegação (h-9) e grande na tela de login (w-20 h-20), e palavra escrita
    vira ilegível no tamanho pequeno.

    As três faces usam a MESMA cor (fill-current, herdada das classes de quem
    chama — text-gray-800 na navegação, text-gray-500 no guest) e se
    diferenciam só por opacidade. Assim o logo funciona em qualquer cor sem
    precisar de variante, e continua legível em preto e branco.
--}}
<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    {{-- topo --}}
    <path d="M24 4 43 14.5 24 25 5 14.5 24 4Z" />
    {{-- face esquerda --}}
    <path d="M4 16.8 22.6 27.1 22.6 44 4 33.7 4 16.8Z" opacity=".75" />
    {{-- face direita --}}
    <path d="M44 16.8 25.4 27.1 25.4 44 44 33.7 44 16.8Z" opacity=".5" />
</svg>
