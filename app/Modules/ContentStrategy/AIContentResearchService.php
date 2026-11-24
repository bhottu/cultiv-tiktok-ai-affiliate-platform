<?php

namespace App\Modules\ContentStrategy;

use App\Enums\ContentFormatType;
use App\Enums\ContentPipelineStatus;
use App\Enums\MarketingAngleType;
use App\Models\AffiliateProduct;
use App\Models\ContentIdea;
use App\Models\ProductCategory;
use App\Models\User;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AICore\AIProviderManager;
use App\Modules\AICore\UniversalAffiliatePromptBuilder;
use App\Modules\ProductCatalog\ProductCategoryService;

class AIContentResearchService
{
    /**
     * Backward-compatible legacy category list (still supported alongside dynamic product_categories table).
     */
    public const CATEGORIES = [
        'Electronics & Gadgets',
        'Fashion & Apparel',
        'Beauty & Skincare',
        'Health & Personal Care',
        'Home & Living',
        'Kitchen & Household',
        'Automotive & Accessories',
        'Sports & Outdoor',
        'Baby & Kids',
        'Pet Supplies',
        'Food & Beverage',
        'Books & Education',
        'Toys & Hobbies',
        'Plants & Gardening',
        'Other / General',
        // Legacy Phase 3 plant labels preserved for backward compatibility
        'Plant Education',
        'Product Review',
        'Plant Showcase',
        'Problem & Solution',
        'Storytelling',
        'Plant Care Tips',
        'Rare Plant Facts',
        'Affiliate Product Promotion',
    ];

    public function __construct(
        private readonly AIProviderManager $aiManager,
        private readonly ActivityLogger $logger,
        private readonly UniversalAffiliatePromptBuilder $promptBuilder,
        private readonly ProductCategoryService $categoryService,
    ) {}

