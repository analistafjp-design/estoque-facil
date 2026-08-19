<?php

namespace App\Livewire\Invitations;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class InvitationIndex extends Component
{
    public string $email = '';

    public string $papel = 'operador';

    public function mount(): void
    {
        // A rota já tem o middleware 'admin', mas o componente confere de
        // novo — a mesma regra da spec pra multi-tenancy vale aqui:
        // permissão não pode depender só de quem lembrou de proteger a rota.
        abort_unless(Auth::user()->isAdmin(), 403);
    }

    /**
     * Convite por e-mail, limitado por tentativa — a mesma ideia do login
     * (spec item 6), só que aqui o abuso seria spammar caixa de entrada de
     * terceiros em vez de forçar senha.
     */
    public function convidar(): void
    {
        $key = 'invite:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $segundos = RateLimiter::availableIn($key);
            $this->addError('email', "Muitos convites em pouco tempo. Tente de novo em {$segundos}s.");

            return;
        }

        $validated = $this->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('invitations', 'email')
                    ->where('company_id', Auth::user()->company_id)
                    ->where('status', InvitationStatus::Pendente->value),
            ],
            'papel' => ['required', Rule::enum(UserRole::class)],
        ], [
            'email.unique' => 'Já existe um usuário ou convite pendente com este e-mail.',
        ]);

        RateLimiter::hit($key, 60);

        $invitation = Invitation::create([
            'email' => $validated['email'],
            'papel' => $validated['papel'],
            'status' => InvitationStatus::Pendente,
            'token' => Str::random(64),
            'invited_by_id' => Auth::id(),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        $this->reset(['email', 'papel']);
        session()->flash('status', 'Convite enviado.');
    }

    public function cancelar(int $invitationId): void
    {
        Invitation::where('status', InvitationStatus::Pendente)
            ->findOrFail($invitationId)
            ->update(['status' => InvitationStatus::Cancelado]);
    }

    public function render(): View
    {
        return view('livewire.invitations.invitation-index', [
            'convites' => Invitation::with('invitedBy')->latest()->paginate(10),
        ]);
    }
}
