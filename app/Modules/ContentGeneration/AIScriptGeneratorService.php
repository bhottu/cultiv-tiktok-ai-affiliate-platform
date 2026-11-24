<?php

namespace App\Modules\ContentGeneration;

use App\Enums\ContentFormatType;
use App\Enums\ContentPipelineStatus;
use App\Enums\MarketingAngleType;
use App\Models\AffiliateProduct;
use App\Models\ContentIdea;
use App\Models\GeneratedContent;
use App\Models\ProductCategory;
use App\Models\User;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AICore\AIProviderManager;
use App\Modules\AICore\UniversalAffiliatePromptBuilder;
use App\Modules\ProductCatalog\ProductCategoryService;

class AIScriptGeneratorService
{
    public const NARRATION_STYLES = [
        'edukatif' => 'Edukatif',
        'santai' => 'Santai dan Natural',
        'storytelling' => 'Storytelling',
        'review_produk' => 'Review Produk',
        'persuasif' => 'Persuasif',
        'fun_facts' => 'Fun Facts',
    ];

    public const DURATIONS = [15, 30, 45, 60, 90];

    public const REGENERABLE_SECTIONS = [
        'hook' => 'Hook (3 detik pertama)',
        'opening' => 'Opening',
        'main_narration' => 'Main Narration',
        'supporting_explanation' => 'Supporting Explanation',
        'closing' => 'Closing',
        'cta' => 'CTA',
    ];

    public function __construct(
        private readonly AIProviderManager $aiManager,
        private readonly ActivityLogger $logger,
        private readonly UniversalAffiliatePromptBuilder $promptBuilder,
        private readonly ProductCategoryService $categoryService,
    ) {}

