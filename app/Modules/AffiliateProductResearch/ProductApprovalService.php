<?php

namespace App\Modules\AffiliateProductResearch;

use App\Enums\ProductApprovalStatus;
use App\Models\AffiliateProduct;
use App\Models\User;
use App\Modules\ActivityLogs\ActivityLogger;

class ProductApprovalService
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function transition(
        AffiliateProduct $product,
        User $actor,
        ProductApprovalStatus $targetStatus,
        ?string $notes = null,
    ): AffiliateProduct {
        $previous = $product->approval_status;

        $updates = [
            'approval_status' => $targetStatus->value,
            'approval_notes' => $notes !== null ? trim($notes) : $product->approval_notes,
        ];

        if ($targetStatus === ProductApprovalStatus::APPROVED) {
            $updates['approved_by'] = $actor->id;
            $updates['approved_at'] = now();
        } elseif (in_array($targetStatus, [ProductApprovalStatus::REJECTED, ProductApprovalStatus::ARCHIVED, ProductApprovalStatus::PENDING_APPROVAL], true)) {
            $updates['approved_by'] = $actor->id;
            $updates['approved_at'] = null;
        }

        $product->update($updates);

        $this->logger->log(
            'affiliate_approval',
            'status_changed',
            "Status produk '{$product->name}' diubah dari '{$previous}' menjadi '{$targetStatus->value}'.",
            'info',
            [
                'affiliate_product_id' => $product->id,
                'from_status' => $previous,
                'to_status' => $targetStatus->value,
                'has_notes' => ! empty($notes),
            ],
            AffiliateProduct::class,
            $product->id,
            $actor->id,
        );

        return $product->fresh();
    }
}
