# PHASE 3.1 — AUDIT IMPLEMENTASI SAAT INI (MENUJU UNIVERSAL AFFILIATE ENGINE)

**Proyek:** `affiliate-ai-agentic-tiktok` (`F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok`)  
**Akun Target Publikasi:** `@plant.coaction` (identitas akun publikasi, bukan batasan kategori produk)  
**Proyek Referensi Lama (Utuh / Tidak Diubah):** `F:\PROMT-ENGINEER\affiliate-ai-agentic`  
**Tanggal Audit:** 21 November 2026 (WIB)  
**Backup Pre-Phase 3.1:**
- Database PostgreSQL: `storage/backups/pre-phase3_1/affiliate_ai_tiktok.sql` (`267,357` bytes)
- Git Bundle: `storage/backups/pre-phase3_1/repo-pre-phase3_1.bundle` (`1,813,662` bytes, commit `7c499b9`)

---

## 1. Kondisi Baseline Sebelum Fase 3.1

Berdasarkan audit langsung terhadap laporan Fase 1–3, seluruh migration, model, service, controller, Blade view, Remotion studio, dan test suite:

- **Fondasi Stabil (Dipertahankan 100%):**
  - Laravel `12.73.0`, PHP `8.2.4`, PostgreSQL `18.6`, Node `v22.19.0`, FFmpeg `9.0.2`, Remotion `4.0.242`.
  - Multi-Provider AI (`OpenRouterProvider` + `GeminiProvider` + `AIProviderManager` dengan controlled fallback & encrypted API keys).
  - Multi-Provider TTS (`GeminiTTSProvider` + `GoogleCloudTTSProvider` + `AudioConverterService` FFmpeg PCM→WAV/MP3).
  - TikTok Official OAuth (`TikTokOAuthService`, `TikTokApiClient`) & Affiliate Product Approval 6 status (`discovered`, `analyzed`, `pending_approval`, `approved`, `rejected`, `archived`).
  - Render Pipeline Queue 4 tahap (`PrepareVideoCompositionJob` → `RenderVideoJob` → `FinalizeVideoJob` → `CleanupTemporaryAssetsJob`) + `ContentStudioPolicy` + 32 PHPUnit tests (361 assertions) lulus.

---

## 2. Inventarisasi Asumsi & Hardcoding Khusus Tanaman yang Ditemukan

