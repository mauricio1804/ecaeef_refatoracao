<?php

namespace App\Enums;

enum LoanItemStatus: int
{
    case BORROWED = 0;
    case RETURNED = 1;
    case DAMAGED = 2;
    case LOST = 3;

    public function label(): string
    {
        return match ($this) {
            self::BORROWED => 'Emprestado',
            self::RETURNED => 'Devolvido',
            self::DAMAGED => 'Danificado',
            self::LOST => 'Extraviado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BORROWED => 'blue',
            self::RETURNED => 'green',
            self::DAMAGED => 'amber',
            self::LOST => 'red',
        };
    }
}
