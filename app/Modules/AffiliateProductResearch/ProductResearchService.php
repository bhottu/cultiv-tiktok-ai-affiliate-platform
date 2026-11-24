<?php

namespace App\Modules\AffiliateProductResearch;

use App\Models\AffiliateProduct;
use App\Models\ProductResearchLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProductResearchService
{
    public function listProducts(?string $approvalStatus = null): Collection
    {
        return AffiliateProduct::with(['approver', 'socialAccount'])
            ->when($approvalStatus, fn ($q) => $q->where('approval_status', $approvalStatus))
            ->orderByDesc('quality_score')
            ->latest()
            ->get();
    }

    public function listProductsForUser(User $user, ?string $approvalStatus = null): Collection
    {
        return AffiliateProduct::with(['approver', 'socialAccount'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (in_array($user->role, ['owner', 'admin'], true)) {
                    $q->orWhereNull('user_id');
                }
            })
            ->when($approvalStatus, fn ($q) => $q->where('approval_status', $approvalStatus))
            ->orderByDesc('quality_score')
            ->latest()
            ->get();
    }

    public function recentLogs(int $limit = 10): Collection
    {
        return ProductResearchLog::latest()->limit($limit)->get();
    }
}