| Area | Lokasi File | Temuan Spesifik Tanaman | Keputusan Fase 3.1 |
| :--- | :--- | :--- | :--- |
| **Kategori Produk** | `AIContentResearchService::CATEGORIES`, `TikTokShopAffiliateClient::PLANT_COACTION_NICHES` | Hardcoded 8 kategori konten tanaman & 10 niche tanaman; belum ada tabel kategori & subkategori dinamis | **Generalisasi & Perluas:** Buat tabel `product_categories` (hirarki `parent_id`), seed 15 kategori TikTok Shop + subkategori + `Plants & Gardening` + `Other / General`. Pertahankan konstanta lama sebagai alias fallback kompatibilitas. |
| **Data Produk Affiliate** | `affiliate_products`, `AffiliateDashboardController`, `affiliate/products.blade.php` | Tidak memiliki `brand`, `subcategory`, `description`, `features`, `specifications`, `target_audience`, `research_notes`, `data_source_type`, `verification_status`; belum ada form input manual saat TikTok Shop API belum aktif | **Perluas Non-Destruktif:** Tambah kolom opsional pada `affiliate_products` + UI tambah produk manual dengan pembeda jelas `official_api` vs `manual_input` vs `ai_analysis` vs `unverified`. |
| **AI Product Analysis** | `app/Modules/AffiliateProductResearch/ProductAnalysisService.php` | System prompt membatasi *"khusus untuk akun TikTok tanaman hias Plant Coaction (Anthurium, aroid, media tanam...)"* | **Generalisasi:** Pindahkan prompt ke `UniversalAffiliatePromptBuilder` agar menilai produk berdasarkan kategori aktual & data terverifikasi tanpa mengarang fakta. |
| **AI Content Research** | `AIContentResearchService.php`, `ContentResearchController.php`, `content-studio/research.blade.php` | Hanya menerima kategori tanaman, tidak mendukung analisis produk lengkap (15 poin output), tidak membedakan `first_hand` vs `research_based` | **Perluas:** Dukung riset produk universal (nama, kategori/subkategori, deskripsi, keunggulan, harga, komisi, URL, foto produk, info manual, mode pengalaman) menghasilkan analisis 15 poin + ide konten. |
| **AI Script Generator** | `AIScriptGeneratorService.php`, `ContentScriptController.php`, `content-studio/scripts.blade.php` | Prompt berorientasi tanaman; belum ada 14 format konten, 10 marketing angles, struktur narasi adaptif per kategori, dan pemisahan tegas `research_based` vs `first_hand` | **Perluas:** Modular `UniversalAffiliatePromptBuilder`, 14 format konten, 10 marketing angles, mode kejujuran review (`research_based` melarang klaim pengalaman pribadi palsu), tetap mempertahankan 6 bagian + regenerate section + rewrite + edit manual + Gemini TTS. |
| **Remotion Visual & Templates** | `remotion/src/components/SceneMedia.tsx`, `Storytelling.tsx`, `types.ts`, `Root.tsx`, `VideoTemplateType.php`, `PrepareVideoCompositionJob.php` | `SceneMedia.tsx` memakai warna hijau botani `#07130E`/`#065F4655` sebagai canvas default semua video; `Storytelling.tsx` menulis `· Cerita Tanaman`; hanya 5 template dengan 2 spesifik tanaman (`PlantEducation`, `PlantShowcase`) | **Generalisasi & Tambah 10 Template Universal:** Hapus paksaan visual botani dari default; buat sistem `CategoryVisualTheme` dinamis per kategori produk; hadirkan 10 template universal (`ProductShowcase`, `ProductFeatures`, `ProductComparison`, `TopRecommendations`, `ProblemSolution`, `ProductReview`, `EducationalExplainer`, `UnboxingStyle`, `DealHighlights`, `Storytelling`) sambil mempertahankan `PlantEducation` & `PlantShowcase` demi backward-compatibility konten lama. |
| **Visual Asset Manager** | `VisualAssetController.php`, `VisualAssetManagerService.php`, `content-studio/assets.blade.php` | Tag kategori hanya `plant_photo`, `product_photo`, `plant_video`, `greenhouse`, `bg_music`; belum ada filter/pengelompokan berdasarkan Produk, Campaign, dan Kategori | **Perluas Non-Destruktif:** Tambah `campaign_id`, `product_category_id`, `category_tag` universal + filter Produk/Campaign/Kategori/Jenis Media; pertahankan seluruh upload lama & keamanan private streaming. |
| **Content Library** | `ContentLibraryController.php`, `content-studio/library.blade.php` | Filter terbatas pada status, kategori string, dan teks; belum ada filter Produk, Content Format, Campaign, Video Template, Approval Status, dan inline edit caption/hashtag/disclosure saat review | **Perluas:** Tambah seluruh filter lintas dimensi + form edit caption/hashtag/disclosure langsung di panel review sambil menjaga seluruh konten lama 100% kompatibel. |

---

## 3. Rencana Arsitektur Non-Destruktif Fase 3.1

1. **Migration Tunggal Non-Destruktif (`2026_11_21_220001_add_universal_affiliate_engine_tables_and_columns.php`):**
   - Buat tabel `product_categories` (hirarki `parent_id`, `slug`, `name`, `theme_preset`, `accent_color`, `sort_order`, `is_active`).
   - Tambah kolom nullable/default-safe pada `affiliate_products`, `content_ideas`, `generated_contents`, `media_assets`, `video_projects`.
   - Tidak menghapus tabel atau kolom mana pun; seluruh data Fase 1–3 tetap utuh.
2. **Seeder Idempoten (`ProductCategorySeeder`):**
   - 15 kategori utama TikTok Shop (Electronics & Gadgets, Fashion & Apparel, Beauty & Skincare, Health & Personal Care, Home & Living, Kitchen & Household, Automotive & Accessories, Sports & Outdoor, Baby & Kids, Pet Supplies, Food & Beverage, Books & Education, Toys & Hobbies, Plants & Gardening, Other / General) + subkategori masing-masing + preset tema visual kategori.
3. **Modular Prompt Engine (`app/Modules/AICore/UniversalAffiliatePromptBuilder.php`):**
   - Terpusat, category-aware, evidence-based, memisahkan `research_based` vs `first_hand`, mendukung OpenRouter & Google Gemini + fallback.
4. **Remotion Universal Studio (`remotion/src/`):**
   - `theme.ts` (pemetaan tema visual kategori: tidak ada warna/elemen tanaman untuk elektronik/fashion/beauty/home/otomotif).
   - 10 template universal + 2 legacy alias (`PlantEducation`, `PlantShowcase`) teruji dengan `tsc --noEmit` & render nyata MP4 + `ffprobe`.
5. **Verifikasi Menyeluruh:**
   - PHPUnit (Fase 3.1 + seluruh regresi Fase 1–3), TypeScript check, Vite production build, Headless Chrome check seluruh rute, serta render MP4 nyata lintas kategori (Electronics, Beauty, Fashion, Home & Living, Plants) + Gemini TTS nyata.
