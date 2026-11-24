<?php

namespace App\Http\Controllers\ContentStudio;

use App\Enums\ContentFormatType;
use App\Enums\MarketingAngleType;
use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\Campaign;
use App\Models\ContentIdea;
use App\Models\GeneratedContent;
use App\Modules\AICore\AIProviderManager;
use App\Modules\ContentGeneration\AIScriptGeneratorService;
use App\Modules\ContentStrategy\AIContentResearchService;
use App\Modules\ProductCatalog\ProductCategoryService;
use App\Modules\TextToSpeech\TTSProviderManager;
use App\Modules\VideoProduction\VideoProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContentScriptController extends Controller
{
    public function __construct(
        private readonly AIScriptGeneratorService $scriptService,
        private readonly VideoProjectService $videoProjectService,
        private readonly AIProviderManager $aiManager,
        private readonly TTSProviderManager $ttsManager,
        private readonly ProductCategoryService $categoryService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $scripts = GeneratedContent::query()
            ->where('user_id', $user->id)
            ->with(['videoProject.voiceoverAsset', 'affiliateProduct', 'productCategory'])
            ->latest('updated_at')
            ->limit(25)
            ->get();

        $activeScript = null;
        if ($request->filled('content_id')) {
            $activeScript = GeneratedContent::with(['idea', 'affiliateProduct', 'productCategory', 'videoProject.voiceoverAsset'])
                ->find($request->integer('content_id'));
            if ($activeScript && Gate::denies('view', $activeScript)) {
                abort(403);
            }
        } elseif ($scripts->isNotEmpty()) {
            $activeScript = $scripts->first()->load(['idea', 'affiliateProduct', 'productCategory', 'videoProject.voiceoverAsset']);
        }

        $prefillIdea = null;
        if ($request->filled('idea_id')) {
            $prefillIdea = ContentIdea::with('productCategory')->find($request->integer('idea_id'));
            if ($prefillIdea && Gate::denies('view', $prefillIdea)) {
                abort(403);
            }
        }

        $savedIdeas = ContentIdea::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(30)
            ->get(['id', 'title', 'category', 'product_category_id', 'subcategory', 'content_format', 'marketing_angle', 'experience_mode', 'target_audience', 'estimated_duration_seconds', 'affiliate_product_id']);

        $products = AffiliateProduct::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (in_array($user->role, ['owner', 'admin'], true)) {
                    $q->orWhereNull('user_id');
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'product_category_id', 'subcategory', 'price', 'approval_status']);

        $campaigns = Campaign::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $activeTtsDriver = $this->ttsManager->driver();

        return view('content-studio.scripts', [
            'scripts' => $scripts,
            'activeScript' => $activeScript,
            'prefillIdea' => $prefillIdea,
            'savedIdeas' => $savedIdeas,
            'approvedProducts' => $products,
            'campaigns' => $campaigns,
            'categoryTree' => $this->categoryService->getTree(),
            'categories' => AIContentResearchService::CATEGORIES,
            'contentFormats' => ContentFormatType::options(),
            'marketingAngles' => MarketingAngleType::options(),
            'styles' => AIScriptGeneratorService::NARRATION_STYLES,
            'durations' => AIScriptGeneratorService::DURATIONS,
            'regenerableSections' => AIScriptGeneratorService::REGENERABLE_SECTIONS,
            'hasAiProvider' => $this->aiManager->hasAnyConfiguredProvider(),
            'activeTtsName' => $activeTtsDriver->getName(),
            'activeTtsConfigured' => $activeTtsDriver->isConfigured(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'subcategory' => ['nullable', 'string', 'max:120'],
            'content_format' => ['nullable', 'string', 'in:' . implode(',', ContentFormatType::values())],
            'marketing_angle' => ['nullable', 'string', 'in:' . implode(',', MarketingAngleType::values())],
            'experience_mode' => ['nullable', 'in:research_based,first_hand'],
            'experience_usage' => ['nullable', 'string', 'max:1500'],
            'experience_pros' => ['nullable', 'string', 'max:1000'],
            'experience_cons' => ['nullable', 'string', 'max:1000'],
            'experience_results' => ['nullable', 'string', 'max:1000'],
            'target_audience' => ['required', 'string', 'max:200'],
            'narration_style' => ['required', 'string', 'in:' . implode(',', array_keys(AIScriptGeneratorService::NARRATION_STYLES))],
            'duration_seconds' => ['required', 'integer', 'in:15,30,45,60,90'],
            'content_idea_id' => ['nullable', 'integer', 'exists:content_ideas,id'],
            'affiliate_product_id' => ['nullable', 'integer', 'exists:affiliate_products,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'preferred_provider' => ['nullable', 'string', 'in:openrouter,google_gemini'],
        ]);

        $idea = null;
        if (! empty($data['content_idea_id'])) {
            $idea = ContentIdea::find($data['content_idea_id']);
            if ($idea) {
                Gate::authorize('view', $idea);
            }
        }

        $product = null;
        if (! empty($data['affiliate_product_id'])) {
            $product = AffiliateProduct::find($data['affiliate_product_id']);
            if ($product && $product->user_id !== null && (int) $product->user_id !== $request->user()->id) {
                abort(403);
            }
        }

        $catModel = $this->categoryService->resolve(
            isset($data['product_category_id']) ? (int) $data['product_category_id'] : ($idea?->product_category_id ?? $product?->product_category_id),
            $data['category'] ?? ($idea?->category ?? $product?->category),
        );
        $categoryName = trim((string) ($data['category'] ?? '')) !== ''
            ? trim((string) $data['category'])
            : ($catModel?->name ?? 'Other / General');

        $expNotes = array_filter([
            'usage_experience' => $data['experience_usage'] ?? null,
            'felt_pros' => $data['experience_pros'] ?? null,
            'found_cons' => $data['experience_cons'] ?? null,
            'visible_results' => $data['experience_results'] ?? null,
        ], fn ($v) => $v !== null && trim((string) $v) !== '');

        $result = $this->scriptService->generateScript(
            $request->user(),
            $data['title'],
            $categoryName,
            $data['target_audience'],
            $data['narration_style'],
            (int) $data['duration_seconds'],
            $idea,
            $product,
            $data['preferred_provider'] ?? null,
            $catModel,
            $data['subcategory'] ?? null,
            $data['content_format'] ?? null,
            $data['marketing_angle'] ?? null,
            $data['experience_mode'] ?? 'research_based',
            $expNotes,
            isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
        );

        if (! ($result['ok'] ?? false)) {
            return back()->withInput()->withErrors(['script' => $result['message']]);
        }

        /** @var GeneratedContent $content */
        $content = $result['content'];

        return redirect()->route('content.scripts', ['content_id' => $content->id])
            ->with('status', $result['message']);
    }

    public function update(Request $request, GeneratedContent $content): RedirectResponse
    {
        Gate::authorize('update', $content);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'hook' => ['nullable', 'string', 'max:1000'],
            'opening' => ['nullable', 'string', 'max:1500'],
            'main_narration' => ['nullable', 'string', 'max:4000'],
            'supporting_explanation' => ['nullable', 'string', 'max:3000'],
            'closing' => ['nullable', 'string', 'max:1500'],
            'cta' => ['nullable', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:2500'],
            'hashtags_text' => ['nullable', 'string', 'max:1000'],
            'disclosure_text' => ['nullable', 'string', 'max:500'],
            'target_duration_seconds' => ['nullable', 'integer', 'in:15,30,45,60,90'],
        ]);

        $hashtags = [];
        if (! empty($data['hashtags_text'])) {
            $tokens = preg_split('/[\s,]+/', trim($data['hashtags_text'])) ?: [];
            foreach ($tokens as $tok) {
                $tok = trim($tok);
                if ($tok !== '') {
                    $hashtags[] = str_starts_with($tok, '#') ? $tok : '#' . $tok;
                }
            }
        }

        $content->fill([
            'title' => $data['title'],
            'hook' => $data['hook'] ?? '',
            'opening' => $data['opening'] ?? '',
            'main_narration' => $data['main_narration'] ?? '',
            'supporting_explanation' => $data['supporting_explanation'] ?? '',
            'closing' => $data['closing'] ?? '',
            'cta' => $data['cta'] ?? '',
            'caption' => $data['caption'] ?? '',
            'hashtags' => $hashtags ?: $content->hashtags,
            'disclosure_text' => $data['disclosure_text'] ?? '',
            'target_duration_seconds' => (int) ($data['target_duration_seconds'] ?? $content->target_duration_seconds ?? 30),
        ]);
        $content->script_text = $content->buildFullNarration();
        $content->revision_count = ((int) $content->revision_count) + 1;
        $content->save();

        return redirect()->route('content.scripts', ['content_id' => $content->id])
            ->with('status', 'Perubahan draft naskah berhasil disimpan.');
    }

    public function regenerateSection(Request $request, GeneratedContent $content): RedirectResponse
    {
        Gate::authorize('update', $content);

        $data = $request->validate([
            'section' => ['required', 'string', 'in:' . implode(',', array_keys(AIScriptGeneratorService::REGENERABLE_SECTIONS))],
            'instruction' => ['nullable', 'string', 'max:300'],
            'preferred_provider' => ['nullable', 'string', 'in:openrouter,google_gemini'],
        ]);

        $res = $this->scriptService->regenerateSection(
            $content,
            $data['section'],
            $data['instruction'] ?? null,
            $data['preferred_provider'] ?? null,
        );

        if (! ($res['ok'] ?? false)) {
            return back()->withErrors(['regenerate' => $res['message']]);
        }

        return redirect()->route('content.scripts', ['content_id' => $content->id])
            ->with('status', $res['message']);
    }

    public function rewrite(Request $request, GeneratedContent $content): RedirectResponse
    {
        Gate::authorize('update', $content);

        $data = $request->validate([
            'narration_style' => ['required', 'string', 'in:' . implode(',', array_keys(AIScriptGeneratorService::NARRATION_STYLES))],
            'duration_seconds' => ['required', 'integer', 'in:15,30,45,60,90'],
            'content_format' => ['nullable', 'string', 'in:' . implode(',', ContentFormatType::values())],
            'marketing_angle' => ['nullable', 'string', 'in:' . implode(',', MarketingAngleType::values())],
            'preferred_provider' => ['nullable', 'string', 'in:openrouter,google_gemini'],
        ]);

        $res = $this->scriptService->generateScript(
            $request->user(),
            $content->title,
            $content->category ?: 'Other / General',
            $content->target_audience ?: 'Audiens TikTok Indonesia',
            $data['narration_style'],
            (int) $data['duration_seconds'],
            $content->idea,
            $content->affiliateProduct,
            $data['preferred_provider'] ?? null,
            $content->productCategory,
            $content->subcategory,
            $data['content_format'] ?? $content->content_format,
            $data['marketing_angle'] ?? $content->marketing_angle,
            $content->experience_mode ?: 'research_based',
            (array) ($content->experience_notes ?? []),
            $content->campaign_id,
        );

        if (! ($res['ok'] ?? false)) {
            return back()->withErrors(['rewrite' => $res['message']]);
        }

        /** @var GeneratedContent $fresh */
        $fresh = $res['content'];
        $content->update([
            'narration_style' => $fresh->narration_style,
            'content_format' => $fresh->content_format,
            'marketing_angle' => $fresh->marketing_angle,
            'target_duration_seconds' => $fresh->target_duration_seconds,
            'hook' => $fresh->hook,
            'opening' => $fresh->opening,
            'main_narration' => $fresh->main_narration,
            'supporting_explanation' => $fresh->supporting_explanation,
            'closing' => $fresh->closing,
            'cta' => $fresh->cta,
            'caption' => $fresh->caption,
            'hashtags' => $fresh->hashtags,
            'disclosure_text' => $fresh->disclosure_text,
            'suggested_visual_scenes' => $fresh->suggested_visual_scenes,
            'script_text' => $fresh->script_text,
            'revision_count' => ((int) $content->revision_count) + 1,
        ]);
        $fresh->delete();

        return redirect()->route('content.scripts', ['content_id' => $content->id])
            ->with('status', 'Naskah berhasil ditulis ulang dengan gaya, format, dan durasi baru.');
    }

    public function generateVoiceover(Request $request, GeneratedContent $content): RedirectResponse
    {
        Gate::authorize('update', $content);

        $data = $request->validate([
            'tts_provider' => ['nullable', 'string', 'in:google_gemini_tts,google_cloud_tts'],
            'voice_id' => ['nullable', 'string', 'max:80'],
            'style_prompt' => ['nullable', 'string', 'max:300'],
        ]);

        $project = $this->videoProjectService->getOrCreateForContent($content, $request->user());

        $opts = array_filter([
            'voice_id' => $data['voice_id'] ?? null,
            'style_prompt' => $data['style_prompt'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $res = $this->videoProjectService->generateVoiceover(
            $project,
            $request->user(),
            $data['tts_provider'] ?? null,
            $opts,
        );

        if (! ($res['ok'] ?? false)) {
            return back()->withErrors(['voiceover' => $res['message'] . ' Naskah dan aset Anda tetap aman tersimpan.']);
        }

        return back()->with('status', $res['message']);
    }

    public function continueToEditor(Request $request, GeneratedContent $content): RedirectResponse
    {
        Gate::authorize('update', $content);

        $project = $this->videoProjectService->getOrCreateForContent($content, $request->user());

        return redirect()->route('content.editor.show', $project);
    }
}
