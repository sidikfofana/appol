<?php

namespace App\Enum;

enum OrderStatus: string
{
    case SUBMITTED = 'submitted';
    case BILLED = 'billed';
    case PAID = 'paid';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public static function fromInt(int|string $value): self
    {
        return match ((string)$value) {
            '1', 'submitted' => self::SUBMITTED,
            '2', 'billed' => self::BILLED,
            '3', 'paid' => self::PAID,
            '4', 'delivered' => self::DELIVERED,
            '5', 'cancelled' => self::CANCELLED,
            default => throw new \InvalidArgumentException("\"$value\" is not a valid OrderStatus"),
        };
    }
}
