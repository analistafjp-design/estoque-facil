<div>
    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <h1 class="text-xl font-semibold text-gray-900">Convites</h1>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white shadow-sm rounded-lg p-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Convidar alguém</h2>

            <form wire:submit="convidar" class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                <div class="sm:col-span-2">
                    <x-input-label for="email" value="E-mail" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="papel" value="Papel" />
                    <select wire:model="papel" id="papel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="operador">Operador</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="sm:col-span-3">
                    <x-primary-button type="submit">Enviar convite</x-primary-button>
                </div>
            </form>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <ul class="divide-y divide-gray-100">
                @forelse ($convites as $convite)
                    <li class="p-4 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $convite->email }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $convite->papel->label() }} · convidado por {{ $convite->invitedBy?->name ?? '—' }}
                                @if ($convite->status === \App\Enums\InvitationStatus::Pendente)
                                    · expira em {{ $convite->expires_at->format('d/m/Y') }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                'bg-amber-100 text-amber-800' => $convite->status === \App\Enums\InvitationStatus::Pendente,
                                'bg-green-100 text-green-800' => $convite->status === \App\Enums\InvitationStatus::Aceito,
                                'bg-gray-100 text-gray-600' => $convite->status === \App\Enums\InvitationStatus::Expirado || $convite->status === \App\Enums\InvitationStatus::Cancelado,
                            ])>{{ $convite->status->label() }}</span>

                            @if ($convite->status === \App\Enums\InvitationStatus::Pendente)
                                <button
                                    wire:click="cancelar({{ $convite->id }})"
                                    wire:confirm="Cancelar o convite para {{ $convite->email }}?"
                                    type="button"
                                    class="text-sm text-red-600 hover:text-red-900"
                                >Cancelar</button>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="p-8 text-center text-sm text-gray-500">Nenhum convite enviado ainda.</li>
                @endforelse
            </ul>
        </div>

        <div>{{ $convites->links() }}</div>
    </div>
</div>