    /**
     * Universal AI Product & Content Research (15-point analysis + multi-idea generation).
     *
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, analysis?: array<string, mixed>, ideas?: array<int, ContentIdea>, provider_used?: string, message: string}
     */
    public function researchProduct(User $user, array $input): array
    {
        if (! $this->aiManager->hasAnyConfiguredProvider()) {
            return [
                'ok' => false,
                'message' => 'Belum ada AI Provider (OpenRouter atau Google Gemini) yang dikonfigurasi. Atur di menu AI Provider.',
            ];
        }

        $product = null;
        if (! empty($input['affiliate_product_id'])) {
            $product = AffiliateProduct::find((int) $input['affiliate_product_id']);
        }

        $categoryModel = $this->categoryService->resolve(
            isset($input['product_category_id']) ? (int) $input['product_category_id'] : $product?->product_category_id,
            (string) ($input['category'] ?? $product?->category ?? 'Other / General'),
        );
        $categoryName = trim((string) ($input['category'] ?? '')) !== ''
            ? trim((string) $input['category'])
            : ($categoryModel?->name ?? 'Other / General');

        $subcategory = trim((string) ($input['subcategory'] ?? $product?->subcategory ?? ''));
        $productName = trim((string) ($input['product_name'] ?? $product?->name ?? ''));
        $experienceMode = ($input['experience_mode'] ?? 'research_based') === 'first_hand' ? 'first_hand' : 'research_based';
        $experienceNotes = is_array($input['experience_notes'] ?? null) ? $input['experience_notes'] : [];
        $count = max(1, min(8, (int) ($input['count'] ?? 4)));

        $promptInput = [
            'product_name' => $productName,
            'category_name' => $categoryName,
            'subcategory' => $subcategory,
            'product_description' => trim((string) ($input['product_description'] ?? $product?->description ?? '')),
            'product_advantages' => trim((string) ($input['product_advantages'] ?? (is_array($product?->features) ? implode('; ', $product->features) : ''))),
            'price' => $input['price'] ?? $product?->price,
            'commission_info' => trim((string) ($input['commission_info'] ?? ($product?->commission_rate !== null ? $product->commission_rate . '%' : ''))),
            'target_audience' => trim((string) ($input['target_audience'] ?? $product?->target_audience ?? 'Audiens TikTok Indonesia')),
            'content_goal' => trim((string) ($input['content_goal'] ?? 'Edukasi objektif dan rekomendasi produk affiliate')),
            'product_url' => trim((string) ($input['product_url'] ?? $product?->product_url ?? '')),
            'has_product_image' => ! empty($input['has_product_image']) || ! empty($product?->image_url),
            'manual_notes' => trim((string) ($input['manual_notes'] ?? $input['topic_hint'] ?? $product?->research_notes ?? '')),
            'experience_mode' => $experienceMode,
            'experience_notes' => $experienceNotes,
            'count' => $count,
        ];

        $prompts = $this->promptBuilder->buildProductResearchPrompt($promptInput);

        $res = $this->aiManager->chatWithFallback(
            [
                ['role' => 'system', 'content' => $prompts['system']],
                ['role' => 'user', 'content' => $prompts['user']],
            ],
            $input['preferred_provider'] ?? null,
            null,
            ['temperature' => 0.65, 'max_tokens' => 2800],
        );

        if (! ($res['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Gagal melakukan riset produk & ide konten AI.')];
        }

        $decoded = $this->decodeJson((string) ($res['content'] ?? ''));
        $rawIdeas = is_array($decoded['ideas'] ?? null) ? $decoded['ideas'] : [];
        $analysis = is_array($decoded['analysis'] ?? null) ? $decoded['analysis'] : [];

        if (empty($rawIdeas)) {
            return ['ok' => false, 'message' => 'AI tidak mengembalikan daftar ide dalam format JSON yang valid. Silakan coba lagi.'];
        }

        $created = [];
        foreach (array_slice($rawIdeas, 0, $count) as $item) {
            if (! is_array($item) || empty($item['title'])) {
                continue;
            }
            $dur = (int) ($item['estimated_duration_seconds'] ?? 30);
            if (! in_array($dur, [15, 30, 45, 60, 90], true)) {
                $dur = $dur <= 22 ? 15 : ($dur <= 37 ? 30 : ($dur <= 52 ? 45 : ($dur <= 75 ? 60 : 90)));
            }

            $rawFormat = (string) ($item['content_format'] ?? 'product_review');
            $contentFormat = ContentFormatType::tryFrom($rawFormat)?->value ?? ContentFormatType::PRODUCT_REVIEW->value;
            $rawAngle = (string) ($item['marketing_angle'] ?? 'problem_solving');
            $angle = MarketingAngleType::tryFrom($rawAngle)?->value ?? MarketingAngleType::PROBLEM_SOLVING->value;

            $created[] = ContentIdea::create([
                'user_id' => $user->id,
                'affiliate_product_id' => $product?->id,
                'product_name' => $productName !== '' ? mb_substr($productName, 0, 220) : null,
                'title' => mb_substr(trim((string) $item['title']), 0, 220),
                'category' => $categoryName,
                'product_category_id' => $categoryModel?->id,
                'subcategory' => $subcategory !== '' ? mb_substr($subcategory, 0, 120) : null,
                'target_audience' => $promptInput['target_audience'],
                'content_goal' => $promptInput['content_goal'],
                'format_type' => $contentFormat,
                'content_format' => $contentFormat,
                'marketing_angle' => $angle,
                'angle' => $angle,
                'experience_mode' => $experienceMode,
                'experience_notes' => $experienceMode === 'first_hand' ? $experienceNotes : null,
                'research_analysis' => $analysis ?: null,
                'hook_concept' => trim((string) ($item['hook_concept'] ?? '')),
                'concept_summary' => trim((string) ($item['concept_summary'] ?? '')),
                'visual_recommendation' => trim((string) ($item['visual_recommendation'] ?? '')),
                'cta_recommendation' => mb_substr(trim((string) ($item['cta_recommendation'] ?? '')), 0, 255),
                'estimated_duration_seconds' => $dur,
                'needs_fact_verification' => (bool) ($item['needs_fact_verification'] ?? ! empty($analysis['claims_needing_verification'])),
                'saved_to_library' => false,
                'status' => ContentPipelineStatus::IDEA->value,
                'metadata' => [
                    'provider_used' => $res['provider_used'] ?? null,
                    'model_used' => $res['model'] ?? null,
                    'fallback_used' => (bool) ($res['fallback_used'] ?? false),
                    'narration_style' => $item['narration_style'] ?? 'edukatif',
                    'product_snapshot' => array_filter([
                        'price' => $promptInput['price'],
                        'commission_info' => $promptInput['commission_info'],
                        'product_url' => $promptInput['product_url'],
                        'product_description' => $promptInput['product_description'],
                    ], fn ($v) => $v !== null && $v !== ''),
                ],
            ]);
        }

        $this->logger->log(
            'content_research',
            'universal_research_completed',
            'Menghasilkan ' . count($created) . " ide konten ({$categoryName}" . ($productName ? " · {$productName}" : '') . ') via ' . ($res['provider_used'] ?? 'AI') . '.',
            'info',
            [
                'count' => count($created),
                'category' => $categoryName,
                'experience_mode' => $experienceMode,
                'provider_used' => $res['provider_used'] ?? null,
            ],
            null,
            null,
            $user->id,
        );

        return [
            'ok' => true,
            'analysis' => $analysis,
            'ideas' => $created,
            'provider_used' => $res['provider_used'] ?? null,
            'message' => 'Berhasil menyelesaikan riset produk dan menghasilkan ' . count($created) . " ide konten ({$categoryName}).",
        ];
    }

    /**
     * Backward-compatible wrapper for Phase 3 callers/tests.
     *
     * @return array{ok: bool, ideas?: array<int, ContentIdea>, provider_used?: string, message: string}
     */
    public function generateIdeas(
        User $user,
        string $category,
        string $targetAudience,
        string $contentGoal,
        int $count = 4,
        ?string $topicHint = null,
        ?AffiliateProduct $product = null,
        ?string $preferredProvider = null,
    ): array {
        return $this->researchProduct($user, [
            'category' => $category,
            'target_audience' => $targetAudience,
            'content_goal' => $contentGoal,
            'count' => $count,
            'manual_notes' => $topicHint,
            'affiliate_product_id' => $product?->id,
            'preferred_provider' => $preferredProvider,
            'experience_mode' => 'research_based',
        ]);
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $raw): ?array
    {
        $clean = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $clean, $m)) {
            $clean = $m[1];
        } else {
            $s = strpos($clean, '{');
            $e = strrpos($clean, '}');
            if ($s !== false && $e !== false && $e > $s) {
                $clean = substr($clean, $s, $e - $s + 1);
            }
        }
        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }
}
