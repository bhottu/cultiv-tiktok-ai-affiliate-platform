# AFFILIATE API ACCESS REQUIREMENTS — TikTok Shop Affiliate Indonesia

## 1. Pemisahan Penting Dua Platform API TikTok
Keberhasilan koneksi OAuth melalui **TikTok for Developers** (`developers.tiktok.com`) **TIDAK memberikan akses** ke katalog produk, komisi, maupun data pesanan TikTok Shop Affiliate.

Akses terprogram untuk produk affiliate dikelola secara terpisah melalui **TikTok Shop Partner Center (TTSPC)**: `https://partner.tiktokshop.com/`.

## 2. Persyaratan Verifikasi Akun Kreator (Manual oleh Pemilik Akun)
Karena status kelayakan affiliate tidak tersedia melalui TikTok Display API standar, pemilik akun `@plant.coaction` wajib memeriksa langsung di aplikasi TikTok / **TikTok Shop Creator Center**:
- Status keikutsertaan Program TikTok Shop Affiliate untuk wilayah Indonesia.
- Persyaratan kelayakan terbaru akun (jumlah follower, usia akun, aktivitas konten, kepatuhan kebijakan — dapat berbeda per akun dan kebijakan wilayah).
- Verifikasi identitas resmi & penautan rekening bank lokal untuk pencairan komisi.
- Ketersediaan fitur **Product Marketplace** dan **Showcase**.

## 3. Cara Mengaktifkan Akses TikTok Shop Affiliate API (Jika Disetujui)
1. Daftar akun developer/partner di `https://partner.tiktokshop.com/`.
2. Buat aplikasi kategori Affiliate Creator / Partner sesuai wilayah **Indonesia (ID)**.
3. Ajukan izin resmi yang relevan (mis. *Read Creator Affiliate Collaborations* untuk *Creator Search Open Collaboration Product*).
4. Setelah disetujui dan mendapatkan token otorisasi kreator (`https://shop.tiktok.com/alliance/creator/auth`), isi kredensial pada `.env` lokal:
   ```env
   TIKTOK_SHOP_APP_KEY=
   TIKTOK_SHOP_APP_SECRET=
   TIKTOK_SHOP_ACCESS_TOKEN=
   TIKTOK_SHOP_API_BASE_URL=https://open-api.tiktokglobalshop.com
   TIKTOK_SHOP_REGION=ID
   ```
5. Selama kredensial tersebut belum tersedia, halaman `/dashboard/affiliate/products` secara jujur menampilkan status **"Menunggu akses TikTok Shop Affiliate API"** tanpa scraping dan tanpa membuat data produk palsu.
