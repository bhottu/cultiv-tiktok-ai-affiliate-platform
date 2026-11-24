# PHASE 3.1 TEST REPORT — UNIVERSAL AFFILIATE ENGINE

**Tanggal Pengujian:** 21 November 2026 (WIB)  
**Database Test Terisolasi:** `affiliate_ai_tiktok_test` (PostgreSQL 18.6)  
**Database Lokal Render Nyata:** `affiliate_ai_tiktok` (PostgreSQL 18.6)

Laporan ini memisahkan secara jujur dan eksplisit antara **Automated Test**, **Integration / Build Check**, **Live API & Real Render Test**, dan **Blocked External Integrations**.

---

## 1. Automated Tests (PHPUnit — `php artisan test`)

**Hasil:** **PASS — 37 passed (435 assertions), 0 failed, durasi 162.21s**

| Test Suite | Jumlah Test | Cakupan Utama | Status |
| :--- | :---: | :--- | :---: |
| `Tests\Unit\ExampleTest` & `Tests\Feature\ExampleTest` | 2 | Booting dasar & redirect root | PASS |
| `Tests\Feature\Phase1FoundationTest` | 7 | PostgreSQL, timezone WIB, Auth, OpenRouter, Google Cloud TTS, Queue HealthCheck | PASS |
| `Tests\Feature\GeminiProviderTest` | 5 | Enkripsi key, masking, model discovery, error handling, controlled multi-provider fallback | PASS |
| `Tests\Feature\Phase2TikTokAndAffiliateTest` | 6 | TikTok OAuth state/callback/refresh/disconnect, unavailable states, product approval & policy | PASS |
| `Tests\Feature\GeminiTtsProviderTest` | 7 | Shared Gemini key, validasi, 401/429/5xx/timeout, decode PCM + FFmpeg konversi nyata | PASS |
| `Tests\Feature\Phase3ContentAndVideoStudioTest` | 5 | Research, Script + single-section regenerate, upload MIME/403, Gemini TTS, Editor/Queue/Approval | PASS |
| **`Tests\Feature\Phase31UniversalAffiliateTest`** | **5** | **15 kategori + subkategori + tambah dinamis + tema non-tanaman; produk manual & analisis AI universal; riset terbatas + `research_based` anti-klaim palsu; 14 format + 10 angle + `first_hand` vs `research_based`; pengelompokan aset + 7 filter Content Library + inline edit metadata + kompatibilitas konten tanaman lama** | **PASS** |

---

## 2. Static, Build & Headless Browser Integration Checks

| Pengujian | Perintah / Target | Hasil |
| :--- | :--- | :---: |
| Remotion TypeScript Check | `npx tsc --noEmit` (seluruh 12 komposisi: 10 universal + 2 legacy) | **PASS (0 errors)** |
| Vite Production Build | `npm run build` (`app-ycXsYSEy.css` 45.76 kB, `app-CTpZwmd8.js` 115.14 kB) | **PASS (5.27s)** |
| Headless Chrome CDP Check (12 Rute Terautentikasi) | `/dashboard`, `/dashboard/content`, `/dashboard/content?inspect=1`, `/dashboard/content/research`, `/dashboard/content/scripts`, `/dashboard/content/assets`, `/dashboard/content/editor/2`, `/dashboard/tiktok`, `/dashboard/affiliate`, `/dashboard/affiliate/products`, `/settings/ai-providers`, `/settings/tts` | **PASS (12/12 HTTP 200, `hasMain=true`, 0 console errors, 0 page errors)** |
| Audit Keamanan Log | Pemindaian pola `AIzaSy` / `sk-or-v1-` di `storage/logs/laravel.log` | **PASS (`SECRET_LEAK_DETECTED=False`)** |

---

## 3. Live API & Real Remotion Video Rendering Verification (10 Template Universal)

### A. Live Google Gemini TTS Test
- **Provider:** `google_gemini_tts` (`gemini-2.5-flash-preview-tts`, voice `Kore`, API key terenkripsi lokal).
- **Teks Narasi (Kategori Electronics, mode `research_based`):** Narasi Bahasa Indonesia objektif 30 kata tentang pemilihan earbuds nirkabel.
- **Hasil:** **PASS (LIVE)** — Master WAV `tts/masters/gemini-c3f23bee-9093-4c1e-a06e-10a9858b700f.wav` terbentuk dengan durasi nyata **`13.81 detik`**.

### B. Real MP4 Rendering — Seluruh 10 Template Universal Lintas Kategori (`ffprobe` Verified)

| # | Template Remotion | Kategori Produk | Tema Visual | Non-Tanaman OK | Resolusi & FPS | Codec | Durasi (`ffprobe`) | Ukuran MP4 | VO Utuh | Thumb & Staging Bersih |
| :---: | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 1 | `ProductShowcase` | Electronics & Gadgets | `tech_dark` | Ya | `1080×1920` · `30` | `h264`/`aac` | **`14.14s`** (VO `13.81s`) | `2.04 MB` | **Ya** | Ya / Ya |
| 2 | `ProductFeatures` | Electronics & Gadgets | `tech_dark` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `0.94 MB` | Ya | Ya / Ya |
| 3 | `ProductComparison` | Beauty & Skincare | `beauty_soft` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `1.02 MB` | Ya | Ya / Ya |
| 4 | `TopRecommendations` | Home & Living | `home_warm` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `1.11 MB` | Ya | Ya / Ya |
| 5 | `ProblemSolution` | Kitchen & Household | `home_warm` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `1.16 MB` | Ya | Ya / Ya |
| 6 | `ProductReview` | Fashion & Apparel | `fashion_editorial` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `1.00 MB` | Ya | Ya / Ya |
| 7 | `EducationalExplainer` | Beauty & Skincare | `beauty_soft` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `0.93 MB` | Ya | Ya / Ya |
| 8 | `UnboxingStyle` | Automotive & Accessories | `auto_carbon` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `1.07 MB` | Ya | Ya / Ya |
| 9 | `DealHighlights` | Electronics & Gadgets | `tech_dark` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `1.24 MB` | Ya | Ya / Ya |
| 10 | `Storytelling` | Fashion & Apparel | `fashion_editorial` | Ya | `1080×1920` · `30` | `h264`/`aac` | `4.54s` | `1.29 MB` | Ya | Ya / Ya |

**Verifikasi Pasca-Cleanup:** `all_completed = true`, `all_non_plant_themes_ok = true`, `all_staging_cleaned = true`, `all_thumbs_ok = true`, `vo_exists_after_cleanup = true`.

---

## 4. Integrasi Live yang Masih BLOCKED (Bukan PASS)

- **TikTok Official OAuth Live Flow:** `BLOCKED — WAITING FOR CREDENTIALS` (`TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET` produksi).
- **TikTok Shop Affiliate API Live Search:** `BLOCKED — WAITING FOR API APPROVAL & CREDENTIALS` (`TIKTOK_SHOP_APP_KEY`, `TIKTOK_SHOP_APP_SECRET`, `TIKTOK_SHOP_ACCESS_TOKEN`).
- **Manual Playback Visual/Audio oleh Pengguna:** Disarankan pengguna memutar 1–2 MP4 sampel langsung dari halaman `/dashboard/content` (`Review`) untuk inspeksi visual akhir.
