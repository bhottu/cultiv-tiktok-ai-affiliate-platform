<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';
    case WAITING_CREDENTIALS = 'waiting_credentials';
    case WAITING_PRODUCT_TAG = 'waiting_product_tag';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui',
            self::PROCESSING => 'Diproses',
            self::COMPLETED => 'Selesai',
            self::FAILED => 'Gagal',
            self::SCHEDULED => 'Terjadwal',
            self::PUBLISHED => 'Terpublikasi',
            self::WAITING_CREDENTIALS => 'Menunggu Kredensial',
            self::WAITING_PRODUCT_TAG => 'Menunggu Keranjang Kuning',
            self::REJECTED => 'Ditolak',
        };
    }
}
