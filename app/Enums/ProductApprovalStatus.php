<?php

namespace App\Enums;

enum ProductApprovalStatus: string
{
    case DISCOVERED = 'discovered';
    case ANALYZED = 'analyzed';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DISCOVERED => 'Ditemukan (Discovered)',
            self::ANALYZED => 'Dianalisis AI (Analyzed)',
            self::PENDING_APPROVAL => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui (Approved)',
            self::REJECTED => 'Ditolak (Rejected)',
            self::ARCHIVED => 'Diarsipkan (Archived)',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
