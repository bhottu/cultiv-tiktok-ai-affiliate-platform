# PHASE 2 — TIKTOK API RESEARCH (`@plant.coaction`)

> **Proyek:** `F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok`  
> **Akun Target:** `https://www.tiktok.com/@plant.coaction` (Plant Coaction — Indonesia)  
> **Tanggal Riset:** 2026-11-20 (WIB)  
> **Sumber Referensi:** Dokumentasi resmi TikTok for Developers (`developers.tiktok.com`) dan TikTok Shop Partner Center (`partner.tiktokshop.com`).

---

## 1. Pemetaan API Resmi TikTok

TikTok memisahkan ekosistem API menjadi **dua platform developer yang berbeda** — keberhasilan OAuth pada salah satunya **tidak otomatis** memberikan akses ke platform lainnya:

| Platform & API | Fungsi Utama | Scope / Izin Resmi | Status Akses & Review |
|---|---|---|---|
| **TikTok for Developers — Login Kit (OAuth v2)** | Autentikasi akun TikTok tanpa password, menerbitkan `access_token` & `refresh_token`. | `user.info.basic` (bawaan Login Kit) | Tersedia di Sandbox untuk akun uji; wajib **App Review** untuk Production. |
| **TikTok for Developers — Display API v2** | Membaca profil & statistik dasar akun (`GET /v2/user/info/`) serta daftar video publik (`POST /v2/video/list/`). | `user.info.basic`, `user.info.profile`, `user.info.stats`, `video.list` | Wajib mengajukan produk Display API + App Review untuk Production. |
| **TikTok for Developers — Content Posting API** *(Fase 6, belum di Fase 2)* | Upload video ke Draft/Inbox (`video.upload`) atau Direct Post (`video.publish`). | `video.upload`, `video.publish` | Direct Post publik wajib lulus **Content Posting API Audit**; sebelum lulus audit terkunci di `SELF_ONLY`. |
| **TikTok Shop Partner Center — Affiliate Creator API** | Pencarian produk Open Collaboration (`Creator Search Open Collaboration Product`), kolaborasi kreator, dan pelacakan pesanan affiliate. | OAuth kreator terpisah (`shop.tiktok.com/alliance/creator/auth`), mis. scope *Read Creator Affiliate Collaborations* (`1021508`) | **Wajib pendaftaran terpisah di TikTok Shop Partner Center (TTSPC)** & persetujuan aplikasi sesuai wilayah/kategori. |

---

## 2. Detail Teknis OAuth v2 & Display API Resmi

### A. Endpoint OAuth v2 (`developers.tiktok.com`)
1. **Authorization URL:** `GET https://www.tiktok.com/v2/auth/authorize/`
   - Parameter: `client_key`, `response_type=code`, `scope` (dipisahkan koma), `redirect_uri` (harus persis sama dengan yang didaftarkan di portal), `state` (token acak anti-CSRF).
2. **Token Exchange & Refresh:** `POST https://open.tiktokapis.com/v2/oauth/token/` (`application/x-www-form-urlencoded`)
   - *Authorization Code:* `client_key`, `client_secret`, `code` (URL-decoded), `grant_type=authorization_code`, `redirect_uri`.
   - *Refresh Token:* `client_key`, `client_secret`, `grant_type=refresh_token`, `refresh_token`.
   - **Masa Berlaku:** `access_token` berlaku **24 jam** (`86.400` detik); `refresh_token` berlaku **365 hari** (`31.536.000` detik) dan dapat berotasi saat refresh (backend wajib menyimpan `refresh_token` terbaru secara terenkripsi).
3. **Token Revocation (Disconnect):** `POST https://open.tiktokapis.com/v2/oauth/revoke/`
   - Parameter: `client_key`, `client_secret`, `token`.

### B. Endpoint Display API v2 & Batas Rate Limit
1. **User Info:** `GET https://open.tiktokapis.com/v2/user/info/?fields=...` (Header: `Authorization: Bearer <access_token>`)
   - Rate limit: **600 request/menit** per endpoint (HTTP `429` / `rate_limit_exceeded` bila terlampaui).
   - Pemetaan field berdasarkan scope yang benar-benar diberikan:
     - `user.info.basic` → `open_id`, `union_id`, `avatar_url`, `display_name`
     - `user.info.profile` → `username`, `bio_description`, `profile_deep_link`, `is_verified`
     - `user.info.stats` → `follower_count`, `following_count`, `likes_count`, `video_count`
2. **Video List:** `POST https://open.tiktokapis.com/v2/video/list/?fields=...` (scope `video.list`, max 20 video/halaman, rate limit 600 req/menit).

---

## 3. Data yang DAPAT vs TIDAK DAPAT Diakses

### Data yang Dapat Diakses (Jika Scope Disetujui)
- Identitas akun (`open_id`, `display_name`, `avatar_url`, dan `username`/`bio_description`/`is_verified` bila `user.info.profile` diberikan).
- Statistik ringkas profil (`follower_count`, `following_count`, `likes_count`, `video_count` bila `user.info.stats` diberikan).
- Daftar metadata video publik (`video.list`).

### Data yang TIDAK Tersedia melalui TikTok Login Kit / Display API
- **Analytics Mendalam TikTok Studio:** demografi audiens (usia, gender, kota), jam aktif followers, *average watch time*, *completion rate*, sumber traffic.
- **Status Eligibility TikTok Shop Affiliate:** status verifikasi KTP/rekening, izin Showcase, status akun kreator affiliate.
- **Katalog Produk TikTok Shop, Komisi, Klik Produk, Pesanan, dan GMV:** hanya tersedia melalui **TikTok Shop Partner Center (Affiliate Creator API)** yang memiliki proses persetujuan terpisah.
- **Pemasangan Keranjang Kuning Otomatis (Product Anchor):** tidak didukung oleh Content Posting API organik standar.

---

## 4. Batasan Sandbox vs Production
- **Sandbox Mode:** Mendukung maksimal 5 konfigurasi sandbox dengan daftar *target test users* yang diundang secara eksplisit; cocok untuk memvalidasi alur OAuth, penyimpanan token terenkripsi, refresh token, dan `/v2/user/info/` sebelum App Review.
- **Production Mode:** Wajib lulus **App Review** TikTok for Developers (memerlukan URL Kebijakan Privasi, Syarat Layanan, domain/callback terverifikasi, serta video demo alur OAuth).

---

## 5. Fitur yang Belum Dapat Diotomatisasi & Alternatif Resmi
1. **Verifikasi Kelayakan Affiliate Akun:** Harus diperiksa langsung oleh pemilik akun di **TikTok Shop Creator Center** (aplikasi menampilkan checklist jujur dan tautan resmi, bukan menebak dari jumlah followers).
2. **Penemuan Produk Tanpa Akses TTSPC API:** Jika kredensial TikTok Shop Affiliate API belum disetujui, halaman Riset Produk menampilkan status jujur **"Menunggu akses TikTok Shop Affiliate API"** tanpa scraping dan tanpa produk palsu.
3. **Analytics Mendalam TikTok Studio:** Hingga tersedia akses API resmi yang diizinkan, halaman Analytics menampilkan status belum tersedia melalui integrasi resmi dan menjelaskan izin yang dibutuhkan.
