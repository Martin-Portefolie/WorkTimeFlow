<?php

namespace App\Enum;

enum Priority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public static function getValues(): array
    {
        return [
            self::LOW->value,
            self::MEDIUM->value,
            self::HIGH->value,
            self::CRITICAL->value,
        ];
    }
}