    /**
     * Generate a universal structured TikTok affiliate/content script in natural Indonesian.
     * Supports 14 content formats, 10 marketing angles, category-adaptive structure, and strict honesty mode.
     *
     * @param  array<string, mixed>  $experienceNotes
     * @return array{ok: bool, content?: GeneratedContent, message: string}
     */
    public function generateScript(
        User $user,
        string $title,
        string $category,
        string $targetAudience,
        string $narrationStyle,
        int $durationSeconds,
        ?ContentIdea $idea = null,
        ?AffiliateProduct $product = null,
        ?string $preferredProvider = null,
        ?ProductCategory $categoryModel = null,
        ?string $subcategory = null,
        ?string $contentFormat = null,
        ?string $marketingAngle = null,
        string $experienceMode = 'research_based',
        array $experienceNotes = [],
        ?int $campaignId = null,
    ): array {
        if (! $this->aiManager->hasAnyConfiguredProvider()) {
            return ['ok' => false, 'message' => 'Belum ada AI Provider yang dikonfigurasi. Atur di menu AI Provider.'];
        }

        $resolvedCategory = $categoryModel ?: $this->categoryService->resolve(
            $idea?->product_category_id ?? $product?->product_category_id,
            $category ?: ($product?->category ?? 'Other / General'),
        );
        $categoryName = trim($category) !== '' ? trim($category) : ($resolvedCategory?->name ?? 'Other / General');
        $resolvedSub = trim((string) ($subcategory ?? $idea?->subcategory ?? $product?->subcategory ?? ''));
        $resolvedFormat = ContentFormatType::tryFrom((string) ($contentFormat ?: $idea?->content_format ?: 'product_review'))?->value
            ?? ContentFormatType::PRODUCT_REVIEW->value;
        $resolvedAngle = MarketingAngleType::tryFrom((string) ($marketingAngle ?: $idea?->marketing_angle ?: 'auto'))?->value
            ?? MarketingAngleType::AUTO->value;
        $resolvedExpMode = $experienceMode === 'first_hand' ? 'first_hand' : 'research_based';
        $resolvedExpNotes = $resolvedExpMode === 'first_hand'
            ? (! empty($experienceNotes) ? $experienceNotes : (array) ($idea?->experience_notes ?? []))
            : [];

        $productContext = '';
        if ($product) {
            $lines = [
                "Nama Produk: {$product->name}",
                "Kategori: {$categoryName}" . ($resolvedSub !== '' ? " ({$resolvedSub})" : ''),
                'Brand: ' . ($product->brand ?: 'Tidak tersedia'),
                'Deskripsi: ' . ($product->description ?: 'Tidak tersedia'),
                'Fitur: ' . (! empty($product->features) ? implode('; ', (array) $product->features) : 'Tidak tersedia'),
                'Spesifikasi: ' . (! empty($product->specifications) ? json_encode($product->specifications, JSON_UNESCAPED_UNICODE) : 'Tidak tersedia'),
                'Harga: ' . ($product->price !== null ? 'Rp' . number_format((float) $product->price, 0, ',', '.') : 'Tidak tersedia'),
                'Komisi: ' . ($product->commission_rate !== null ? $product->commission_rate . '%' : 'Tidak tersedia'),
                'Seller: ' . ($product->seller_name ?: 'Tidak tersedia'),
                'Sumber Data: ' . ($product->data_source_type ?: $product->source_platform ?: 'manual_input'),
            ];
            $productContext = implode("\n", $lines);
        } elseif ($idea && ! empty($idea->product_name)) {
            $snap = (array) data_get($idea->metadata, 'product_snapshot', []);
            $productContext = "Produk dari riset: {$idea->product_name}"
                . (! empty($snap['product_description']) ? "\nDeskripsi: {$snap['product_description']}" : '')
                . (! empty($snap['price']) ? "\nHarga: Rp" . number_format((float) $snap['price'], 0, ',', '.') : "\nHarga: Tidak tersedia");
        }

        $ideaContext = '';
        if ($idea) {
            $ideaParts = array_filter([
                $idea->concept_summary ? "Konsep ide: {$idea->concept_summary}" : null,
                $idea->hook_concept ? "Hook awal: {$idea->hook_concept}" : null,
                $idea->visual_recommendation ? "Saran visual: {$idea->visual_recommendation}" : null,
                ! empty($idea->research_analysis['unique_selling_point']) ? 'USP: ' . $idea->research_analysis['unique_selling_point'] : null,
                ! empty($idea->research_analysis['unknown_information']) ? 'Info belum tersedia (jangan dikarang): ' . implode(', ', (array) $idea->research_analysis['unknown_information']) : null,
            ]);
            $ideaContext = implode("\n", $ideaParts);
        }

        $prompts = $this->promptBuilder->buildScriptGenerationPrompt([
            'title' => $title,
            'category_name' => $categoryName,
            'subcategory' => $resolvedSub,
            'content_format' => $resolvedFormat,
            'marketing_angle' => $resolvedAngle,
            'style_label' => self::NARRATION_STYLES[$narrationStyle] ?? 'Edukatif',
            'duration_seconds' => $durationSeconds,
            'word_budget' => $this->wordBudgetForDuration($durationSeconds),
            'target_audience' => $targetAudience,
            'experience_mode' => $resolvedExpMode,
            'experience_notes' => $resolvedExpNotes,
            'idea_context' => $ideaContext,
            'product_context' => $productContext,
        ]);

        $res = $this->aiManager->chatWithFallback(
            [
                ['role' => 'system', 'content' => $prompts['system']],
                ['role' => 'user', 'content' => $prompts['user']],
            ],
            $preferredProvider,
            null,
            ['temperature' => 0.7, 'max_tokens' => 2400],
        );

        if (! ($res['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Gagal menghasilkan naskah video.')];
        }

        $parsed = $this->decodeJson((string) ($res['content'] ?? ''));
        if (! $parsed || empty($parsed['hook'])) {
            return ['ok' => false, 'message' => 'Respons AI tidak dalam format JSON naskah yang valid. Silakan coba lagi.'];
        }

        $chosenAngle = MarketingAngleType::tryFrom((string) ($parsed['selected_angle'] ?? ''))?->value
            ?? ($resolvedAngle === 'auto' ? MarketingAngleType::PROBLEM_SOLVING->value : $resolvedAngle);

        $content = new GeneratedContent([
            'user_id' => $user->id,
            'campaign_id' => $campaignId,
            'content_idea_id' => $idea?->id,
            'affiliate_product_id' => $product?->id ?? $idea?->affiliate_product_id,
            'title' => mb_substr(trim((string) ($parsed['title'] ?? $title)), 0, 220),
            'category' => $categoryName,
            'product_category_id' => $resolvedCategory?->id,
            'subcategory' => $resolvedSub !== '' ? mb_substr($resolvedSub, 0, 120) : null,
            'target_audience' => $targetAudience,
            'narration_style' => $narrationStyle,
            'content_format' => $resolvedFormat,
            'marketing_angle' => $chosenAngle,
            'experience_mode' => $resolvedExpMode,
            'experience_notes' => $resolvedExpMode === 'first_hand' ? $resolvedExpNotes : null,
            'target_duration_seconds' => $durationSeconds,
            'hook' => trim((string) ($parsed['hook'] ?? '')),
            'opening' => trim((string) ($parsed['opening'] ?? '')),
            'main_narration' => trim((string) ($parsed['main_narration'] ?? '')),
            'supporting_explanation' => trim((string) ($parsed['supporting_explanation'] ?? '')),
            'closing' => trim((string) ($parsed['closing'] ?? '')),
            'cta' => trim((string) ($parsed['cta'] ?? '')),
            'caption' => trim((string) ($parsed['caption'] ?? '')),
            'hashtags' => array_values(array_map('strval', (array) ($parsed['hashtags'] ?? ['#tiktokshop', '#rekomendasi']))),
            'disclosure_text' => trim((string) ($parsed['disclosure_text'] ?? '')),
            'suggested_visual_scenes' => is_array($parsed['suggested_visual_scenes'] ?? null) ? $parsed['suggested_visual_scenes'] : [],
            'status' => ContentPipelineStatus::SCRIPT_DRAFT->value,
            'metadata' => [
                'provider_used' => $res['provider_used'] ?? null,
                'model_used' => $res['model'] ?? null,
                'fallback_used' => (bool) ($res['fallback_used'] ?? false),
            ],
        ]);
        $content->script_text = $content->buildFullNarration();
        $content->save();

        if ($idea) {
            $idea->update(['saved_to_library' => true, 'status' => ContentPipelineStatus::SCRIPT_DRAFT->value]);
        }

        $this->logger->log(
            'content_scripts',
            'script_generated',
            "Naskah video '{$content->title}' ({$categoryName} · {$resolvedFormat} · {$durationSeconds}s) dibuat via " . ($res['provider_used'] ?? 'AI') . '.',
            'info',
            [
                'generated_content_id' => $content->id,
                'category' => $categoryName,
                'content_format' => $resolvedFormat,
                'experience_mode' => $resolvedExpMode,
                'duration' => $durationSeconds,
            ],
            GeneratedContent::class,
            $content->id,
            $user->id,
        );

        return ['ok' => true, 'content' => $content, 'message' => 'Naskah video berhasil digenerate dan disimpan sebagai draft.'];
    }

