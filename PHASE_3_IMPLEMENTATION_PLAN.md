# PHASE 3 IMPLEMENTATION PLAN — AI Content Generator & Remotion Video Producer

> **Proyek:** `F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok` (Plant Coaction Affiliate AI)  
> **Brand:** Plant Coaction · *"connecting plants, people & animals"*  
> **Tanggal Rencana:** 2026-11-21 (WIB / `Asia/Jakarta`)  
> **Proyek Lama (`affiliate-ai-agentic`):** Tetap utuh (read-only reference).

---

## 1. Hasil Audit Fondasi yang Sudah Ada
- **Framework & Database:** Laravel 12.73.0 (PHP 8.2.4), PostgreSQL 18 (`affiliate_ai_tiktok` & `affiliate_ai_tiktok_test`), timezone `Asia/Jakarta`, antarmuka Bahasa Indonesia.
- **AI Providers:** `AIProviderManager` aktif dengan `OpenRouterProvider` & `GeminiProvider` (terdeteksi terkonfigurasi di database lokal) + controlled fallback.
- **TTS Providers:** `TextToSpeechInterface`, `TTSProviderManager`, `GeminiTTSProvider` (berbagi API Key terenkripsi dari `GeminiProvider`, output Master WAV 24 kHz + Preview MP3 via `AudioConverterService` + FFmpeg), dan `GoogleCloudTTSProvider` (tetap dipertahankan).
- **Video & Media Binary:** Node.js v22.19.0, npm 10.9.3, FFmpeg 9.0.2, FFprobe, Google Chrome/Edge tersedia; direktori `remotion/` belum ada di proyek baru → akan diinstal Remotion v4 penuh.
- **Backup Pra-Fase 3:** `storage/backups/pre-phase3/affiliate_ai_tiktok.sql` (`219.066` byte) & `repo-pre-phase3.bundle` (`1.116.091` byte).

---

## 2. Alur Data End-to-End (Ide → MP4 Siap Upload TikTok)
1. **AI Content Research (`/dashboard/content/research`):** `AIProviderManager` menghasilkan beberapa ide spesifik tanaman hias / Anthurium / aroid / affiliate → disimpan ke `content_ideas` (`status: idea`).
2. **AI Script Generator (`/dashboard/content/scripts`):** Memilih ide (opsional tautkan produk affiliate `approved` dari Fase 2) → menghasilkan naskah terstruktur (`hook`, `opening`, `main_narration`, `supporting_explanation`, `closing`, `cta`, `caption`, `hashtags`, `suggested_visual_scenes`) → edit manual, *Regenerate Hook*, *Regenerate Section* (tanpa menimpa bagian lain), *Rewrite Script*, *Save Draft* (`generated_contents`).
3. **Gemini TTS Voiceover:** Mengirim narasi naskah ke `TTSProviderManager` (`GeminiTTSProvider`) → konversi FFmpeg PCM → Master WAV (`storage/app/tts/masters/`) → simpan `MediaAsset` + durasi aktual (`ffprobe`) → status `voiceover_ready`.
4. **Visual Asset Manager (`/dashboard/content/assets`):** Upload aman (validasi server MIME & ukuran: JPG/JPEG/PNG/WEBP/MP4/MOV) ke storage privat `storage/app/media/uploads/{user_id}/` dengan inspeksi dimensi/durasi (`getimagesize`/`ffprobe`), rekomendasi visual AI per scene, dan urutan penggunaan aset.
5. **Subtitle Generator (`SubtitleGeneratorService`):** Segmentasi frasa natural berbahasa Indonesia, highlight kata penting, estimasi timing berdasarkan durasi audio aktual (ditandai jelas `timing_source: estimated` dan dapat diedit manual), tersimpan di `subtitle_segments` dan diekspor ke props Remotion + `.srt`.
6. **Video Preview & Editor (`/dashboard/content/editor/{id}`):** Layout desktop compact: preview 9:16 (video ter-render / stage preview), daftar scene & urutan, editor teks overlay & durasi scene, editor subtitle (teks/timing/posisi/ukuran/on-off), ganti visual, ganti template (5 template berbeda), voiceover & opsi background music dengan ducking, serta tombol **Render Ulang**.
7. **Remotion Video Producer (`remotion/` + `RemotionVideoRenderer`):**
   - 5 Komposisi Template Unik (`1080×1920`, 30 FPS, H.264 + AAC, TikTok safe area):
     1. `PlantEducation` (clean editorial edukasi, kartu info terstruktur, highlight kata kunci, progress bar atas)
     2. `ProductReview` (framing produk fokus, badge kategori & poin fakta terverifikasi, lower-third CTA natural)
     3. `PlantShowcase` (cinematic full-bleed Ken Burns smooth pan/zoom, tipografi minimalis elegan, vignette halus)
     4. `ProblemSolution` (hook kontras masalah → indikator penyebab → transisi solusi → CTA penutup)
     5. `Storytelling` (aliran bab cerita hangat, transisi crossfade halus, tipografi editorial emosional)
   - Menggunakan browser Chrome lokal (`--browser-executable`) + concurrency terbatas (`REMOTION_CONCURRENCY=2`) agar aman di RAM 16 GB Windows.
