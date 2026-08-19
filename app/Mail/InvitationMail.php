<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Não implementa ShouldQueue de propósito: enfileirar exigiria depender de
// `php artisan queue:work` rodando para o convite sair, e a spec não pede
// fila em lugar nenhum. Envio síncrono é uma escrita rápida e simples de
// mais para justificar essa peça a mais de infraestrutura na v1.
class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Convite para o '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: [
                'empresa' => $this->invitation->company->nome,
                'papel' => $this->invitation->papel->label(),
                'url' => route('invitations.accept', $this->invitation->token),
                'expiraEm' => $this->invitation->expires_at->format('d/m/Y'),
            ],
        );
    }
}
