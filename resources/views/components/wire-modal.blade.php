@props(['wireShow', 'maxWidth' => '2xl'])

@php
$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

{{--
    Variante do <x-modal> padrão do Breeze que reage a uma propriedade
    Livewire de verdade (via $wire, que o Alpine mantém reativo entre
    re-renders) em vez de só a um evento de navegador — o <x-modal>
    original só abre de novo se alguém disparar 'open-modal' pelo Alpine;
    abrir a partir de um método PHP (ex.: editar um registro específico e
    já preencher o formulário) não teria como reabrir o modal de outro
    jeito.
--}}
<div
    x-show="{{ $wireShow }}"
    style="display: none;"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    x-on:keydown.escape.window="{{ $wireShow }} = false"
>
    <div
        x-show="{{ $wireShow }}"
        class="fixed inset-0 transform transition-all"
        x-on:click="{{ $wireShow }} = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    <div
        x-show="{{ $wireShow }}"
        class="mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidthClass }} sm:mx-auto"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>
