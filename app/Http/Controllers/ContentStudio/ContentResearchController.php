<?php

namespace App\Http\Controllers\ContentStudio;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\ContentIdea;
use App\Modules\AICore\AIProviderManager;
use App\Modules\ContentStrategy\AIContentResearchService;
use App\Modules\ProductCatalog\ProductCategoryService;
use App\Modules\VideoProduction\VisualAssetManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContentResearchController extends Controller
{
    public function __construct(
        private readonly AIContentResearchService $researchService,
        private readonly AIProviderManager $aiManager,
        private readonly ProductCategoryService $categoryService,
        private readonly VisualAssetManagerService $assetService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $categoryFilter = $request->string('category')->toString();
        $categoryIdFilter = $request->integer('category_id');
        $savedOnly = $request->boolean('saved');

        $ideas = ContentIdea::query()
            ->where('user_id', $user->id)
            ->with(['affiliateProduct', 'productCategory'])
            ->when($categoryIdFilter > 0, fn ($q) => $q->where('product_category_id', $categoryIdFilter))
            ->when($categoryFilter !== '', fn ($q) => $q->where('category', $categoryFilter))
            ->when($savedOnly, fn ($q) => $q->where('saved_to_library', true))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $userProducts = AffiliateProduct::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (in_array($user->role, ['owner', 'admin'], true)) {
                    $q->orWhereNull('user_id');
                }
            })
            ->with('productCategory')
            ->orderByDesc('approval_status')
            ->orderBy('name')
            ->get();

        $latestAnalysis = $ideas->first(fn ($i) => ! empty($i->research_analysis))?->research_analysis;

        return view('content-studio.research', [
            'ideas' => $ideas,
            'latestAnalysis' => $latestAnalysis,
            'categoryTree' => $this->categoryService->getTree(),
            'categories' => AIContentResearchService::CATEGORIES,
            'approvedProducts' => $userProducts,
            'hasAiProvider' => $this->aiManager->hasAnyConfiguredProvider(),
            'activeProvider' => $this->aiManager->getDefaultProviderSlug(),
            'categoryFilter' => $categoryFilter,
            'categoryIdFilter' => $categoryIdFilter,
            'savedOnly' => $savedOnly,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'subcategory' => ['nullable', 'string', 'max:120'],
            'product_name' => ['nullable', 'string', 'max:220'],
            'product_description' => ['nullable', 'string', 'max:3000'],
            'product_advantages' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'commission_info' => ['nullable', 'string', 'max:120'],
            'product_url' => ['nullable', 'url', 'max:500'],
            'product_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'manual_notes' => ['nullable', 'string', 'max:2000'],
            'topic_hint' => ['nullable', 'string', 'max:500'],
            'target_audience' => ['required', 'string', 'max:200'],
            'content_goal' => ['required', 'string', 'max:200'],
            'count' => ['required', 'integer', 'min:1', 'max:8'],
            'affiliate_product_id' => ['nullable', 'integer', 'exists:affiliate_products,id'],
            'experience_mode' => ['nullable', 'in:research_based,first_hand'],
            'experience_usage' => ['nullable', 'string', 'max:1500'],
            'experience_pros' => ['nullable', 'string', 'max:1000'],
            'experience_cons' => ['nullable', 'string', 'max:1000'],
            'experience_results' => ['nullable', 'string', 'max:1000'],
            'preferred_provider' => ['nullable', 'string', 'in:openrouter,google_gemini'],
        ]);

        $product = null;
        if (! empty($data['affiliate_product_id'])) {
            $product = AffiliateProduct::find($data['affiliate_product_id']);
            if ($product && $product->user_id !== null && (int) $product->user_id !== $request->user()->id) {
                abort(403);
            }
        }

        $hasUploadedImage = false;
        if ($request->hasFile('product_image')) {
            $catModel = $this->categoryService->resolve(
                isset($data['product_category_id']) ? (int) $data['product_category_id'] : $product?->product_category_id,
                $data['category'] ?? null,
            );
            $this->assetService->storeUpload(
                $request->user(),
                $request->file('product_image'),
                'product_photo',
                null,
                $product?->id,
                null,
                $catModel?->id,
            );
            $hasUploadedImage = true;
        }

        $expNotes = array_filter([
            'usage_experience' => $data['experience_usage'] ?? null,
            'felt_pros' => $data['experience_pros'] ?? null,
            'found_cons' => $data['experience_cons'] ?? null,
            'visible_results' => $data['experience_results'] ?? null,
        ], fn ($v) => $v !== null && trim((string) $v) !== '');

        $result = $this->researchService->researchProduct($request->user(), [
            'product_category_id' => $data['product_category_id'] ?? $product?->product_category_id,
            'category' => $data['category'] ?? null,
            'subcategory' => $data['subcategory'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'product_description' => $data['product_description'] ?? null,
            'product_advantages' => $data['product_advantages'] ?? null,
            'price' => $data['price'] ?? null,
            'commission_info' => $data['commission_info'] ?? null,
            'product_url' => $data['product_url'] ?? null,
            'has_product_image' => $hasUploadedImage,
            'manual_notes' => $data['manual_notes'] ?? ($data['topic_hint'] ?? null),
            'target_audience' => $data['target_audience'],
            'content_goal' => $data['content_goal'],
            'count' => (int) $data['count'],
            'affiliate_product_id' => $product?->id,
            'experience_mode' => $data['experience_mode'] ?? 'research_based',
            'experience_notes' => $expNotes,
            'preferred_provider' => $data['preferred_provider'] ?? null,
        ]);

        if (! ($result['ok'] ?? false)) {
            return back()->withInput()->withErrors(['research' => $result['message']]);
        }

        return redirect()->route('content.research')
            ->with('status', $result['message']);
    }

    public function createCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'theme_preset' => ['nullable', 'string', 'in:tech_dark,fashion_editorial,beauty_soft,health_clean,home_warm,auto_carbon,sports_energy,food_terracotta,botanical_green,neutral_studio'],
        ]);

        $cat = $this->categoryService->createCategory(
            $data['name'],
            isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            $data['theme_preset'] ?? null,
        );

        return back()->with('status', "Kategori '{$cat->name}' berhasil ditambahkan dan langsung tersedia di seluruh modul.");
    }

    public function saveToLibrary(ContentIdea $idea): RedirectResponse
    {
        Gate::authorize('update', $idea);

        $idea->update(['saved_to_library' => ! $idea->saved_to_library]);

        $msg = $idea->saved_to_library
            ? "Ide '{$idea->title}' disimpan ke Content Library."
            : "Ide '{$idea->title}' dilepas dari daftar tersimpan.";

        return back()->with('status', $msg);
    }

    public function destroy(ContentIdea $idea): RedirectResponse
    {
        Gate::authorize('delete', $idea);

        $title = $idea->title;
        $idea->delete();

        return back()->with('status', "Ide '{$title}' telah dihapus.");
    }
}
