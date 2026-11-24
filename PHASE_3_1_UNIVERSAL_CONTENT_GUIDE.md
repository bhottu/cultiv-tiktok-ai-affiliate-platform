# PHASE 3.1 — PANDUAN PENGGUNAAN UNIVERSAL AFFILIATE CONTENT ENGINE

Proyek: `affiliate-ai-agentic-tiktok` (`F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok`)  
Akun Publikasi Target: `@plant.coaction` (mendukung promosi produk lintas kategori TikTok Shop)

---

## 1. Memahami Sistem Kategori Universal

Sistem menyediakan **15 kategori utama TikTok Shop** beserta **59 subkategori** yang aktif di seluruh modul (Riset Produk, Analisis AI, Script Generator, Visual Asset Manager, Video Editor, dan Content Library):

- Electronics & Gadgets
- Fashion & Apparel
- Beauty & Skincare
- Health & Personal Care
- Home & Living
- Kitchen & Household
- Automotive & Accessories
- Sports & Outdoor
- Baby & Kids
- Pet Supplies
- Food & Beverage
- Books & Education
- Toys & Hobbies
- Plants & Gardening
- Other / General

**Menambah Kategori Tanpa Ubah Kode:** Pada halaman **AI Content Research** (`/dashboard/content/research`), gunakan formulir **Tambah Kategori / Subkategori Baru** di bagian bawah kolom kiri. Kategori baru langsung muncul di seluruh dropdown sistem.

---

## 2. Alur Produksi Konten Affiliate Lintas Kategori

### Langkah 1 — Input Produk (API Resmi atau Manual) (`/dashboard/affiliate/products`)
1. Jika TikTok Shop Affiliate API belum disetujui, klik **+ Tambah Produk Manual**.
2. Isi informasi yang benar-benar Anda ketahui (hanya **Nama Produk** yang wajib). Field kosong akan secara jujur ditandai AI sebagai *informasi belum tersedia*.
3. Sistem memberi label sumber data yang transparan:
   - `API Resmi TikTok Shop` (`official_api`)
   - `Input Manual` (`manual_input`)
   - `Analisis AI` (`ai_analysis`)
   - `Belum Diverifikasi` (`unverified`)
4. Jalankan **Analisis AI** untuk melihat relevansi kategori, keunggulan, keterbatasan, dan skor kurasi objektif, lalu ubah status persetujuan produk (`Discovered` → `Analyzed` → `Pending Approval` → `Approved` / `Rejected` / `Archived`).

### Langkah 2 — Universal AI Product Research (`/dashboard/content/research`)
1. Pilih produk tersimpan atau masukkan detail produk secara manual (kategori, subkategori, deskripsi, keunggulan, harga, komisi, URL referensi, foto produk opsional).
2. Pilih **Mode Kejujuran Konten**:
   - **Research-Based (Belum Pernah Mencoba):** AI menghasilkan sudut pandang edukasi, fitur, perbandingan, dan hal yang perlu dicek sebelum membeli. AI dilarang menulis frasa pengalaman pribadi palsu seperti *"Saya sudah mencoba produk ini"*.
   - **First-Hand Experience (Pernah Mencoba):** Isi pengalaman pemakaian nyata, kelebihan yang dirasakan, kekurangan yang ditemukan, dan hasil yang bisa ditampilkan. AI hanya boleh menggunakan pengalaman nyata yang Anda berikan.
3. Klik **Analisis Produk & Generate Ide AI** untuk mendapatkan **analisis terstruktur 15 poin** + ide konten TikTok siap pakai.

### Langkah 3 — Universal AI Script Generator & Voiceover (`/dashboard/content/scripts`)
1. Pilih dari **14 Format Konten** (`Product Review`, `Product Introduction`, `Unboxing`, `Problem & Solution`, `Product Comparison`, `Top Recommendations`, `Educational Content`, `Product Showcase`, `Features & Benefits`, `Best Deal / Product Highlights`, `Buying Guide`, `Storytelling`, `Frequently Asked Questions`, `Tutorial / How to Use`).
2. Pilih **Marketing Angle** (`Auto` atau 10 angle strategis), **Gaya Narasi**, **Durasi** (`15/30/45/60/90` detik), dan **Mode Kejujuran**.
3. Edit manual atau gunakan **Regenerate Bagian Tertentu** (misalnya hanya `Hook`) tanpa mengubah bagian lain.
4. Klik **Generate VO** untuk menghasilkan voiceover Bahasa Indonesia via **Google Gemini TTS** (atau Google Cloud TTS), lalu klik **Lanjutkan ke Video Editor →**.

### Langkah 4 — Visual Asset Manager (`/dashboard/content/assets`)
- Unggah foto produk, detail tekstur/spesifikasi, klip demo, klip unboxing, lifestyle shot, atau musik latar legal.
- Kelompokkan dan filter aset berdasarkan **Produk**, **Campaign**, **Kategori**, dan **Jenis Media**.

### Langkah 5 — Video Editor & 10 Template Universal Remotion (`/dashboard/content/editor/{id}`)
1. Pilih 1 dari **10 Template Universal**:
   - `ProductShowcase`, `ProductFeatures`, `ProductComparison`, `TopRecommendations`, `ProblemSolution`, `ProductReview`, `EducationalExplainer`, `UnboxingStyle`, `DealHighlights`, `Storytelling`.
   - *(Konten lama Fase 3 yang menggunakan `PlantEducation` / `PlantShowcase` tetap 100% kompatibel).*
2. Pilih **Tema Visual Kategori** (10 preset warna seperti `Tech Dark`, `Editorial Warm`, `Soft Rose`, `Warm Sand`, `Carbon Crimson`, `Botanical Green`, `Neutral Studio`). Visual tanaman tidak lagi muncul pada produk non-tanaman.
3. Pasangkan aset visual per scene (jika kosong, placeholder studio bersih sesuai kategori otomatis digunakan), periksa subtitle, atur volume voiceover/musik, simpan draft, lalu klik **Render Video (Remotion)**.

### Langkah 6 — Review, Edit Metadata, Approval & Export MP4 (`/dashboard/content`)
1. Filter konten berdasarkan **Kategori Produk**, **Produk**, **Format Konten**, **Template Video**, **Campaign**, **Status Pipeline**, atau **Status Approval**.
2. Klik **Review** untuk memutar video MP4, mengedit langsung **Caption**, **Hashtag**, dan **Affiliate Disclosure**, lalu klik **Setujui (Approve)** dan **Download / Export MP4** untuk diunggah manual ke TikTok.
