<?php

namespace App\Http\Controllers;

use App\Enums\ProductApprovalStatus;
use App\Models\AffiliateProduct;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AffiliateProductResearch\ProductAnalysisService;
use App\Modules\AffiliateProductResearch\ProductApprovalService;
use App\Modules\AffiliateProductResearch\ProductResearchService;
use App\Modules\AffiliateProductResearch\TikTokShopAffiliateClient;
use App\Modules\AICore\AIProviderManager;
use App\Modules\ProductCatalog\ProductCategoryService;
use App\Modules\TikTokIntegration\TikTokPlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateDashboardController extends Controller
{
    public function __construct(
        private readonly TikTokPlatformService $tiktokPlatform,
        private readonly TikTokShopAffiliateClient $shopClient,
        private readonly ProductResearchService $researchService,
        private readonly ProductAnalysisService $analysisService,
        private readonly ProductApprovalService $approvalService,
        private readonly AIProviderManager $aiManager,
        private readonly ProductCategoryService $categoryService,
        private readonly ActivityLogger $logger,
    ) {}

    public function readiness(Request $request): View
    {
        $account = $this->tiktokPlatform->resolveAccountForUser($request->user());

        return view('affiliate.readiness', [
            'account' => $account,
            'readiness' => $this->shopClient->getReadinessAssessment($account),
            'niches' => TikTokShopAffiliateClient::PLANT_COACTION_NICHES,
            'categories' => $this->categoryService->getTree(),
        ]);
    }

    public function products(Request $request): View
    {
        $statusFilter = $request->query('status');
        $categoryFilter = $request->integer('category_id');
        $user = $request->user();

        $products = $this->researchService->listProductsForUser($user, $statusFilter ?: null);
        if ($categoryFilter > 0) {
            $products = $products->where('product_category_id', $categoryFilter)->values();
        }

        return view('affiliate.products', [
            'apiConfigured' => $this->shopClient->isApiConfigured(),
            'products' => $products,
            'logs' => $this->researchService->recentLogs(8),
            'niches' => TikTokShopAffiliateClient::PLANT_COACTION_NICHES,
            'categories' => $this->categoryService->getTree(),
            'statuses' => ProductApprovalStatus::cases(),
            'activeStatus' => $statusFilter,
            'activeCategoryId' => $categoryFilter,
            'aiProviders' => $this->aiManager->available(),
            'aiReady' => $this->aiManager->hasAnyConfiguredProvider(),
        ]);
    }

    public function approvedProducts(Request $request): View
    {
        $user = $request->user();

        return view('affiliate.approved', [
            'products' => $this->researchService->listProductsForUser($user, ProductApprovalStatus::APPROVED->value),
        ]);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:220'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'subcategory' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'seller_name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'features_text' => ['nullable', 'string', 'max:2000'],
            'specifications_text' => ['nullable', 'string', 'max:2000'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'product_url' => ['nullable', 'url', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'research_notes' => ['nullable', 'string', 'max:2000'],
            'verification_status' => ['nullable', 'in:unverified,partially_verified,verified'],
            'affiliate_eligibility_status' => ['nullable', 'in:unverified,eligible,not_eligible'],
        ], [
            'name.required' => 'Nama produk wajib diisi.',
        ]);

        $catModel = $this->categoryService->resolve(
            isset($data['product_category_id']) ? (int) $data['product_category_id'] : null,
            $data['category'] ?? null,
        );
        $categoryName = trim((string) ($data['category'] ?? '')) !== ''
            ? trim((string) $data['category'])
            : ($catModel?->name ?? 'Other / General');

        $features = [];
        if (! empty($data['features_text'])) {
            $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|;/', $data['features_text']) ?: [])));
        }

        $specs = [];
        if (! empty($data['specifications_text'])) {
            foreach (preg_split('/\r\n|\r|\n/', $data['specifications_text']) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, ':')) {
                    [$k, $v] = array_map('trim', explode(':', $line, 2));
                    if ($k !== '') {
                        $specs[$k] = $v;
                    }
                } else {
                    $specs[] = $line;
                }
            }
        }

        $product = AffiliateProduct::create([
            'user_id' => $request->user()->id,
            'external_product_id' => 'manual-' . Str::uuid()->toString(),
            'source_platform' => 'manual_input',
            'data_source_type' => 'manual_input',
            'verification_status' => $data['verification_status'] ?? 'unverified',
            'affiliate_eligibility_status' => $data['affiliate_eligibility_status'] ?? 'unverified',
            'name' => trim($data['name']),
            'category' => $categoryName,
            'product_category_id' => $catModel?->id,
            'subcategory' => ! empty($data['subcategory']) ? trim($data['subcategory']) : null,
            'brand' => ! empty($data['brand']) ? trim($data['brand']) : null,
            'seller_name' => ! empty($data['seller_name']) ? trim($data['seller_name']) : null,
            'description' => ! empty($data['description']) ? trim($data['description']) : null,
            'features' => $features ?: null,
            'specifications' => $specs ?: null,
            'target_audience' => ! empty($data['target_audience']) ? trim($data['target_audience']) : null,
            'price' => $data['price'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'product_url' => $data['product_url'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'research_notes' => ! empty($data['research_notes']) ? trim($data['research_notes']) : null,
            'approval_status' => ProductApprovalStatus::DISCOVERED->value,
            'metadata' => ['created_via' => 'manual_form_phase3_1'],
        ]);

        $this->logger->log(
            'affiliate_research',
            'manual_product_created',
            "Produk manual '{$product->name}' ({$product->category}) ditambahkan oleh pengguna.",
            'info',
            ['affiliate_product_id' => $product->id, 'category' => $product->category, 'data_source_type' => 'manual_input'],
            AffiliateProduct::class,
            $product->id,
            $request->user()->id,
        );

        return redirect()->route('affiliate.products')
            ->with('status', "Produk '{$product->name}' berhasil ditambahkan (sumber: Input Manual) dan siap dianalisis AI.");
    }

    public function search(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'min:2', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
        ], [
            'keyword.required' => 'Kata kunci pencarian produk wajib diisi.',
        ]);

        $account = $this->tiktokPlatform->resolveAccountForUser($request->user());
        $res = $this->shopClient->searchOfficialProducts(
            $request->user(),
            $account,
            $data['keyword'],
            $data['category'] ?? null,
        );

        if (! $res['ok']) {
            return redirect()
                ->route('affiliate.products')
                ->withErrors(['shop_api' => $res['message']]);
        }

        return redirect()
            ->route('affiliate.products')
            ->with('status', $res['message']);
    }

    public function analyze(Request $request, AffiliateProduct $product): RedirectResponse
    {
        Gate::authorize('analyze', $product);

        $data = $request->validate([
            'provider' => ['nullable', 'in:openrouter,google_gemini'],
        ]);

        $res = $this->analysisService->analyze(
            $product,
            $request->user(),
            $data['provider'] ?? null,
        );

        if (! $res['ok']) {
            return redirect()
                ->route('affiliate.products')
                ->withErrors(['ai_analysis' => $res['message']]);
        }

        return redirect()
            ->route('affiliate.products')
            ->with('status', $res['message']);
    }

    public function updateStatus(Request $request, AffiliateProduct $product): RedirectResponse
    {
        Gate::authorize('approve', $product);

        $data = $request->validate([
            'approval_status' => ['required', Rule::in(ProductApprovalStatus::values())],
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'approval_status.in' => 'Status persetujuan produk tidak valid.',
        ]);

        $target = ProductApprovalStatus::from($data['approval_status']);
        $this->approvalService->transition($product, $request->user(), $target, $data['approval_notes'] ?? null);

        return back()->with('status', "Status produk '{$product->name}' diperbarui menjadi {$target->label()}.");
    }
}
