<?php

namespace App\Enum;

enum TodoStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in progress';
    case REVIEW = 'review';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';

    // Get all status options as an array
    public static function getStatusOptions(): array
    {
        return [
            self::PENDING,
            self::IN_PROGRESS,
            self::REVIEW,
            self::PAUSED,
            self::COMPLETED,
        ];
    }

    // Get human-readable labels
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::REVIEW => 'Review',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
        };
    }
}