8. **Rendering Queue Pipeline (`app/Jobs/Video/`):**
   - `PrepareVideoCompositionJob` → validasi konten, aset, voiceover, staging file ke direktori render sementara, normalisasi & ducking audio opsional via FFmpeg → dispatch `RenderVideoJob`.
   - `RenderVideoJob` → menjalankan CLI Remotion dengan monitoring progress ke `video_render_jobs` (`preparing` → `rendering` → `completed`/`failed`/`cancelled`), anti-duplikasi via `idempotency_key` & cache lock concurrency.
   - `FinalizeVideoJob` → validasi file MP4 via `ffprobe` (resolusi, FPS, codec H.264/AAC, durasi), ekstrak thumbnail poster, simpan metadata, update status konten menjadi `video_ready`.
   - `CleanupTemporaryAssetsJob` → hanya menghapus folder staging sementara pasca render tanpa pernah menyentuh aset asli pengguna.
9. **Content Library & Approval (`/dashboard/content`):** Pusat seluruh konten (filter status 10 tahap & kategori), pemutar MP4, pemeriksaan caption & hashtag, ekspor/unduh MP4 privat terotorisasi, serta persetujuan/penolakan dengan catatan revisi (`content_approval_logs`). Tanpa auto-publish TikTok di Fase 3.

---

## 3. Strategi Database Non-Destruktif
- Migration tunggal non-destruktif `2026_11_21_100001_extend_content_and_video_studio_tables_for_phase3.php`:
  - Memperluas kolom pada `content_ideas`, `generated_contents`, `media_assets`, `video_projects`, `video_render_jobs`.
  - Menambahkan 3 tabel baru: `video_scenes`, `subtitle_segments`, `content_approval_logs`.
- Seluruh akses dilindungi Policy kepemilikan (`ContentStudioPolicy`) agar data & file antar pengguna terisolasi penuh (`403 Forbidden`).

---

## 4. Risiko Teknis & Mitigasi
| Risiko | Mitigasi |
|---|---|
| Beban RAM/CPU saat render Remotion paralel | Batasi concurrency Remotion (`--concurrency=2`) dan kunci queue render dengan `Cache::lock` agar hanya 1 render berat berjalan bersamaan. |
| Aset gambar/video lokal privat tidak dapat dibaca headless Chrome via `file://` | `PrepareVideoCompositionJob` menyalin aset tervalidasi ke folder staging `remotion/public/_staging/{job_id}/` (akses via `staticFile()`), lalu dibersihkan oleh `CleanupTemporaryAssetsJob` tanpa menghapus aset asli pengguna. |
| Format MOV/video upload tidak kompatibel H.264 browser | FFmpeg transcode otomatis video input non-H.264 ke MP4 H.264 ringan pada tahap staging. |
| Kegagalan TTS / AI rate limit | Tidak pernah menghapus naskah/aset yang sudah ada; tampilkan error jelas & simpan draft utuh. |
