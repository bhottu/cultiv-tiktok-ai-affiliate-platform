# PROJECT READINESS REPORT — AFFILIATE AI AGENTIC TIKTOK (Plant Coaction)

> **Proyek Target:** `F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok`  
> **Proyek Referensi (Tidak Diubah):** `F:\PROMT-ENGINEER\affiliate-ai-agentic`  
> **Akun Target:** `https://www.tiktok.com/@plant.coaction` (Brand: **Plant Coaction**)  
> **Tanggal Audit:** 2026-11-20 (WIB / `Asia/Jakarta`)  
> **Status Fase:** **PRE-FLIGHT AUDIT SELESAI — MENUNGGU PERSETUJUAN PENGGUNA SEBELUM IMPLEMENTASI**

---

## 1. AUDIT PROYEK LAMA (`F:\PROMT-ENGINEER\affiliate-ai-agentic`)

Proyek lama diaudit secara read-only dan dipertahankan sepenuhnya tanpa modifikasi.

### 1.1 Inventaris Arsitektur Proyek Lama
- **Stack Backend:** Python 3.14.7 + FastAPI + Uvicorn + SQLite (`data/studio.db`).
- **Frontend:** Vanilla HTML/CSS/JS dashboard (`app/web/`).
- **AI Provider:** `app/services/ai_provider.py` — OpenRouter API client (dengan model utama, fallback model, retry, JSON extraction, dan SQLite caching).
- **Keamanan:** `app/security.py` — Enkripsi simetris Fernet + redaksi secret di log/error.
- **Media:**
  - `app/services/image_engine.py` — HTML/CSS + Playwright renderer.
  - `app/services/video_engine.py` — FFmpeg vertical 9:16 renderer + subtitle `.srt`.
  - `remotion/` — Baru berupa scaffold awal (`package.json`, `Root.tsx`, `ThreadsVertical.tsx`), **`node_modules` belum terinstal**.
- **Platform API:** `app/services/threads_api.py` — Khusus Meta Threads API.
- **Status Kredensial Lama:** File `.env` belum dibuat di proyek lama (`OPENROUTER_API_KEY` belum terkonfigurasi).

### 1.2 Keputusan Reuse vs Bangun Baru
| Komponen Proyek Lama | Keputusan | Alasan Teknis |
|---|---|---|
| Pola `ai_provider.py` (OpenRouter + fallback + cache + retry) | **Reuse konsep/pola** | Sudah modular, fleksibel ganti model, aman dari hardcode key. |
| Pola `security.py` (enkripsi token & redaksi secret) | **Reuse konsep/pola** | Wajib dipertahankan untuk OAuth token TikTok & API key. |
| `remotion/` scaffold | **Upgrade besar** | Proyek baru mewajibkan video Remotion profesional (bukan slideshow teks) + dubbing Bahasa Indonesia + sinkronisasi subtitle. |
| `threads_api.py` & `THREADS_PROFILE.md` (`@nate_plantco` Tech/OSINT) | **Jangan disalin** | Platform (TikTok) dan persona/niche (`Plant Coaction` tanaman hias) berbeda total. |
| `AFFILIATE_PRODUCTS.md` (Shopee Tech) | **Jangan disalin** | Proyek baru fokus pada TikTok Shop Affiliate kategori tanaman/greenhouse. |
| SQLite + in-process `asyncio` loop | **Ganti** | Proyek baru memerlukan database relasional lebih kuat (**PostgreSQL 18** sudah aktif di lokal) dan Queue/Scheduler yang andal dengan idempotency & locking. |

---

## 2. PRE-FLIGHT REQUIREMENTS

### A. TikTok Developer API
Referensi resmi: `https://developers.tiktok.com/products/content-posting-api`

#### 1) Kredensial & Komponen yang Dibutuhkan
- **TikTok Developer Account** & **Developer App** di TikTok for Developers Portal.
- **Client Key** dan **Client Secret** (disimpan terenkripsi di `.env` / database).
- **OAuth 2.0 Redirect URI** (HTTPS wajib untuk produksi/audit; localhost hanya untuk sandbox/development tertentu).
- **Access Token** (berlaku **24 jam** / `86.400` detik) & **Refresh Token** (berlaku **365 hari**, dapat berotasi setiap kali refresh sehingga backend wajib menyimpan token terbaru).

