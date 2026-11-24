<?php

namespace App\Enums;

enum RenderJobStatus: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case PREPARING = 'preparing';
    case RENDERING = 'rendering';
    case FINALIZING = 'finalizing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::QUEUED => 'Queued',
            self::PREPARING => 'Preparing',
            self::RENDERING => 'Rendering',
            self::FINALIZING => 'Finalizing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
