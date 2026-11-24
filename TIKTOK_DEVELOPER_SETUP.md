# TIKTOK DEVELOPER SETUP — Plant Coaction Affiliate AI

Panduan langkah demi langkah mendaftarkan aplikasi resmi di **TikTok for Developers** (`https://developers.tiktok.com/`) untuk koneksi akun `@plant.coaction`.

## 1. Buat Akun & Aplikasi di TikTok for Developers
1. Buka `https://developers.tiktok.com/` dan masuk menggunakan akun developer Anda.
2. Klik **Manage Apps** → **Connect an App** / **Create App**.
3. Isi detail aplikasi (Nama: *Plant Coaction Affiliate AI*, Kategori: *Business / Creator Tools*).
4. Untuk tahap pengujian awal, aktifkan **Sandbox Mode** dan tambahkan akun TikTok uji Anda pada daftar **Target Users** (Sandbox memungkinkan pengujian OAuth & Display API sebelum App Review).

## 2. Tambahkan Produk & Scopes yang Dibutuhkan (Fase 2)
Tambahkan produk berikut di dashboard aplikasi TikTok:
- **Login Kit** — otomatis menyertakan scope `user.info.basic`.
- **Display API** — ajukan scope:
  - `user.info.profile` (username, bio, status verifikasi)
  - `user.info.stats` (follower count, following count, likes count, video count)
  - *(Opsional untuk fase mendatang: `video.list`)*

> **Catatan:** Jangan mengajukan atau mengaktifkan **Content Posting API (`video.publish`)** untuk autopost publik pada Fase 2. Direct Post dijadwalkan untuk fase publikasi setelah lolos audit resmi TikTok.

## 3. Ambil Kredensial & Konfigurasi `.env`
Salin `Client Key` dan `Client Secret` dari dashboard TikTok Developer ke file `.env` lokal Anda (**jangan pernah commit `.env` ke Git**):
```env
TIKTOK_CLIENT_KEY=isi_client_key_anda
TIKTOK_CLIENT_SECRET=isi_client_secret_anda
TIKTOK_REDIRECT_URI=http://127.0.0.1:8000/dashboard/tiktok/callback
TIKTOK_OAUTH_SCOPES=user.info.basic,user.info.profile,user.info.stats
TIKTOK_ENVIRONMENT=sandbox
```

## 4. Persyaratan untuk Production (App Review)
Saat siap berpindah dari Sandbox ke Production:
- Daftarkan Redirect URI HTTPS yang valid di server produksi.
- Sediakan halaman **Privacy Policy** dan **Terms of Service** publik.
- Unggah video demo alur OAuth sesuai ketentuan TikTok App Review.
