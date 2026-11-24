# GEMINI TTS SETUP — Panduan Konfigurasi & Pengujian Suara Bahasa Indonesia

## 1. Prasyarat
- **Gemini API Key resmi** dari Google AI Studio: `https://aistudio.google.com/apikey`
  *(Menggunakan ulang API Key yang sama dengan Gemini AI Provider — **tidak perlu** memasukkan ulang API Key baru dan **tidak memerlukan Google Cloud Billing Account** untuk akses Free Tier Google AI Studio, tunduk pada kuota & wilayah Google).*
- **FFmpeg & FFprobe** tersedia di sistem Windows (digunakan untuk mengonversi PCM 24 kHz menjadi Master WAV untuk Remotion dan Preview MP3 untuk browser).

## 2. Langkah Konfigurasi di Aplikasi
1. Jika belum mengatur Gemini API Key, buka menu **AI Provider (OpenRouter / Gemini)** → tab **2. Google Gemini**, simpan API Key Anda secara terenkripsi.
2. Buka menu **Text-to-Speech (Gemini / Cloud)** (`/settings/tts`).
3. Pada bar atas, pastikan **Provider TTS Utama** diset ke **Google Gemini TTS (Gemini Developer API)** lalu klik **Simpan Provider Utama**.
4. Pada tab **1. Google Gemini TTS**:
   - Pilih **Model Gemini TTS Resmi**: `gemini-2.5-flash-preview-tts` *(disarankan untuk produksi harian)* atau `gemini-2.5-pro-preview-tts`.
   - Pilih **Voice Prebuilt Resmi** (mis. `Kore`, `Charon`, `Aoede`, `Zephyr`, `Puck`, `Sulafat`).
   - Pilih **Tempo Bicara** (`Pelan`, `Normal`, atau `Sedikit Cepat`) dan sesuaikan **Instruksi Gaya Bicara** (style prompt).
   - Klik **Simpan Pengaturan Gemini TTS**.

## 3. Menguji Koneksi & Preview Suara
1. Klik **Test Connection** untuk memverifikasi akses API Key dan ketersediaan model TTS pada akun/wilayah Anda.
2. Pada panel kanan **Preview Suara**, gunakan teks default Bahasa Indonesia:
   > *"Halo, selamat datang di Plant Coaction. Hari ini kita akan membahas cara merawat tanaman hias agar tumbuh sehat dan subur."*
3. Klik **Generate Preview Voice** (tombol otomatis terkunci selama proses agar tidak terjadi request ganda).
4. Dengarkan hasil audio langsung pada pemutar browser, periksa durasi & sample rate, atau klik **Unduh MP3** jika diperlukan.

## 4. Konfigurasi `.env` Opsional (Tanpa Secret di Git)
```env
TTS_DEFAULT_PROVIDER=google_gemini_tts
GEMINI_TTS_MODEL=gemini-2.5-flash-preview-tts
GEMINI_TTS_VOICE=Kore
GEMINI_TTS_TIMEOUT=45
GEMINI_TTS_MAX_RETRIES=1
```

## 5. Keterbatasan Penting
- Free Tier Gemini TTS memiliki batas RPM/RPD ketat; jika muncul `HTTP 429 / RESOURCE_EXHAUSTED`, tunggu jendela kuota berikutnya atau aktifkan billing di Google AI Studio.
- Pengaturan numerik `pitch` dan `volumeGainDb` hanya berlaku pada **Google Cloud TTS**, bukan pada Gemini TTS (yang menggunakan *style prompt*).
- File preview sementara disimpan di `storage/app/tts/previews/` (privat, terproteksi otorisasi user) dan dibersihkan otomatis setelah 24 jam oleh scheduler.
