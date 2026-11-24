# TIKTOK OAUTH SETUP — Konfigurasi Callback & Token Management

## 1. Alur OAuth v2 Resmi di Aplikasi
1. Pengguna membuka menu **TikTok → Akun TikTok** (`/dashboard/tiktok`) dan klik **Connect TikTok (OAuth Resmi)**.
2. Backend (`TikTokOAuthService::createAuthorizationUrl`) membuat token acak `state` 48 karakter (disimpan di session terenkripsi selama 10 menit dan terikat ke `user_id` aktif) lalu mengarahkan ke:
   `https://www.tiktok.com/v2/auth/authorize/`
3. Setelah pengguna menyetujui izin di halaman resmi TikTok, TikTok mengarahkan kembali ke:
   `http://127.0.0.1:8000/dashboard/tiktok/callback` *(sesuaikan dengan `TIKTOK_REDIRECT_URI` di `.env` dan portal TikTok Developer)*.
4. Backend memvalidasi `state` secara ketat (`hash_equals` + single-use) untuk mencegah CSRF.
5. Backend menukar authorization `code` melalui `POST https://open.tiktokapis.com/v2/oauth/token/`.
6. `access_token` (24 jam) dan `refresh_token` (365 hari) dienkripsi menggunakan Laravel `Crypt` (`AES-256-CBC`) sebelum disimpan ke tabel `social_accounts`.
7. Sistem menyinkronkan profil akun via `GET https://open.tiktokapis.com/v2/user/info/` dengan hanya meminta field sesuai scope yang disetujui.

## 2. Manajemen Token & Keamanan
- **Refresh Token Rotation:** Tombol **Refresh Token** memanggil `grant_type=refresh_token` dan menimpa token lama dengan token rotasi terbaru secara terenkripsi.
- **Revoked / Expired Handling:** Jika token kedaluwarsa atau dicabut (HTTP 401/403), status akun otomatis berubah menjadi `token_expired` atau `revoked` dan tombol **Reconnect TikTok** ditampilkan.
- **Secure Disconnect:** Tombol **Disconnect TikTok** memanggil `POST https://open.tiktokapis.com/v2/oauth/revoke/` dan menghapus seluruh token terenkripsi dari database lokal.
- **Zero Secret Leak:** Token tidak pernah dikembalikan ke HTML maupun dicatat di `activity_logs` / `storage/logs/laravel.log`.
