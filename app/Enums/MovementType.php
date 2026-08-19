<?php

namespace App\Enums;

enum MovementType: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
        };
    }
}
