<?php

namespace App\Modules\AICore;

use App\Enums\ContentFormatType;
use App\Enums\MarketingAngleType;
use App\Models\AffiliateProduct;
use App\Models\GeneratedContent;

class UniversalAffiliatePromptBuilder
{
    /**
     * Category-specific narrative guidance (flexible baseline — AI adapts further to actual product).
     */
    public function categoryNarrativeGuidance(string $categoryName): string
    {
        $lower = strtolower($categoryName);

        return match (true) {
            str_contains($lower, 'electronic'), str_contains($lower, 'gadget') => 'Fokus kategori Electronics & Gadgets: jelaskan masalah pengguna, fitur utama yang nyata, spesifikasi penting (hanya dari data yang diberikan), kelebihan & keterbatasan praktis, serta CTA informatif.',
            str_contains($lower, 'fashion'), str_contains($lower, 'apparel') => 'Fokus kategori Fashion & Apparel: bahas model/desain, bahan (hanya jika diketahui), kenyamanan/kegunaan, inspirasi gaya pemakaian, kecocokan target pengguna, dan CTA natural.',
            str_contains($lower, 'beauty'), str_contains($lower, 'skincare') => 'Fokus kategori Beauty & Skincare: jelaskan fungsi produk berdasarkan informasi resmi/input, kandungan hanya jika tersedia & terverifikasi, cara pakai jika diketahui, serta cantumkan batasan klaim (tidak menjanjikan hasil medis/instan tanpa bukti).',
            str_contains($lower, 'health'), str_contains($lower, 'personal care') => 'Fokus kategori Health & Personal Care: jelaskan fungsi umum sesuai data resmi/input, cara pemakaian, dan WAJIB hindari klaim medis/penyembuhan yang tidak terverifikasi.',
            str_contains($lower, 'home'), str_contains($lower, 'living'), str_contains($lower, 'kitchen'), str_contains($lower, 'household') => 'Fokus kategori Home/Kitchen & Household: jelaskan fungsi harian, masalah rumah tangga yang dibantu, kepraktisan pemakaian, detail fitur penting, dan CTA natural.',
            str_contains($lower, 'automotive') => 'Fokus kategori Automotive & Accessories: bahas kecocokan kendaraan (jika diketahui), fungsi praktis berkendara/perawatan, fitur keamanan/material dari data tersedia, dan CTA.',
            str_contains($lower, 'sport'), str_contains($lower, 'outdoor') => 'Fokus kategori Sports & Outdoor: bahas skenario aktivitas, kenyamanan & daya tahan sesuai data tersedia, target pemula/aktif, dan keterbatasan produk.',
            str_contains($lower, 'pet') => 'Fokus kategori Pet Supplies: bahas kebutuhan hewan peliharaan, keamanan bahan/ukuran sesuai data tersedia, cara pakai, dan catatan kecocokan jenis/usia hewan.',
            str_contains($lower, 'food'), str_contains($lower, 'beverage') => 'Fokus kategori Food & Beverage: bahas profil rasa/varian hanya jika diketahui, komposisi/kemasan/cara penyajian dari data tersedia, hindari klaim rasa seolah sudah mencicipi bila belum mencoba.',
            str_contains($lower, 'plant'), str_contains($lower, 'garden'), str_contains($lower, 'tanaman') => 'Fokus kategori Plants & Gardening: bahas fungsi perawatan tanaman/media/alat, manfaat praktis untuk kesehatan tanaman atau hobi berkebun, hindari klaim botani/pestisida tak berdasar.',
            default => "Fokus kategori {$categoryName}: sesuaikan alur narasi dengan konteks produk, manfaat fungsional nyata, spesifikasi yang diketahui, hal yang perlu diperhatikan sebelum membeli, dan CTA natural.",
        };
    }

