<?php

namespace App\Livewire\Invitations;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class InvitationAccept extends Component
{
    public ?Invitation $invitation = null;

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        // Sem usuário logado ainda (rota 'guest'), então o CompanyScope
        // nem entraria em ação aqui — a busca é só pelo token, que é
        // quem autentica este fluxo específico.
        $invitation = Invitation::where('token', $token)->first();

        if ($invitation && $invitation->status === InvitationStatus::Pendente && ! $invitation->isExpired()) {
            $this->invitation = $invitation;
        }
    }

    /**
     * Cria o usuário com o papel e a empresa que o convite já define — o
     * convidado não escolhe nem empresa nem papel, só nome e senha.
     */
    public function aceitar(): void
    {
        abort_unless($this->invitation, 404);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'company_id' => $this->invitation->company_id,
                'name' => $validated['name'],
                'email' => $this->invitation->email,
                'password' => Hash::make($validated['password']),
                'papel' => $this->invitation->papel,
                'email_verified_at' => now(), // veio de um e-mail que ele já provou controlar, abrindo o link
            ]);

            $this->invitation->update([
                'status' => InvitationStatus::Aceito,
                'accepted_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.invitations.invitation-accept');
    }
}
