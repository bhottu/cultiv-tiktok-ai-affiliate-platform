<?php

namespace App\Enums;

enum VideoTemplateType: string
{
    // 10 Template Universal Fase 3.1
    case PRODUCT_SHOWCASE = 'product_showcase';
    case PRODUCT_FEATURES = 'product_features';
    case PRODUCT_COMPARISON = 'product_comparison';
    case TOP_RECOMMENDATIONS = 'top_recommendations';
    case PROBLEM_SOLUTION = 'problem_solution';
    case PRODUCT_REVIEW = 'product_review';
    case EDUCATIONAL_EXPLAINER = 'educational_explainer';
    case UNBOXING_STYLE = 'unboxing_style';
    case DEAL_HIGHLIGHTS = 'deal_highlights';
    case STORYTELLING = 'storytelling';

    // Legacy aliases Fase 3 (tetap dipertahankan agar proyek lama 100% kompatibel)
    case PLANT_EDUCATION = 'plant_education';
    case PLANT_SHOWCASE = 'plant_showcase';

    public function compositionId(): string
    {
        return match ($this) {
            self::PRODUCT_SHOWCASE => 'ProductShowcase',
            self::PRODUCT_FEATURES => 'ProductFeatures',
            self::PRODUCT_COMPARISON => 'ProductComparison',
            self::TOP_RECOMMENDATIONS => 'TopRecommendations',
            self::PROBLEM_SOLUTION => 'ProblemSolution',
            self::PRODUCT_REVIEW => 'ProductReview',
            self::EDUCATIONAL_EXPLAINER => 'EducationalExplainer',
            self::UNBOXING_STYLE => 'UnboxingStyle',
            self::DEAL_HIGHLIGHTS => 'DealHighlights',
            self::STORYTELLING => 'Storytelling',
            self::PLANT_EDUCATION => 'PlantEducation',
            self::PLANT_SHOWCASE => 'PlantShowcase',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PRODUCT_SHOWCASE => 'Product Showcase — Sinematik & Visual Produk',
            self::PRODUCT_FEATURES => 'Product Features — Sorotan Fitur & Spesifikasi',
            self::PRODUCT_COMPARISON => 'Product Comparison — Perbandingan Objektif',
            self::TOP_RECOMMENDATIONS => 'Top Recommendations — Daftar Pilihan Bernomor',
            self::PROBLEM_SOLUTION => 'Problem & Solution — Hook Masalah ke Solusi',
            self::PRODUCT_REVIEW => 'Product Review — Ulasan Fakta & Catatan Jujur',
            self::EDUCATIONAL_EXPLAINER => 'Educational Explainer — Penjelasan Terstruktur',
            self::UNBOXING_STYLE => 'Unboxing Style — Kesan Pertama & Detail Paket',
            self::DEAL_HIGHLIGHTS => 'Deal Highlights — Nilai Produk & Info Harga',
            self::STORYTELLING => 'Storytelling — Narasi Bab Mengalir',
            self::PLANT_EDUCATION => 'Plant Education (Legacy Botani)',
            self::PLANT_SHOWCASE => 'Plant Showcase (Legacy Botani)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PRODUCT_SHOWCASE => 'Full-bleed visual produk dengan gerak Ken Burns halus, palet warna sesuai kategori, dan tipografi editorial.',
            self::PRODUCT_FEATURES => 'Kartu daftar fitur terstruktur dengan indikator nomor fitur dan penekanan spesifikasi utama.',
            self::PRODUCT_COMPARISON => 'Tata letak perbandingan jelas (Opsi A vs B atau Kelebihan vs Keterbatasan) untuk membantu keputusan beli.',
            self::TOP_RECOMMENDATIONS => 'Format listicle bernomor (#01, #02, #03) untuk rekomendasi produk berdasarkan kebutuhan spesifik.',
            self::PROBLEM_SOLUTION => 'Transisi berenergi dari Masalah → Penyebab → Solusi dengan aksen visual adaptif sesuai kategori produk.',
            self::PRODUCT_REVIEW => 'Framing fokus produk dengan badge kategori dinamis, poin fakta terverifikasi, dan catatan jujur.',
            self::EDUCATIONAL_EXPLAINER => 'Struktur edukasi bersih dengan progress bar atas, poin penjelasan teratur, dan highlight kata kunci.',
            self::UNBOXING_STYLE => 'Gaya first-impression bertahap (Paket → Isi → Detail → Kesan) dengan bingkai modern bersih.',
            self::DEAL_HIGHLIGHTS => 'Sorotan nilai tambah produk & ringkasan harga (hanya menampilkan harga jika data asli tersedia).',
            self::STORYTELLING => 'Aliran bab cerita hangat (Bab 01, 02, 03) dengan crossfade lembut dan tema visual sesuai kategori.',
            self::PLANT_EDUCATION => 'Template legacy Fase 3 khusus edukasi tanaman.',
            self::PLANT_SHOWCASE => 'Template legacy Fase 3 khusus showcase tanaman.',
        };
    }

    public function isLegacy(): bool
    {
        return in_array($this, [self::PLANT_EDUCATION, self::PLANT_SHOWCASE], true);
    }

    /** @return array<int, self> */
    public static function universalCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => ! $c->isLegacy()));
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