#### 2) Pemetaan API, Scope, Biaya & Persetujuan
| API / Scope | Status Kebutuhan | Biaya | Butuh App Review / Audit? | Batasan Penting |
|---|---|---|---|---|
| **Login Kit** (`user.info.basic`) | **Wajib** | Gratis | Ya (review dasar) | Akses nama tampilan & avatar akun. |
| **Display API** (`user.info.profile`, `user.info.stats`, `video.list`) | **Sangat disarankan** | Gratis | Ya | Membaca jumlah followers, likes, total video, dan daftar video publik. *Tidak* memberikan data komisi affiliate/GMV. |
| **Content Posting API — Upload ke Inbox/Draft** (`video.upload`) | **Wajib (Sangat disarankan untuk Affiliate)** | Gratis | Ya | Mengunggah video ke Draft/Inbox aplikasi TikTok agar kreator dapat menempelkan **keranjang kuning** secara resmi sebelum publish. |
| **Content Posting API — Direct Post** (`video.publish`) | **Opsional / Wajib untuk Autopost** | Gratis | **Wajib lolos Audit ketat** | **Sebelum lulus audit (`unaudited`), semua postingan otomatis dipaksa menjadi `SELF_ONLY` (privat)** dan terkena batas kuota harian ketat. |
| **Creator Info Query** (`/v2/post/publish/creator_info/query/`) | **Wajib sebelum Direct Post** | Gratis | Bagian dari `video.publish` | Wajib dipanggil sebelum posting untuk cek opsi privasi, durasi maksimal, izin duet/stitch. Dropdown privasi **tidak boleh** memiliki default pre-selected. |
| **Compliance Flags** (`is_aigc`, `brand_content_toggle`, `brand_organic_toggle`) | **Wajib untuk konten AI/Komersial** | Gratis | Bagian dari Direct Post | Wajib jujur menandai konten AI-generated (`is_aigc: true`) dan konten komersial/promosi. |
| **Media Transfer: `FILE_UPLOAD` vs `PULL_FROM_URL`** | Pilih salah satu | Gratis | `PULL_FROM_URL` wajib **Domain Verification** | Untuk tahap lokal Windows, **`FILE_UPLOAD` lebih disarankan** karena tidak membutuhkan server domain publik terverifikasi. |

#### 3) BATASAN KRITIS: Keranjang Kuning (TikTok Shop Product Anchor)
- **Standard TikTok Content Posting API TIDAK mendukung penyisipan tautan produk affiliate (keranjang kuning) pada video organik.**
- API yang mengelola produk affiliate secara terprogram (**TikTok Shop Affiliate Creator/Partner API**) hanya tersedia melalui **TikTok Shop Open Platform / Partner Center** dan memerlukan pendaftaran sebagai *Approved Service Partner* dengan persetujuan TikTok Partner Manager (tidak terbuka untuk developer individu standar).
- **Implikasi Arsitektur Resmi & Patuh:**
  1. Sistem tetap mengotomatisasi riset, strategi, naskah, dubbing Bahasa Indonesia, rendering Remotion, QC, caption, dan upload video.
  2. Untuk video affiliate, jalur paling aman dan realistis adalah **upload ke Draft/Inbox TikTok via `video.upload`**, status konten menjadi **`Waiting Product Tag`**, lalu pemilik akun menempelkan keranjang kuning dari Showcase di aplikasi TikTok (memakan waktu ~30 detik/hari).
  3. **Dilarang** menggunakan browser automation / private endpoint untuk memaksa penempelan keranjang kuning.

---

### B. TikTok Shop Affiliate (`@plant.coaction`)
Persyaratan resmi TikTok Shop Affiliate Indonesia yang harus diverifikasi pemilik akun:
1. **Usia & Identitas:** Usia minimal 18 tahun, verifikasi **KTP asli** yang valid.
2. **Syarat Kreator:**
   - Standar jalur Kreator Afiliasi: minimal **600 followers** dan aktif mengunggah video dalam **28 hari terakhir**.
   - Jika di bawah 600 followers: sistem biasanya mengarahkan ke akses terbatas (*Link-Share Only*), atau kreator dapat mendaftar lewat jalur **TikTok Shop Seller Center** (akun penjual yang ditautkan sebagai akun pemasaran/official) jika memenuhi ketentuan yang berlaku.
3. **Kepatuhan Akun:** Bukan akun GPPPA (pemerintah/politik), bebas pelanggaran Pedoman Komunitas & kebijakan e-commerce.
4. **Rekening Bank:** Rekening bank lokal aktif dengan **nama pemilik identik dengan KTP**.
5. **Aktivasi Fitur:** Akses **Product Marketplace**, penambahan produk ke **Showcase**, dan izin menautkan produk pada video.

