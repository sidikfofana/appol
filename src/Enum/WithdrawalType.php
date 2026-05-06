<?php

namespace App\Enum;

enum WithdrawalType: string
{
    case PICK_UP = 'pick_up';
    case DELIVERY = 'delivery';
    case OTHER = 'other';

    public static function fromInt(int|string $value): self
    {
        return match ((string)$value) {
            '1', 'pick_up' => self::PICK_UP,
            '2', 'delivery' => self::DELIVERY,
            '3', 'other' => self::OTHER,
            default => throw new \InvalidArgumentException("\"$value\" is not a valid WithdrawalType"),
        };
    }
}

