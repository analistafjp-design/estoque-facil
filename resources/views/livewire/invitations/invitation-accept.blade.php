<div>
    @if (! $invitation)
        <div class="text-center">
            <h1 class="text-lg font-semibold text-gray-900">Convite inválido ou expirado</h1>
            <p class="mt-2 text-sm text-gray-600">
                Este link já foi usado, cancelado ou não existe mais. Peça para quem te convidou mandar um novo.
            </p>
            <a href="{{ route('login') }}" wire:navigate class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-500">
                Ir para o login
            </a>
        </div>
    @else
        <div class="mb-4 text-sm text-gray-600">
            Você foi convidado para <strong>{{ $invitation->company->nome }}</strong> como
            <strong>{{ $invitation->papel->label() }}</strong>. Defina seu nome e senha para entrar.
        </div>

        <form wire:submit="aceitar">
            <div>
                <x-input-label for="name" value="Seu nome" />
                <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label value="E-mail" />
                <p class="mt-1 block w-full text-sm text-gray-500">{{ $invitation->email }}</p>
            </div>

            <div class="mt-4">
                <x-input-label for="password" value="Senha" />
                <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password_confirmation" value="Confirme a senha" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-primary-button>Entrar</x-primary-button>
            </div>
        </form>
    @endif
</div>
