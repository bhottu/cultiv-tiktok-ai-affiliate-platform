<?php

namespace App\Modules\AffiliateProductResearch;

use App\Enums\ProductApprovalStatus;
use App\Models\AffiliateProduct;
use App\Models\User;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AICore\AIProviderManager;
use App\Modules\AICore\UniversalAffiliatePromptBuilder;

class ProductAnalysisService
{
    public function __construct(
        private readonly AIProviderManager $aiManager,
        private readonly ActivityLogger $logger,
        private readonly UniversalAffiliatePromptBuilder $promptBuilder,
    ) {}

    /**
     * Analyze a stored AffiliateProduct across ANY TikTok Shop category via AIProviderManager.
     *
     * @return array{ok: bool, product?: AffiliateProduct, provider_used?: string, fallback_used?: bool, message: string}
     */
    public function analyze(AffiliateProduct $product, User $actor, ?string $preferredProvider = null): array
    {
        if (! $this->aiManager->hasAnyConfiguredProvider()) {
            return [
                'ok' => false,
                'message' => 'Belum ada AI Provider (OpenRouter atau Google Gemini) yang dikonfigurasi. Atur API Key di menu AI Provider terlebih dahulu.',
            ];
        }

        $product->loadMissing('productCategory');
        $prompts = $this->promptBuilder->buildProductAnalysisPrompt($product);

        $res = $this->aiManager->chatWithFallback(
            [
                ['role' => 'system', 'content' => $prompts['system']],
                ['role' => 'user', 'content' => $prompts['user']],
            ],
            $preferredProvider,
            null,
            ['temperature' => 0.3, 'max_tokens' => 1600],
        );

        if (! ($res['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'Analisis produk oleh AI gagal.'),
            ];
        }

        $parsed = $this->decodeJsonSafely((string) ($res['content'] ?? ''));
        if (! $parsed) {
            return [
                'ok' => false,
                'message' => 'Respons AI tidak dalam format JSON terstruktur yang valid. Coba ulangi analisis.',
            ];
        }

        $factors = is_array($parsed['faktor_penilaian'] ?? null) ? $parsed['faktor_penilaian'] : [];
        $weights = [
            'relevansi_niche_skor' => 0.35,
            'nilai_edukasi_skor' => 0.25,
            'kemudahan_demonstrasi_skor' => 0.20,
            'kelengkapan_data_skor' => 0.20,
        ];

        $totalScore = 0.0;
        $breakdown = [
            'disclaimer' => 'Skor kurasi konten berbobot transparan (skala 0–100); bukan prediksi penjualan atau data resmi TikTok Shop.',
            'data_origin' => 'ai_analysis',
            'provider_used' => $res['provider_used'] ?? null,
            'model_used' => $res['model'] ?? null,
            'fallback_used' => (bool) ($res['fallback_used'] ?? false),
            'factors' => [],
        ];

        foreach ($weights as $k => $weight) {
            $rawVal = max(0, min(100, (float) ($factors[$k] ?? 0)));
            $weighted = round($rawVal * $weight, 2);
            $totalScore += $weighted;
            $breakdown['factors'][$k] = [
                'score' => $rawVal,
                'weight_percent' => (int) ($weight * 100),
                'weighted_points' => $weighted,
            ];
        }

        $finalScore = round($totalScore, 2);

        $product->update([
            'quality_score' => $finalScore,
            'analysis_result' => $parsed,
            'analysis_score_breakdown' => $breakdown,
            'analyzed_at' => now(),
            'approval_status' => $product->approval_status === ProductApprovalStatus::APPROVED->value
                ? ProductApprovalStatus::APPROVED->value
                : ProductApprovalStatus::ANALYZED->value,
        ]);

        $this->logger->log(
            'affiliate_analysis',
            'product_analyzed',
            "Produk '{$product->name}' ({$product->category}) selesai dianalisis AI ({$breakdown['provider_used']}) dengan skor kurasi {$finalScore}/100.",
            'info',
            [
                'affiliate_product_id' => $product->id,
                'category' => $product->category,
                'provider_used' => $breakdown['provider_used'],
                'fallback_used' => $breakdown['fallback_used'],
                'quality_score' => $finalScore,
            ],
            AffiliateProduct::class,
            $product->id,
            $actor->id,
        );

        return [
            'ok' => true,
            'product' => $product->fresh(),
            'provider_used' => $breakdown['provider_used'],
            'fallback_used' => $breakdown['fallback_used'],
            'message' => "Analisis AI selesai menggunakan {$breakdown['provider_used']} (Skor kurasi: {$finalScore}/100).",
        ];
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonSafely(string $raw): ?array
    {
        $clean = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $clean, $m)) {
            $clean = $m[1];
        } else {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $clean = substr($clean, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }
}
