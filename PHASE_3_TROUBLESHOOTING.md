# PHASE 3 — PANDUAN TROUBLESHOOTING

Panduan penanganan kendala umum pada AI Content Generator, Gemini TTS Voiceover, Visual Asset Manager, dan Remotion Video Rendering.

---

## 1. Status Render Tertahan di `queued` (5%)

- **Penyebab:** Worker Laravel Queue belum dijalankan.
- **Solusi:** Jalankan worker pada terminal proyek:
  ```powershell
  cd F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok
  php artisan queue:work --tries=2 --timeout=700
  ```
- Periksa tabel `failed_jobs` atau menu **Log Aktivitas** (`/activity-logs`) jika job berhenti dengan status `failed`.

---

## 2. Remotion Gagal Menemukan Browser Chrome/Edge

- **Gejala:** Error `Could not find Chrome/Chromium executable` pada status render.
- **Solusi:**
  1. Pastikan Google Chrome atau Microsoft Edge terinstal di Windows.
  2. Atau tetapkan path absolut di `.env`:
     ```env
     REMOTION_BROWSER_EXECUTABLE="C:\Program Files\Google\Chrome\Application\chrome.exe"
     ```
  3. Jalankan `php artisan config:clear`.

---

## 3. Render Gagal / Timeout di Windows

- **Penyebab:** Durasi video panjang atau banyak klip video beresolusi 4K bersamaan.
- **Solusi:**
  1. Gunakan klip video 1080p (maks 100 MB) untuk performa render optimal.
  2. Naikkan batas timeout di `.env` bila merender video 60–90 detik:
     ```env
     REMOTION_RENDER_TIMEOUT=900
     ```
  3. Gunakan tombol **Retry Render** di halaman Video Editor — sistem akan menggunakan ulang record job yang sama tanpa menciptakan file MP4 duplikat.

---

## 4. Gemini TTS Mengembalikan Error `429` / Quota / Timeout

- **Penyebab:** Batas kuota free-tier Google AI Studio tercapai atau jaringan melambat.
- **Solusi:**
  - Naskah dan aset visual Anda **tidak hilang** saat TTS gagal.
  - Tunggu beberapa saat lalu klik **Regenerate Voiceover**, atau ubah default TTS sementara di **Pengaturan Text-to-Speech** (`/settings/tts`).
  - Anda juga tetap dapat merender video visual tanpa voiceover jika diperlukan.

---

## 5. Upload Aset Visual Ditolak

- **Penyebab:** Format/MIME tidak diizinkan atau ukuran melebihi batas.
- **Aturan Ketat Server:**
  - Gambar: `JPG`, `JPEG`, `PNG`, `WEBP` — maksimal **15 MB**.
  - Video: `MP4`, `MOV` — maksimal **100 MB**.
  - Musik Latar: `MP3`, `WAV` — maksimal **25 MB**.
- Periksa juga konfigurasi `upload_max_filesize` dan `post_max_size` di `php.ini` lokal jika mengunggah video > 40 MB.

---

## 6. Subtitle Kurang Sinkron dengan Narasi

- **Penjelasan:** Subtitle otomatis menggunakan estimasi proporsional berbasis jumlah kata dan durasi total voiceover (ditandai badge `estimasi`).
- **Solusi:** Di halaman **Video Editor** bagian **3. Subtitle Otomatis & Editor Waktu**, sesuaikan angka detik mulai/selesai dan teks secara langsung, lalu klik **Simpan Draft Editor** (badge otomatis berubah menjadi `manual`) dan klik **Render Ulang Video**.

---

## 7. File Staging Remotion Masih Tersisa Setelah Crash Paksa

- Jika proses dihentikan paksa (`Ctrl+C` saat render berjalan), hapus folder sementara secara aman di:
  `remotion/public/_staging/`
- **Aman:** Folder ini hanya berisi salinan sementara untuk Chromium headless; seluruh unggahan asli (`storage/app/private/media/uploads`) dan voiceover (`storage/app/private/tts`) tetap utuh.
