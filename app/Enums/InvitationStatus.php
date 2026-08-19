<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pendente = 'pendente';
    case Aceito = 'aceito';
    case Expirado = 'expirado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Aceito => 'Aceito',
            self::Expirado => 'Expirado',
            self::Cancelado => 'Cancelado',
        };
    }
}