    /**
     * Strict honesty & experience mode policy block.
     *
     * @param  array<string, mixed>  $experienceNotes
     */
    public function experienceHonestyRules(string $experienceMode, array $experienceNotes = []): string
    {
        if ($experienceMode === 'first_hand') {
            $facts = [];
            if (! empty($experienceNotes['usage_experience'])) {
                $facts[] = '- Pengalaman pemakaian nyata: ' . trim((string) $experienceNotes['usage_experience']);
            }
            if (! empty($experienceNotes['felt_pros'])) {
                $facts[] = '- Kelebihan yang benar-benar dirasakan: ' . trim((string) $experienceNotes['felt_pros']);
            }
            if (! empty($experienceNotes['found_cons'])) {
                $facts[] = '- Kekurangan yang ditemukan: ' . trim((string) $experienceNotes['found_cons']);
            }
            if (! empty($experienceNotes['visible_results'])) {
                $facts[] = '- Hasil penggunaan yang dapat ditampilkan: ' . trim((string) $experienceNotes['visible_results']);
            }

            $detailBlock = ! empty($facts)
                ? implode("\n", $facts)
                : '- Pengguna menandai pernah mencoba produk, namun detail pengalaman spesifik belum diisi. JANGAN mengarang detail hasil/pengalaman tambahan di luar data produk.';

            return <<<RULES
MODE KEJUJURAN: FIRST-HAND PRODUCT EXPERIENCE (Pengguna pernah mencoba produk).
- Anda BOLEH menyusun narasi sudut pandang pengalaman HANYA berdasarkan fakta pengalaman yang benar-benar diberikan pengguna di bawah ini:
{$detailBlock}
- DILARANG menambahkan pengalaman pribadi, hasil uji coba, atau testimoni fiktif yang tidak disebutkan pengguna.
RULES;
        }

        return <<<'RULES'
MODE KEJUJURAN: RESEARCH-BASED PRODUCT CONTENT (Pengguna BELUM pernah mencoba produk ini).
- Gunakan pendekatan berbasis riset, informasi produk resmi/manual yang tersedia, fitur, fungsi, perbandingan data, atau hal yang perlu diperhatikan sebelum membeli.
- DILARANG KERAS menulis kalimat yang memberi kesan pengguna sudah mencoba/memakai produk ini!
- HINDARI mutlak frasa seperti: "Saya sudah mencoba produk ini", "Setelah saya gunakan", "Menurut pengalaman saya", "Saya pakai setiap hari", "Rasanya enak banget waktu saya coba", atau klaim pengalaman pribadi sejenis.
- Gunakan frasa jujur berbasis informasi, misalnya: "Berdasarkan informasi produk...", "Dari spesifikasinya...", "Hal yang menarik untuk dicek sebelum beli...", "Cocok dipertimbangkan jika kamu butuh...".
RULES;
    }