> **Aturan Keamanan:** Aplikasi ini **TIDAK AKAN PERNAH** meminta atau menyimpan password TikTok Anda. Seluruh pendaftaran, verifikasi KTP/rekening, dan persetujuan dilakukan langsung oleh pemilik akun di aplikasi resmi TikTok / TikTok Shop Creator Center.

---

### C. AI Provider (OpenRouter)
- **Audit Proyek Lama:** Pola integrasi OpenRouter sudah terbukti berjalan baik (mendukung ganti model tanpa ubah kode, fallback model, retry, dan caching).
- **Status Saat Ini:** Belum ada `OPENROUTER_API_KEY` di lingkungan lokal.
- **Ketersediaan & Batasan Model:**
  - **Model Gratis (`:free`, mis. variasi Gemma / Llama / Qwen yang aktif):**
    - Batas global: **20 request/menit (RPM)** dan **50 request/hari (RPD)** untuk akun tanpa kredit, atau naik menjadi **1.000 RPD** setelah pembelian kredit lifetime minimal **\$10**.
    - *Risiko:* Sering terkena `429 Too Many Requests` dari upstream provider dan daftar model `:free` dapat berubah sewaktu-waktu.
  - **Model Berbayar Hemat Biaya (Disarankan untuk produksi 1 video/hari):**
    - Analisis & Strategi Konten: `google/gemini-2.5-flash` atau `deepseek/deepseek-chat-v3` (sangat murah, kualitas Bahasa Indonesia natural).
    - Naskah Voiceover & Copywriting Natural: `anthropic/claude-3.5-haiku` / `google/gemini-2.5-flash`.
    - Quality Control (Structured JSON Evaluation): `openai/gpt-4o-mini` atau `google/gemini-2.5-flash`.
    - Fallback Otomatis: `openrouter/free` atau model murah kedua.
- **Estimasi Pemakaian (1 video/hari ≈ 5–8 panggilan LLM/hari):** Hanya sekitar **\$1–\$3 per bulan** menggunakan model berbayar hemat biaya.

---

### D. Text-to-Speech (Dubbing Bahasa Indonesia Natural)
Perbandingan provider resmi dengan dukungan Bahasa Indonesia (suara pria & wanita):

