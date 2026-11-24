<?php

namespace App\Enums;

enum ContentPipelineStatus: string
{
    case IDEA = 'idea';
    case SCRIPT_DRAFT = 'script_draft';
    case VOICEOVER_READY = 'voiceover_ready';
    case READY_TO_RENDER = 'ready_to_render';
    case RENDERING = 'rendering';
    case VIDEO_READY = 'video_ready';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::IDEA => 'Idea',
            self::SCRIPT_DRAFT => 'Script Draft',
            self::VOICEOVER_READY => 'Voiceover Ready',
            self::READY_TO_RENDER => 'Ready to Render',
            self::RENDERING => 'Rendering',
            self::VIDEO_READY => 'Video Ready',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::ARCHIVED => 'Archived',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