    /**
     * System & user prompt for Universal Affiliate Product Analysis (Phase 2/3.1).
     *
     * @return array{system: string, user: string}
     */
    public function buildProductAnalysisPrompt(AffiliateProduct $product): array
    {
        $categoryName = $product->productCategory?->name ?: ($product->category ?: 'Other / General');
        $productFacts = [
            'nama_produk' => $product->name,
            'kategori' => $categoryName,
            'subkategori' => $product->subcategory ?: 'Tidak tersedia',
            'brand' => $product->brand ?: 'Tidak tersedia',
            'deskripsi' => $product->description ?: 'Tidak tersedia',
            'fitur' => ! empty($product->features) ? $product->features : 'Tidak tersedia',
            'spesifikasi' => ! empty($product->specifications) ? $product->specifications : 'Tidak tersedia',
            'target_audiens' => $product->target_audience ?: 'Tidak tersedia',
            'seller' => $product->seller_name ?: 'Tidak tersedia',
            'harga' => $product->price !== null ? 'Rp' . number_format((float) $product->price, 0, ',', '.') : 'Tidak tersedia',
            'harga_promo' => $product->promo_price !== null ? 'Rp' . number_format((float) $product->promo_price, 0, ',', '.') : 'Tidak tersedia',
            'komisi_nominal' => $product->commission_amount !== null ? (string) $product->commission_amount : 'Tidak tersedia',
            'komisi_persen' => $product->commission_rate !== null ? (string) $product->commission_rate . '%' : 'Tidak tersedia',
            'rating' => $product->rating !== null ? (string) $product->rating : 'Tidak tersedia',
            'jumlah_ulasan' => $product->review_count !== null ? (string) $product->review_count : 'Tidak tersedia',
            'jumlah_penjualan' => $product->sales_count !== null ? (string) $product->sales_count : 'Tidak tersedia',
            'status_stok' => $product->stock_status ?: 'Tidak tersedia',
            'sumber_data' => $product->data_source_type ?: ($product->source_platform ?: 'Tidak tersedia'),
            'status_verifikasi' => $product->verification_status ?: 'unverified',
            'catatan_riset' => $product->research_notes ?: 'Tidak tersedia',
        ];

        $guidance = $this->categoryNarrativeGuidance($categoryName);

        $system = <<<PROMPT
Anda adalah analis produk TikTok Shop Affiliate lintas kategori untuk kreator Indonesia (akun publikasi @plant.coaction yang mempromosikan berbagai kategori produk TikTok Shop secara objektif dan transparan).
{$guidance}

ATURAN KETAT (WAJIB DIPATUHI):
1. DILARANG mengarang spesifikasi, bahan, kandungan, manfaat medis, harga, komisi, rating, atau klaim produk yang tidak ada dalam data input.
2. Jika suatu informasi bernilai "Tidak tersedia", wajib cantumkan pada daftar `informasi_belum_tersedia`.
3. Bedakan fakta dari data resmi/manual dengan hal yang masih perlu diverifikasi.
4. Skor penilaian (0-100) bersifat transparan sebagai indikator kelayakan kurasi konten dan BUKAN prediksi penjualan.
5. Kembalikan HANYA JSON valid dengan struktur persis berikut:
{
  "relevansi_niche": "Penjelasan kecocokan produk dengan kategori dan potensi pasar konten TikTok Indonesia",
  "kesesuaian_audiens": "Profil target pembeli/audiens yang paling relevan",
  "potensi_ide_konten": ["Ide konten 1", "Ide konten 2", "Ide konten 3"],
  "keunggulan": ["Keunggulan berbasis data tersedia"],
  "kekurangan_atau_risiko": ["Keterbatasan atau risiko klaim/data"],
  "sudut_edukasi": "Angle edukasi/informasi utama tanpa hard-selling berlebihan",
  "ide_demonstrasi": "Cara menampilkan/mendemonstrasikan produk secara objektif di video 9:16",
  "potensi_storytelling": "Sudut cerita natural yang sesuai kategori produk",
  "informasi_belum_tersedia": ["Daftar field yang masih kosong/perlu diverifikasi"],
  "rekomendasi_kelayakan": "layak | pertimbangkan | tidak_disarankan",
  "alasan_rekomendasi": "Ringkasan objektif berbasis data",
  "faktor_penilaian": {
    "relevansi_niche_skor": 0,
    "nilai_edukasi_skor": 0,
    "kemudahan_demonstrasi_skor": 0,
    "kelengkapan_data_skor": 0,
    "catatan_transparansi": "Skor ini adalah bobot kurasi konten internal, bukan prediksi penjualan."
  }
}
PROMPT;

        $user = "Analisis produk affiliate berikut secara objektif HANYA berdasarkan data aktual ini:\n"
            . json_encode($productFacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return ['system' => $system, 'user' => $user];
    }

    /**
     * System & user prompt for Universal AI Product Research (15-point analysis + content ideas).
     *
     * @param  array<string, mixed>  $input
     * @return array{system: string, user: string}
     */
    public function buildProductResearchPrompt(array $input): array
    {
        $categoryName = (string) ($input['category_name'] ?? 'Other / General');
        $subcategory = trim((string) ($input['subcategory'] ?? ''));
        $experienceMode = (string) ($input['experience_mode'] ?? 'research_based');
        $experienceNotes = is_array($input['experience_notes'] ?? null) ? $input['experience_notes'] : [];
        $count = max(1, min(8, (int) ($input['count'] ?? 4)));

        $guidance = $this->categoryNarrativeGuidance($categoryName);
        $honesty = $this->experienceHonestyRules($experienceMode, $experienceNotes);

        $system = <<<PROMPT
Anda adalah Universal AI Affiliate Product Researcher & TikTok Content Strategist untuk pasar TikTok Indonesia.
Akun publikasi: @plant.coaction (dapat mempromosikan berbagai kategori produk TikTok Shop: Electronics, Fashion, Beauty, Home, Kitchen, Automotive, Sports, Baby, Pet, Food, Plants, dsb.).

{$guidance}

{$honesty}

ATURAN ANTI-HALUSINASI (WAJIB):
1. DILARANG mengarang spesifikasi, bahan, harga, komisi, garansi, sertifikasi (BPOM/Halal/SNI), atau hasil pemakaian yang tidak tercantum di input pengguna.
2. Jika harga, spesifikasi, ukuran, bahan, atau foto tidak diberikan, sebutkan secara jujur pada `unknown_information` bahwa data tersebut belum diketahui/perlu diverifikasi.
3. Jangan menganggap URL produk dapat diakses secara otomatis; analisis HANYA berdasarkan informasi tertulis yang diberikan pengguna.
4. Hindari klikbait kosong atau promosi berulang tanpa dasar seperti "wajib beli", "paling bagus", atau "termurah".
5. Kembalikan HANYA JSON valid dengan struktur persis:
{
  "analysis": {
    "product_summary": "Ringkasan produk objektif berdasarkan data input",
    "functions_and_uses": ["Fungsi / kegunaan utama"],
    "target_users": ["Profil target pengguna paling cocok"],
    "problems_solved": ["Masalah nyata yang mungkin dibantu produk ini"],
    "main_advantages": ["Keunggulan utama berbasis data tersedia"],
    "limitations_to_note": ["Kekurangan, keterbatasan, atau hal yang perlu diperhatikan"],
    "unique_selling_point": "USP paling relevan berdasarkan fakta tersedia",
    "selling_angles": ["problem_solving", "feature_focused"],
    "recommended_video_formats": ["product_review", "problem_solution"],
    "recommended_narration_styles": ["edukatif", "santai"],
    "recommended_ctas": ["Contoh CTA natural tanpa hard-selling"],
    "unknown_information": ["Informasi penting yang belum tersedia pada input"],
    "claims_needing_verification": ["Klaim/detail yang wajib dicek ulang pemilik akun sebelum publikasi"]
  },
  "ideas": [
    {
      "title": "Judul ide video TikTok yang spesifik dan menarik",
      "hook_concept": "Kalimat hook 3 detik pertama (jujur sesuai mode pengalaman)",
      "concept_summary": "Ringkasan konsep video 2-3 kalimat",
      "content_format": "product_review",
      "marketing_angle": "problem_solving",
      "narration_style": "edukatif",
      "visual_recommendation": "Saran shot visual sesuai kategori produk (bukan tema tanaman kecuali produk kategori Plants)",
      "estimated_duration_seconds": 30,
      "cta_recommendation": "CTA natural dan relevan",
      "needs_fact_verification": false
    }
  ]
}
Nilai `content_format` pilih dari: product_review, product_introduction, unboxing, problem_solution, product_comparison, top_recommendations, educational_content, product_showcase, features_benefits, deal_highlights, buying_guide, storytelling, faq, tutorial.
Nilai `marketing_angle` pilih dari: problem_solving, feature_focused, price_value, comparison, convenience, educational, curiosity, gift_recommendation, lifestyle, practical_demonstration.
PROMPT;

        $facts = [
            'nama_produk' => $input['product_name'] ?: 'Tidak spesifik (riset topik kategori)',
            'kategori' => $categoryName,
            'subkategori' => $subcategory ?: 'Tidak tersedia',
            'deskripsi_produk' => $input['product_description'] ?: 'Tidak tersedia',
            'keunggulan_input' => $input['product_advantages'] ?: 'Tidak tersedia',
            'harga' => $input['price'] !== null && $input['price'] !== '' ? 'Rp' . number_format((float) $input['price'], 0, ',', '.') : 'Tidak tersedia',
            'informasi_komisi' => $input['commission_info'] ?: 'Tidak tersedia',
            'target_audiens' => $input['target_audience'] ?: 'Pengguna TikTok Indonesia sesuai kategori',
            'tujuan_konten' => $input['content_goal'] ?: 'Edukasi & konversi affiliate jujur',
            'url_produk' => $input['product_url'] ?: 'Tidak tersedia (jangan diasumsikan dapat di-scrape)',
            'foto_produk_tersedia' => ! empty($input['has_product_image']) ? 'Ya (diunggah manual oleh pengguna)' : 'Tidak tersedia',
            'informasi_tambahan_manual' => $input['manual_notes'] ?: 'Tidak ada',
            'jumlah_ide_diminta' => $count,
        ];

        $user = "Lakukan riset produk dan hasilkan {$count} ide konten TikTok yang beragam (tidak berulang) HANYA berdasarkan data berikut:\n"
            . json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return ['system' => $system, 'user' => $user];
    }

    /**
     * System & user prompt for Universal AI Script Generation (14 formats, 10 angles, category-adaptive, honesty-enforced).
     *
     * @param  array<string, mixed>  $input
     * @return array{system: string, user: string}
     */
    public function buildScriptGenerationPrompt(array $input): array
    {
        $categoryName = (string) ($input['category_name'] ?? 'Other / General');
        $subcategory = trim((string) ($input['subcategory'] ?? ''));
        $formatValue = (string) ($input['content_format'] ?? ContentFormatType::PRODUCT_REVIEW->value);
        $formatLabel = ContentFormatType::tryFrom($formatValue)?->label() ?? $formatValue;
        $angleValue = (string) ($input['marketing_angle'] ?? MarketingAngleType::AUTO->value);
        $angleLabel = MarketingAngleType::tryFrom($angleValue)?->label() ?? $angleValue;
        $durationSeconds = (int) ($input['duration_seconds'] ?? 30);
        $wordBudget = (string) ($input['word_budget'] ?? '65–80');
        $styleLabel = (string) ($input['style_label'] ?? 'Edukatif');
        $sceneCount = $durationSeconds <= 15 ? 3 : ($durationSeconds <= 30 ? 4 : ($durationSeconds <= 45 ? 5 : 6));
        $experienceMode = (string) ($input['experience_mode'] ?? 'research_based');
        $experienceNotes = is_array($input['experience_notes'] ?? null) ? $input['experience_notes'] : [];

        $guidance = $this->categoryNarrativeGuidance($categoryName);
        $honesty = $this->experienceHonestyRules($experienceMode, $experienceNotes);

        $system = <<<PROMPT
Anda adalah Universal TikTok Affiliate Scriptwriter profesional berbahasa Indonesia untuk video pendek vertikal 9:16.
Akun publikasi: @plant.coaction (namun konten 100% mengikuti kategori & konteks produk yang sedang dibuat — JANGAN memaksakan topik tanaman untuk kategori non-tanaman).

{$guidance}

{$honesty}

ATURAN PENULISAN & KEPATUHAN:
1. Bahasa Indonesia natural, mengalir seperti percakapan kreator TikTok Indonesia, tidak kaku/robotik, tidak berulang.
2. Hindari klaim berlebihan tanpa dasar seperti "wajib beli", "paling bagus sedunia", "termurah", atau klaim medis/hasil instan tanpa bukti.
3. DILARANG mengarang harga, spesifikasi, garansi, sertifikasi, atau fitur yang tidak ada pada data input.
4. Jika ini konten produk affiliate, isi `disclosure_text` dengan disclosure singkat & jelas (mis. "Tautan produk tersedia di keranjang kuning (affiliate).").
5. Panjang total narasi lisan (hook + opening + main_narration + supporting_explanation + closing + cta): sekitar {$wordBudget} kata agar pas untuk durasi {$durationSeconds} detik (~2.3 kata/detik).
6. Visual scene suggestions HARUS relevan dengan kategori produk ({$categoryName}) — jangan menyarankan visual daun/tanaman kecuali kategori memang Plants & Gardening.
7. Kembalikan HANYA JSON valid dengan struktur persis:
{
  "title": "Judul video final yang spesifik",
  "selected_angle": "angle yang dipakai (mis. problem_solving)",
  "hook": "Hook 3 detik pertama yang menarik perhatian",
  "opening": "Kalimat pembuka pengantar konteks",
  "main_narration": "Inti narasi utama sesuai format konten dan kategori produk",
  "supporting_explanation": "Penjelasan pendukung, fakta/keterbatasan, atau perbandingan objektif",
  "closing": "Penutup ringkas yang mengikat pesan utama",
  "cta": "Ajakan interaksi natural tanpa hard-selling agresif",
  "caption": "Caption TikTok siap pakai dalam Bahasa Indonesia",
  "hashtags": ["#tiktokshop", "#reviewproduk", "#rekomendasi"],
  "disclosure_text": "Disclosure affiliate singkat (kosongkan jika bukan konten promosi produk)",
  "suggested_visual_scenes": [
    {
      "scene_order": 1,
      "title": "Hook",
      "narration_segment": "Potongan narasi scene 1",
      "overlay_text": "Teks layar ringkas maks 10 kata",
      "visual_recommendation": "Saran shot visual sesuai kategori produk",
      "highlight_keywords": ["kata1", "kata2"]
    }
  ]
}
Pastikan `suggested_visual_scenes` memiliki {$sceneCount} scene berurutan.
PROMPT;

        $lines = array_filter([
            'Judul / Topik: ' . ($input['title'] ?? ''),
            "Kategori Produk: {$categoryName}" . ($subcategory !== '' ? " — Subkategori: {$subcategory}" : ''),
            "Format Konten: {$formatLabel}",
            "Marketing Angle: {$angleLabel}",
            "Gaya Narasi: {$styleLabel}",
            "Target Durasi: {$durationSeconds} detik",
            'Target Audiens: ' . ($input['target_audience'] ?? 'Audiens TikTok Indonesia'),
            ! empty($input['idea_context']) ? "Konteks Ide / Riset:\n" . $input['idea_context'] : null,
            ! empty($input['product_context']) ? "Data Produk Terverifikasi/Input:\n" . $input['product_context'] : null,
        ]);

        return ['system' => $system, 'user' => implode("\n", $lines)];
    }

    /**
     * Prompt for regenerating a single script section without modifying other sections.
     *
     * @return array{system: string, user: string}
     */
    public function buildSectionRegeneratePrompt(
        GeneratedContent $content,
        string $section,
        string $sectionLabel,
        ?string $instruction = null,
    ): array {
        $categoryName = $content->productCategory?->name ?: ($content->category ?: 'Other / General');
        $honesty = $this->experienceHonestyRules(
            (string) ($content->experience_mode ?: 'research_based'),
            (array) ($content->experience_notes ?? []),
        );

        $system = "Anda adalah editor naskah TikTok Affiliate lintas kategori. Tulis ulang HANYA satu bagian yang diminta dalam Bahasa Indonesia natural sesuai kategori {$categoryName} tanpa mengubah bagian lain.\n{$honesty}\nKembalikan JSON valid: {\"text\": \"...\"}";

        $user = implode("\n", array_filter([
            "Judul video: {$content->title}",
            "Kategori: {$categoryName} · Format: " . ($content->content_format ?: 'product_review') . " · Gaya: {$content->narration_style} · Durasi target: {$content->target_duration_seconds} detik",
            "Bagian yang ditulis ulang: {$sectionLabel} ({$section})",
            'Isi saat ini: ' . (string) ($content->{$section} ?? ''),
            "Konteks narasi lengkap:\n" . $content->buildFullNarration(),
            $instruction ? "Instruksi tambahan pengguna: {$instruction}" : 'Buat alternatif yang lebih segar, tajam, jujur, dan natural.',
        ]));

        return ['system' => $system, 'user' => $user];
    }
}