    /**
     * Regenerate ONLY one requested section without altering any other section of the script.
     *
     * @return array{ok: bool, content?: GeneratedContent, section_value?: string, message: string}
     */
    public function regenerateSection(
        GeneratedContent $content,
        string $section,
        ?string $instruction = null,
        ?string $preferredProvider = null,
    ): array {
        if (! array_key_exists($section, self::REGENERABLE_SECTIONS)) {
            return ['ok' => false, 'message' => "Bagian naskah '{$section}' tidak valid."];
        }

        if (! $this->aiManager->hasAnyConfiguredProvider()) {
            return ['ok' => false, 'message' => 'Belum ada AI Provider yang dikonfigurasi.'];
        }

        $label = self::REGENERABLE_SECTIONS[$section];
        $content->loadMissing('productCategory');
        $prompts = $this->promptBuilder->buildSectionRegeneratePrompt($content, $section, $label, $instruction);

        $res = $this->aiManager->chatWithFallback(
            [
                ['role' => 'system', 'content' => $prompts['system']],
                ['role' => 'user', 'content' => $prompts['user']],
            ],
            $preferredProvider,
            null,
            ['temperature' => 0.8, 'max_tokens' => 600],
        );

        if (! ($res['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Gagal meregenerasi bagian naskah.')];
        }

        $parsed = $this->decodeJson((string) ($res['content'] ?? ''));
        $newText = trim((string) ($parsed['text'] ?? $res['content'] ?? ''));
        if ($newText === '') {
            return ['ok' => false, 'message' => 'AI mengembalikan teks kosong untuk bagian tersebut.'];
        }

        $content->{$section} = $newText;
        $content->script_text = $content->buildFullNarration();
        $content->revision_count = ((int) $content->revision_count) + 1;
        $content->save();

        $this->logger->log(
            'content_scripts',
            'section_regenerated',
            "Bagian '{$section}' pada naskah #{$content->id} diperbarui tanpa mengubah bagian lain.",
            'info',
            ['generated_content_id' => $content->id, 'section' => $section],
            GeneratedContent::class,
            $content->id,
        );

        return [
            'ok' => true,
            'content' => $content->fresh(),
            'section_value' => $newText,
            'message' => "Bagian {$label} berhasil diperbarui tanpa mengubah bagian naskah lainnya.",
        ];
    }

    public function wordBudgetForDuration(int $seconds): string
    {
        return match (true) {
            $seconds <= 15 => '30–40',
            $seconds <= 30 => '65–80',
            $seconds <= 45 => '100–115',
            $seconds <= 60 => '130–150',
            default => '190–220',
        };
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