| Provider | Suara Indonesia (Pria / Wanita) | Kualitas & Kontrol | Free Tier & Lisensi Komersial | Estimasi Biaya (30 video/bln @ ~800 karakter = ~24.000 karakter) |
|---|---|---|---|---|
| **Microsoft Azure Speech Neural** *(Rekomendasi #1 untuk efisiensi)* | `id-ID-ArdiNeural` (Pria), `id-ID-GadisNeural` (Wanita) | Sangat natural, jelas untuk istilah tanaman, mendukung SSML (kecepatan, pitch, jeda, penekanan) | Free F0: 500rb karakter/bln (**hanya prototyping**; hak komersial wajib tier S0) | Tier S0: \$15 / 1 juta karakter → **≈ \$0,36 / bulan** (Rp6.000/bln) |
| **Google Cloud TTS** *(Rekomendasi #2)* | `id-ID-Wavenet-A/B/C/D`, `id-ID-Neural2`, `Chirp 3 HD` | Stabil, mendukung SSML, tersedia pria & wanita | Free: 4 juta char/bln (WaveNet) / 1 juta char/bln (Neural2) dgn billing aktif | **\$0 / bulan** (masuk kuota gratis) atau \$4–\$16 / 1 juta char |
| **ElevenLabs** *(Rekomendasi #1 untuk kualitas ekspresif / voice cloning)* | Multilingual v2 / Flash v2.5 (banyak suara pria/wanita + Voice Cloning suara asli pemilik) | Paling manusiawi, intonasi creator-grade | Free: 10rb kredit/bln (**tanpa lisensi komersial**) | Starter: **\$6 / bulan** (30rb kredit + lisensi komersial) · Creator: **\$22 / bulan** (121rb kredit + Pro Voice Cloning) |

> **Rekomendasi Arsitektur:** Buat **Multi-Provider TTS Adapter** yang mendukung Azure Speech S0, Google Cloud TTS, dan ElevenLabs (bisa diganti dari menu **TTS Settings**). Jika provider TTS belum dikonfigurasi atau gagal, sistem **wajib menghentikan finalisasi video** dan tidak pernah mengklaim dubbing selesai.

---

### E. Remotion & Audit Hardware Lokal Windows
Hasil pemeriksaan langsung pada mesin Windows Anda:
- **CPU:** AMD Ryzen 5 2400G (4 Core / 8 Thread)
- **RAM:** 15,95 GB (~16 GB)
- **Penyimpanan Kosong Drive F:** 40,91 GB
- **Runtime Terpasang:**
  - `Node.js v22.19.0` & `npm 10.9.3` (Siap untuk Remotion v4)
  - `FFmpeg 9.0.2` (Terpasang di PATH)
  - `Google Chrome` & `Microsoft Edge` (Siap sebagai browser engine Chromium untuk Remotion)
- **Estimasi Waktu Rendering:** Video vertikal `1080×1920`, `30 FPS`, durasi `30–45 detik` (`900–1.350 frame`) pada Ryzen 5 2400G membutuhkan sekitar **2–5 menit/video** (dengan concurrency 2–4 agar RAM 16 GB tetap stabil).
- **Lokal Windows vs VPS (Tahap Awal):**
  - **Komputer lokal Windows JAUH LEBIH COCOK untuk tahap awal.** Target produksi hanya 1 video/hari (~5 menit beban CPU per hari). Menyewa VPS khusus rendering Remotion (minimal 4 vCPU + 8 GB RAM) akan menelan biaya \$20–\$50/bulan tanpa urgensi di fase awal.
  - **Catatan Storage:** 40,91 GB cukup untuk tahap awal (1 video final ≈ 20–40 MB; 365 video/tahun ≈ 8–15 GB), asalkan cache render sementara dibersihkan otomatis dan backup aset produk diatur.

---

### F. Penyimpanan, Database & Pilihan Stack Backend
Hasil pemeriksaan layanan lokal:
- **PostgreSQL 18 (`postgresql-x64-18`):** **SUDAH TERPASANG DAN BERJALAN (`Running`)** di Windows Anda!
- **PHP 8.2.4 (XAMPP) & Composer:** **SUDAH TERPASANG**.
- **Python 3.14.7:** **SUDAH TERPASANG**.

#### Keputusan Stack Backend (Memerlukan Konfirmasi Anda)
Master Prompt menyebut *PostgreSQL* dan *Laravel Scheduler & Queue jika stack Laravel digunakan*. Karena kedua runtime tersedia di komputer Anda, berikut perbandingannya:
1. **Opsi 1 — Laravel (PHP 8.2 + PostgreSQL 18 + Laravel Queue/Scheduler + Node.js Remotion Worker):**
   - Kelebihan: Native Queue Worker, Scheduler (`Asia/Jakarta`), Migration, CSRF, Auth, Failed Jobs, Locking (`WithoutOverlapping`) sudah matang dari bawaan framework (sangat cocok dengan Bagian 12–14 Master Prompt).
   - Catatan: Perlu memastikan ekstensi PHP `pdo_pgsql` aktif di `C:\xampp\php\php.ini`.
2. **Opsi 2 — Python FastAPI + PostgreSQL 18 + Scheduler/Worker + Node.js Remotion Worker:**
   - Kelebihan: Dapat langsung menggunakan ulang struktur kode Python dari proyek lama `affiliate-ai-agentic`.

---

### G. Tabel Kesiapan Proyek (`Readiness Matrix`)

| Komponen | Dibutuhkan | Status Aktual | Tindakan yang Diperlukan |
|---|---|---|---|
| **Proyek Referensi** (`affiliate-ai-agentic`) | Referensi | **Tersedia & Utuh** | Tidak diubah; hanya dijadikan rujukan pola arsitektur. |
| **Folder Proyek Baru** (`affiliate-ai-agentic-tiktok`) | Ya | **Sudah ada (kosong)** | Siap digunakan setelah persetujuan. |
| **TikTok Developer Account & App** | Ya | **Belum dikonfigurasi** | Daftar di `developers.tiktok.com`, buat App, ambil Client Key & Secret. |
| **Content Posting API (`video.upload` / `video.publish`)** | Ya untuk upload/autopost | **Belum terdaftar** | Ajukan produk Content Posting API; pahami bahwa Direct Post publik wajib lulus **App Audit**. |
| **TikTok Shop Affiliate (`@plant.coaction`)** | Ya | **Belum terverifikasi** | Pemilik akun cek status Affiliate, KTP, rekening bank, dan Showcase di aplikasi TikTok. |
| **OpenRouter API** | Ya | **Belum dikonfigurasi** | Buat API key di `openrouter.ai` (disarankan top-up awal \$10 agar bebas batas 50 req/hari). |
| **TTS Bahasa Indonesia** | Ya (wajib dubbing) | **Belum dipilih/dikonfigurasi** | Pilih provider: Azure Speech S0 (paling hemat komersial), Google Cloud TTS, atau ElevenLabs Starter (\$6/bln). |
| **Remotion** | Ya | **Node 22 + Chrome + FFmpeg siap; Remotion belum diinstal di proyek baru** | Install `remotion` di proyek baru pada Fase 1/5. |
| **Database (PostgreSQL 18)** | Ya | **Running (`postgresql-x64-18`)** | Buat database baru (mis. `plant_coaction_tiktok`) saat Fase 1. |
| **Storage Lokal (`F:`)** | Ya | **40,91 GB kosong (Siap)** | Atur struktur folder `storage/raw`, `storage/tts`, `storage/renders`, `storage/backups`. |
| **Scheduler & Queue (`Asia/Jakarta`)** | Ya | **Belum dibangun** | Implementasi saat Fase 1 & 6 (default mode: **Semi Automatic**). |

---

## 3. ANALISIS AWAL AKUN TIKTOK TARGET (`@plant.coaction`)

### 3.1 Informasi Publik yang Terverifikasi
Berdasarkan informasi indeks web publik (tanpa scraping melanggar ketentuan platform):
- **Brand:** **Plant Coaction** — dikenal sebagai brand/nursery dan komunitas tanaman hias di Indonesia yang kuat di segmen *Araceae* / aroid koleksi (Monstera, Philodendron, Anthurium variegata) dan terafiliasi dengan ekosistem kreator Nate Nasution (hadir di Linktree, YouTube, Instagram, Tokopedia, Shopee, dan TikTok `@plant.coaction`).
- **Keunggulan Kompetitif untuk Affiliate:** Berbeda dari akun affiliate umum yang hanya menempel foto katalog, Plant Coaction memiliki **kredibilitas praktisi/nursery nyata**. Audiens tanaman hias sangat menghargai bukti perawatan, media tanam yang benar, dan alat yang memang menyelesaikan masalah (akar busuk, hama, kelembapan greenhouse).

### 3.2 Data Aktual yang Belum Dapat Diakses Otomatis (Harus Diserahkan Pemilik Akun)
TikTok membatasi pembacaan metrik detail profil tanpa OAuth / akses TikTok Studio. Agar tidak mengarang data, mohon siapkan data berikut sebelum Fase 2–4:
1. **Screenshot profil `@plant.coaction`** (bio, jumlah followers, total likes, apakah ikon Showcase/keranjang sudah aktif).
2. **Status TikTok Shop Affiliate** (sudah aktif / belum daftar / jumlah produk di Showcase saat ini).
3. **Data TikTok Studio Analytics (28–60 hari terakhir):**
   - Total views, profile views, likes, comments, shares.
   - Audience demographics (usia, gender, kota/lokasi utama di Indonesia).
   - **Jam & hari aktif followers** (untuk validasi prime time di luar hipotesis awal 11:30–13:00 & 19:00–21:30 WIB).
4. **Daftar 5–10 video terbaru atau video performa terbaik** (judul/topik, durasi, views, likes, komentar, shares).
5. **Ketersediaan aset visual asli:** Apakah Anda memiliki stok video/foto tanaman, greenhouse, atau alat berkebun milik sendiri yang boleh dipakai Remotion sebagai B-roll?

### 3.3 Draft Awal Content Pillars Plant Coaction (Akan Dikalibrasi Setelah Data Masuk)
1. **Tips Perawatan Tanaman Harian** — Membangun trust & edukasi (non-jualan murni).
2. **Problem Solving Tanaman** — Akar busuk, daun menguning, stres repotting, hama (transisi sangat natural ke produk solusi).
3. **Review & Racikan Media Tanam** — Cocopeat, perlite, pumice, pakis, sphagnum moss, media aroid.
4. **Perawatan Aroid & Anthurium Koleksi** — Menjaga identitas inti Plant Coaction.
5. **Review Alat Berkebun** — Sprayer, gunting pruning, meteran pH/kelembapan, alat repotting.
6. **Greenhouse & Mikroklimat** — Grow light, kipas sirkulasi, hygrometer, humidifier, misting.
7. **Demonstrasi Produk Nyata** — Menampilkan cara kerja produk secara objektif.
8. **Perbandingan Produk (A vs B)** — Membantu audiens memilih sesuai budget & skala koleksi.
9. **Rekomendasi Perlengkapan Kolektor** — Pot transparan, perlengkapan propagasi, rak tanaman.

---

## 4. ESTIMASI BIAYA BULANAN (TAHAP AWAL — 1 VIDEO/HARI)

| Komponen | Opsi Hemat (Direkomendasikan Awal) | Opsi Premium (Voice Cloning / Ekspresif) |
|---|---|---|
| **Hosting / Infrastruktur** | **\$0 / bulan** (Windows lokal + PostgreSQL 18 lokal) | \$20–\$40 / bulan (jika nanti pindah ke VPS) |
| **TikTok Developer & Content Posting API** | **\$0 / bulan** (Resmi gratis) | \$0 / bulan |
| **OpenRouter AI** | **≈ \$1–\$3 / bulan** *(top-up awal \$10 bertahan berbulan-bulan + buka 1.000 req/hari free tier)* | \$5–\$15 / bulan |
| **TTS Bahasa Indonesia** | **\$0 – \$1 / bulan** (Google Cloud TTS gratis kuota bulanan / Azure Speech S0 ≈ \$0,36/bln) | **\$6 – \$22 / bulan** (ElevenLabs Starter \$6 / Creator \$22) |
| **Remotion** | **\$0 / bulan** (Lisensi gratis untuk individu/perusahaan kecil sesuai syarat Remotion) | \$0 / bulan |
| **TOTAL ESTIMASI** | **≈ \$1 – \$4 / bulan** *(± Rp16.000 – Rp65.000/bln)* | **≈ \$11 – \$37 / bulan** *(+ biaya VPS jika pindah cloud)* |

---

## 5. RISIKO TEKNIS & BATASAN OTOMATISASI

1. **Keranjang Kuning Tidak Bisa Dipasang Lewat Standard Content Posting API:** Otomatisasi 100% tanpa sentuhan manusia hingga berstatus *affiliate video dengan keranjang kuning* **tidak didukung** oleh API publik TikTok standar. Workflow wajib menyertakan tahap **`Waiting Product Tag`** (upload otomatis ke Draft/Inbox → tambah produk di app TikTok).
2. **Audit TikTok Direct Post Cukup Ketat:** Sebelum lolos audit, semua Direct Post terkunci di **`SELF_ONLY`**. Video tidak boleh di-autopost ke publik sebelum audit disetujui dan mode Semi-Automatic teruji.
3. **Kualitas Visual Produk:** Remotion akan terlihat seperti template kaku jika hanya diberi foto katalog polos. Diperlukan B-roll nyata (rekaman tanaman/greenhouse Plant Coaction atau materi resmi seller yang diizinkan).
4. **Kepatuhan Konten & Hak Cipta:** Dilarang mengambil video seller/kreator lain tanpa izin, dilarang mengklaim "obat pasti sembuh" untuk penyakit tanaman, dan wajib mengaktifkan disclosure AI (`is_aigc`) serta konten komersial sesuai kebijakan TikTok.
5. **Stok & Perubahan Komisi Produk:** Produk TikTok Shop bisa habis stok atau komisi berubah sewaktu-waktu; scheduler wajib mengecek status ketersediaan sebelum memproduksi/menjadwalkan video.

---

## 6. DAFTAR TINDAKAN UNTUK PEMILIK AKUN (SEBELUM FASE 1 DIMULAI)

Mohon konfirmasi / siapkan poin-poin berikut:
1. **Pilih Stack Backend:** Apakah Anda ingin proyek baru menggunakan **Laravel (PHP 8.2 + PostgreSQL 18)** atau **Python FastAPI + PostgreSQL 18**?
2. **Pilih Provider TTS Indonesia:** Apakah memilih **Azure Speech Neural (S0)**, **Google Cloud TTS**, atau **ElevenLabs**?
3. **Konfirmasi Status Akun `@plant.coaction`:**
   - Apakah TikTok Shop Affiliate & Showcase sudah aktif?
   - Berikan data dasar/screenshot TikTok Studio Analytics (followers, jam aktif, 5–10 video terbaru) agar profil brand akurat tanpa tebakan.
4. **Siapkan Kredensial (Saat Masuk Fase Integrasi):**
   - `OPENROUTER_API_KEY` (dari `openrouter.ai`)
   - API Key TTS pilihan Anda
   - Daftar aplikasi di `developers.tiktok.com` (Client Key & Client Secret)
5. **Persetujuan Memulai Fase 1:** Konfirmasikan apakah kita dapat mulai membangun fondasi proyek di `F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok`.
