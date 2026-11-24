# PHASE 3 — PANDUAN VIDEO RENDERING REMOTION

Proyek: **Plant Coaction — Affiliate AI Agentic TikTok** (`@plant.coaction`)  
Lokasi Proyek: `F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok`

---

## 1. Spesifikasi Output Video Standar

Seluruh video yang dirender melalui Remotion Studio mematuhi standar vertikal TikTok Indonesia:

| Parameter | Nilai Standar |
| :--- | :--- |
| Rasio Aspek | `9:16` (Vertikal) |
| Resolusi | `1080 × 1920` piksel |
| Frame Rate | `30 FPS` |
| Codec Video | `H.264` (`libx264`, `yuv420p`) |
| Codec Audio | `AAC` (`48kHz / 24kHz` dinormalisasi) |
| Format File | `.mp4` |
| Safe-Area Subtitle | `bottom: 320px`, `right: 132px` (aman dari tombol like/komen/caption TikTok) |

---

## 2. Arsitektur 5 Template Video Remotion (`remotion/`)

Studio Remotion terinstal lokal di folder `remotion/` menggunakan `remotion@4.0.242` dan `@remotion/cli@4.0.242`. Tersedia **5 template berbeda secara visual, tata letak, tipografi, dan transisi**:

1. **`PlantEducation`** (`plant_education`)
   - Fokus edukasi tanaman bersih: progress bar atas, badge kategori, kartu judul/fakta botani terstruktur, highlight kata kunci, serta brand watermark `Plant Coaction`.
2. **`ProductReview`** (`product_review`)
   - Fokus review produk pendukung tanaman/affiliate: framing inset berbayangan, badge kategori produk, sorotan poin fakta terverifikasi, dan lower-third CTA natural.
3. **`PlantShowcase`** (`plant_showcase`)
   - Full-bleed sinematik untuk pamer koleksi aroid/Anthurium: gerak Ken Burns halus (`zoom_in`, `zoom_out`, `pan_left`, `pan_right`), vignette lembut, dan tipografi editorial minimalis.
4. **`ProblemSolution`** (`problem_solution`)
   - Hook kuat dengan transisi bertahap `MASALAH` → `PENYEBAB` → `SOLUSI` menggunakan badge warna kontras dan tipografi kinetik berenergi.
5. **`Storytelling`** (`storytelling`)
   - Aliran bab cerita hangat (indeks `01`, `02`, `03`, dst.) dengan crossfade lembut, tone warna hangat editorial, dan penutup emosional `connecting plants, people & animals`.

---

## 3. Pipeline Render Berbasis Queue (4 Tahap)

Rendering video **tidak dijalankan langsung pada HTTP request** agar browser tidak timeout. Alur asinkron berjalan melalui Laravel Queue (`database` connection):

1. **`PrepareVideoCompositionJob`**
   - Mengambil aset voiceover dari storage privat, menormalisasi loudness via FFmpeg `loudnorm=I=-16:TP=-1.5:LRA=11`.
   - Menyiapkan musik latar opsional (jika dipilih pengguna).
   - Menyalin foto/klip video tiap scene ke direktori staging terisolasi `remotion/public/_staging/job-{id}/` (mengonversi `.mov` ke H.264 `.mp4` agar kompatibel penuh dengan Chromium headless).
   - Menyusun `composition_props` JSON dan mendispatch `RenderVideoJob`.
2. **`RenderVideoJob`**
   - Menggunakan concurrency lock `Cache::lock('remotion_active_render_lock', 660)` agar hanya 1 render aktif berjalan bersamaan (mencegah lonjakan CPU/RAM).
   - Mengeksekusi `RemotionVideoRenderer` melalui `node remotion/node_modules/@remotion/cli/remotion-cli.js render` dengan auto-detect browser Chrome/Edge lokal (`--browser-executable`), `--concurrency=2`, `--codec=h264`, `--audio-codec=aac`.
   - Memperbarui `progress_percent` & `stage` secara bertahap (dipantau live oleh Video Editor via `/dashboard/content/editor/{project}/render-status`).
   - Output deterministik: `storage/app/private/renders/videos/project-{projectId}-job-{renderJobId}.mp4` (retry menimpa file yang sama, tanpa file sampah duplikat).
3. **`FinalizeVideoJob`**
   - Memverifikasi MP4 hasil render dengan `ffprobe` (`width`, `height`, `fps`, `video_codec`, `audio_codec`, `duration_seconds`).
   - Mengekstrak thumbnail JPG otomatis menggunakan FFmpeg ke `renders/thumbnails/project-{id}-job-{id}.jpg`.
   - Memperbarui status `VideoProject` ke `completed` dan `GeneratedContent` ke `video_ready`.
4. **`CleanupTemporaryAssetsJob`**
   - Menghapus **hanya** direktori sementara `remotion/public/_staging/job-{id}/`.
   - **Tidak pernah** menghapus foto/video unggahan pengguna (`media/uploads/*`) maupun file voiceover master (`tts/*`).

---

## 4. Menjalankan Queue Worker & Preview Lokal

Untuk memproses antrean render video di lingkungan lokal Windows:

```powershell
cd F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok
php artisan queue:work --tries=2 --timeout=700
```

Untuk memeriksa konfigurasi Remotion Studio secara manual (opsional):

```powershell
cd F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok\remotion
npx tsc --noEmit
npx remotion compositions src/index.ts
```

---

## 5. Pengaturan Lingkungan (`config/affiliate_ai.php` & `.env`)

- `FFMPEG_BINARY` (default: `ffmpeg`)
- `FFPROBE_BINARY` (default: `ffprobe`)
- `NODE_BINARY` (default: `node`)
- `REMOTION_BROWSER_EXECUTABLE` (opsional; otomatis mendeteksi Chrome/Edge di Windows jika dikosongkan)
- `REMOTION_RENDER_TIMEOUT` (default: `600` detik)
