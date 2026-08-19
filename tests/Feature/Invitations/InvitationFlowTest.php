<?php

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Livewire\Invitations\InvitationAccept;
use App\Livewire\Invitations\InvitationIndex;
use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
});

test('admin convida alguém e o e-mail é enviado', function () {
    Mail::fake();

    Livewire::actingAs($this->admin)
        ->test(InvitationIndex::class)
        ->set('email', 'novo@example.com')
        ->set('papel', 'operador')
        ->call('convidar')
        ->assertHasNoErrors();

    $convite = Invitation::where('email', 'novo@example.com')->firstOrFail();

    expect($convite->company_id)->toBe($this->company->id)
        ->and($convite->status)->toBe(InvitationStatus::Pendente)
        ->and($convite->invited_by_id)->toBe($this->admin->id);

    Mail::assertSent(InvitationMail::class, fn ($mail) => $mail->invitation->is($convite));
});

test('não convida e-mail que já é usuário', function () {
    User::factory()->create(['company_id' => $this->company->id, 'email' => 'ja-existe@example.com']);

    Livewire::actingAs($this->admin)
        ->test(InvitationIndex::class)
        ->set('email', 'ja-existe@example.com')
        ->call('convidar')
        ->assertHasErrors(['email']);
});

test('não convida o mesmo e-mail duas vezes enquanto o convite estiver pendente', function () {
    Invitation::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'pendente@example.com',
        'status' => InvitationStatus::Pendente,
    ]);

    Livewire::actingAs($this->admin)
        ->test(InvitationIndex::class)
        ->set('email', 'pendente@example.com')
        ->call('convidar')
        ->assertHasErrors(['email']);
});

test('aceitar convite cria usuário com a empresa e o papel do convite', function () {
    $convite = Invitation::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'convidado@example.com',
        'papel' => UserRole::Operador,
        'status' => InvitationStatus::Pendente,
    ]);

    Livewire::test(InvitationAccept::class, ['token' => $convite->token])
        ->set('name', 'Fulano Convidado')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('aceitar')
        ->assertRedirect(route('dashboard', absolute: false));

    $usuario = User::where('email', 'convidado@example.com')->firstOrFail();

    expect($usuario->company_id)->toBe($this->company->id)
        ->and($usuario->papel)->toBe(UserRole::Operador);

    expect($convite->fresh()->status)->toBe(InvitationStatus::Aceito);
    $this->assertAuthenticatedAs($usuario);
});

test('convite expirado não pode ser aceito', function () {
    $convite = Invitation::factory()->create([
        'company_id' => $this->company->id,
        'status' => InvitationStatus::Pendente,
        'expires_at' => now()->subDay(),
    ]);

    Livewire::test(InvitationAccept::class, ['token' => $convite->token])
        ->assertSet('invitation', null);
});

test('convite já aceito não pode ser usado de novo', function () {
    $convite = Invitation::factory()->create([
        'company_id' => $this->company->id,
        'status' => InvitationStatus::Aceito,
    ]);

    Livewire::test(InvitationAccept::class, ['token' => $convite->token])
        ->assertSet('invitation', null);
});

test('token inválido não quebra a tela, só não mostra o formulário', function () {
    Livewire::test(InvitationAccept::class, ['token' => 'token-que-nao-existe'])
        ->assertSet('invitation', null)
        ->assertOk();
});

test('admin cancela convite pendente', function () {
    $convite = Invitation::factory()->create([
        'company_id' => $this->company->id,
        'status' => InvitationStatus::Pendente,
    ]);

    Livewire::actingAs($this->admin)
        ->test(InvitationIndex::class)
        ->call('cancelar', $convite->id);

    expect($convite->fresh()->status)->toBe(InvitationStatus::Cancelado);
});

test('convite tem limite de tentativas por administrador', function () {
    Mail::fake();

    Livewire::actingAs($this->admin)->test(InvitationIndex::class);

    // A décima primeira tentativa no mesmo minuto deve ser barrada.
    for ($i = 1; $i <= 10; $i++) {
        Livewire::actingAs($this->admin)
            ->test(InvitationIndex::class)
            ->set('email', "convite{$i}@example.com")
            ->call('convidar');
    }

    Livewire::actingAs($this->admin)
        ->test(InvitationIndex::class)
        ->set('email', 'umaacima@example.com')
        ->call('convidar')
        ->assertHasErrors(['email']);

    expect(Invitation::where('email', 'umaacima@example.com')->exists())->toBeFalse();
});
