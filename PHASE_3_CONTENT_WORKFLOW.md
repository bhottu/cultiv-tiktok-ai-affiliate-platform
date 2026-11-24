# PHASE 3 — ALUR KERJA PRODUKSI KONTEN (PLANT COACTION)

Panduan operasional harian untuk menghasilkan 1 video TikTok vertikal 9:16 per hari bagi akun **@plant.coaction** (*connecting plants, people & animals*).

---

## Ringkasan Pipeline 10 Status

Setiap konten melewati siklus hidup yang transparan dan teraudit:

`idea` → `script_draft` → `voiceover_ready` → `ready_to_render` → `rendering` → `video_ready` → `pending_approval` → `approved` (atau `rejected` untuk revisi / `archived`).

---

## Langkah 1 — AI Content Research (`/dashboard/content/research`)

1. Pilih salah satu dari **8 kategori niche Plant Coaction**:
   - `Plant Education`
   - `Product Review`
   - `Plant Showcase`
   - `Problem & Solution`
   - `Storytelling`
   - `Plant Care Tips`
   - `Rare Plant Facts`
   - `Affiliate Product Promotion`
2. Tentukan target audiens, tujuan konten, jumlah ide (1–8), dan opsional tautkan produk affiliate yang sudah berstatus **Approved** dari Fase 2.
3. Klik **Generate Ide Konten AI** (menggunakan `AIProviderManager`: Google Gemini / OpenRouter dengan controlled fallback).
4. Periksa badge **Perlu Verifikasi Fakta** bila muncul. Klik **Simpan** untuk menyimpan ide favorit, atau klik **Buat Naskah →** untuk langsung masuk ke Script Generator.

---

## Langkah 2 — AI Script Generator & Voiceover (`/dashboard/content/scripts`)

1. Pilih **gaya narasi** (`Edukatif`, `Santai dan Natural`, `Storytelling`, `Review Produk`, `Persuasif`, `Fun Facts`) dan **target durasi** (`15`, `30`, `45`, `60`, atau `90` detik).
2. Klik **Generate Naskah AI** untuk menghasilkan struktur lengkap:
   - Hook (3 detik pertama)
   - Opening
   - Main Narration
   - Supporting Explanation
   - Closing & CTA natural
   - Caption TikTok, Hashtag, Disclosure affiliate (jika ada), serta rekomendasi scene visual.
3. **Edit Terarah:**
   - Gunakan **Regenerate Bagian Tertentu** untuk mengganti hanya 1 bagian (misalnya `Hook`) tanpa mengubah bagian naskah lainnya.
   - Gunakan **Edit Manual** lalu klik **Simpan Draft Naskah** untuk penyesuaian bahasa pribadi.
4. **Generate Voiceover Bahasa Indonesia (Gemini TTS):**
   - Pada panel Voiceover, pilih suara Gemini (`Kore`, `Aoede`, `Puck`, `Charon`, `Fenrir`) dan arahan gaya baca opsional, lalu klik **Generate VO**.
   - Dengarkan preview audio langsung di halaman. Jika terjadi error/quota TTS, naskah dan aset Anda tetap aman tersimpan.
5. Klik **Lanjutkan ke Video Editor →**.

---

## Langkah 3 — Unggah Aset Visual Legal (`/dashboard/content/assets`)

1. Unggah foto tanaman (`JPG/JPEG/PNG/WEBP`, maks 15 MB), klip video pendek (`MP4/MOV`, maks 100 MB), atau musik latar legal (`MP3/WAV`, maks 25 MB).
2. Tautkan aset ke konten tertentu dan atur urutan tampil (`display_order`).
3. Seluruh file disimpan pada storage privat lokal dan hanya dapat diakses oleh pemilik akun yang terautentikasi.

---

## Langkah 4 — Video Preview & Remotion Editor (`/dashboard/content/editor/{id}`)

1. Pilih 1 dari **5 template Remotion** sesuai karakter konten (`PlantEducation`, `ProductReview`, `PlantShowcase`, `ProblemSolution`, `Storytelling`).
2. Atur **Daftar Scene**: urutan, aset foto/video per scene, efek gerak kamera (`zoom_in`, `zoom_out`, `pan_left`, `pan_right`, `static`), transisi, teks overlay singkat, dan durasi.
3. Periksa **Subtitle Editor**:
   - Subtitle awal bertanda `estimasi` (dibagi proporsional mengikuti durasi audio).
   - Edit teks dan waktu mulai/selesai bila perlu (otomatis berubah ke status `manual`), atau matikan subtitle lewat toggle.
4. Atur volume voiceover dan musik latar opsional (otomatis di-ducking agar narasi tetap jernih).
5. Klik **Simpan Draft Editor**, lalu klik **Render Video (Remotion)**.
6. Pantau progress bar secara live. Setelah selesai (`100%`), putar video MP4 langsung di panel kiri.

---

## Langkah 5 — Review, Approval & Ekspor MP4 (`/dashboard/content`)

1. Buka **Content Library & Approval** dan klik **Review** pada konten yang telah selesai dirender.
2. Putar video hasil render 9:16, periksa caption, hashtag, dan affiliate disclosure.
3. Berikan keputusan manusia (*Human-in-the-Loop*):
   - **Setujui (Approve)** — mencatat log persetujuan pada `content_approval_logs` dan menandai konten siap unggah.
   - **Tolak / Minta Revisi** — mencatat catatan revisi untuk diedit ulang di Video Editor.
   - **Arsipkan** — memindahkan konten lama ke arsip.
4. Klik **Download / Export MP4** untuk mengunduh file MP4 final dan mengunggahnya secara manual ke akun TikTok `@plant.coaction` (auto-publish TikTok bukan bagian dari Fase 3).
