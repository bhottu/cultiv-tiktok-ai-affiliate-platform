# GEMINI TTS API RESEARCH — Google Gemini Developer API (Speech Generation)

> **Proyek:** `F:\PROMT-ENGINEER\affiliate-ai-agentic-tiktok`  
> **Tanggal Riset:** 2026-11-20 (WIB)  
> **Sumber Resmi:**
> - `https://ai.google.dev/gemini-api/docs/speech-generation`
> - `https://ai.google.dev/gemini-api/docs/pricing`
> - `https://ai.google.dev/gemini-api/docs/models`
> - `https://aistudio.google.com/apikey`

---

## 1. Perbedaan Layanan: Gemini Developer API TTS vs Google Cloud Text-to-Speech

| Aspek | Google Gemini TTS (Gemini Developer API) | Google Cloud Text-to-Speech |
|---|---|---|
| **Endpoint Resmi** | `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` | `https://texttospeech.googleapis.com/v1/text:synthesize` |
| **Autentikasi** | Gemini API Key dari Google AI Studio (`x-goog-api-key`) — **berbagi key yang sama dengan Gemini AI Provider** | Google Cloud API Key / Service Account |
| **Kebutuhan Google Cloud Billing** | **Tidak mewajibkan Google Cloud Billing Account** untuk akses Free Tier Google AI Studio (tunduk pada kuota & kebijakan wilayah) | Umumnya memerlukan proyek Google Cloud dengan Billing aktif |
| **Kontrol Gaya & Tempo** | Melalui instruksi bahasa alami (*style prompt*) di prompt input; **tidak** memakai slider numerik `pitch`/`volumeGainDb` | Parameter numerik (`speakingRate`, `pitch`, `volumeGainDb`) |
| **Format Audio Mentah** | Umumnya raw linear PCM 16-bit (`audio/L16;codec=pcm;rate=24000`, mono 24 kHz) tanpa header container → **wajib konversi FFmpeg ke WAV/MP3** | Langsung MP3 / LINEAR16 / OGG |

---

## 2. Model Gemini TTS Resmi yang Diverifikasi

> **Aturan Ketat:** Model generasi teks Gemini standar (mis. `gemini-2.5-flash` biasa atau model embedding) **tidak** digunakan sebagai model TTS. Hanya model khusus speech generation (`-tts`) yang diizinkan.

| Model ID Resmi | Karakteristik & Kapasitas | Catatan Akses |
|---|---|---|
| `gemini-2.5-flash-preview-tts` | Model TTS utama cepat & hemat biaya. Batas input **8.192 token**, batas output **16.384 audio token** (32 token/detik ≈ maks. ~8,3 menit audio/turn). | Tersedia di Gemini Developer API (Free Tier memiliki batas RPM/RPD ketat; Paid Tier ~\$0,50/1M token input & ~\$10,00/1M token output audio). |
| `gemini-2.5-pro-preview-tts` | Model TTS kualitas tinggi dengan kontrol prosodi & ekspresi lebih mendalam. | Ketersediaan Free Tier dan rate limit lebih ketat; bergantung pada wilayah & status proyek Google AI Studio. |

*(Catatan: Ketersediaan Free Tier, RPM, dan RPD dapat berubah sewaktu-waktu sesuai kebijakan Google. Tidak ada jaminan "gratis selamanya".)*

---

## 3. Format Request & Response Resmi

### Request (`POST /v1beta/models/{model}:generateContent`, Header `x-goog-api-key`)
```json
{
  "contents": [
    {
      "parts": [
        {
          "text": "Ucapkan dengan gaya ramah, natural, dan jelas dalam Bahasa Indonesia:\nHalo, selamat datang di Plant Coaction."
        }
      ]
    }
  ],
  "generationConfig": {
    "responseModalities": ["AUDIO"],
    "speechConfig": {
      "voiceConfig": {
        "prebuiltVoiceConfig": {
          "voiceName": "Kore"
        }
      }
    }
  }
}
```

### Response Audio
- Field: `candidates[0].content.parts[0].inlineData`
  - `mimeType`: umumnya `audio/L16;codec=pcm;rate=24000` (PCM 16-bit signed little-endian, 24000 Hz, mono).
  - `data`: string Base64 berisi raw bytes audio.
- Karena PCM mentah tidak dapat langsung diputar di `<audio>` browser dan membutuhkan container standar untuk Remotion, backend wajib:
  1. Mendekode & memvalidasi Base64.
  2. Mengonversi dengan **FFmpeg** (`-f s16le -ar 24000 -ac 1`) menjadi **WAV** (master Remotion) dan **MP3** (preview browser).
  3. Memverifikasi durasi & integritas stream melalui `ffprobe`.

---

## 4. Prebuilt Voices Resmi & Dukungan Bahasa Indonesia (`id-ID`)

Gemini TTS mendukung 70+ bahasa termasuk **Bahasa Indonesia (`id-ID`)** dengan pelafalan dan intonasi natural. Berikut daftar suara *prebuilt* resmi yang diprioritaskan untuk konten edukasi & promosi tanaman hias Plant Coaction:

| Voice ID Resmi | Karakter Resmi | Rekomendasi Penggunaan Plant Coaction |
|---|---|---|
| `Kore` | Firm (Tegas & jernih) | Review produk & edukasi teknis media tanam |
| `Charon` | Informative (Informatif & tenang) | Tutorial perawatan Anthurium & aroid |
| `Aoede` | Breezy (Santai & natural) | Storytelling & demonstrasi ringan |
| `Zephyr` | Bright (Cerah & ramah) | Pembukaan / hook edukasi pemula |
| `Puck` | Upbeat (Ceria & energik) | Hook promosi & sorotan produk affiliate |
| `Sulafat` | Warm (Hangat & bersahabat) | Narasi tips harian & interaksi komunitas |
| `Leda` | Youthful (Segar & ringan) | Konten singkat 15–30 detik |
| `Fenrir` | Excitable (Dinamis & antusias) | Highlight fitur alat greenhouse/berkebun |
| `Orus` | Firm (Mantap & jelas) | Penekanan poin penting & CTA |

---

## 5. Batasan & Keterbatasan yang Ditemukan
1. **Batas Input Teks:** Aplikasi membatasi input sintesis (mis. maks. **3.500 karakter** per request untuk voiceover pendek TikTok 15–60 detik, dan maks. **600 karakter** untuk preview) agar tidak melampaui batas token maupun memboroskan kuota.
2. **Tidak Mendukung Numeric Pitch/Volume API Field:** Gemini TTS mengatur ekspresi dan kecepatan lewat *style prompt* bahasa alami, bukan field numerik `pitch`/`volumeGainDb` seperti Google Cloud TTS.
3. **Rate Limit & Quota Exceeded (`HTTP 429 / RESOURCE_EXHAUSTED`):** Free Tier memiliki batas RPM & RPD ketat; aplikasi **tidak** melakukan retry pada `400/401/403/429` dan menampilkan pesan error yang transparan.
4. **Privasi Free Tier:** Pada Free Tier Google AI Studio, konten dapat digunakan oleh Google sesuai ketentuan layanan Free Tier; jangan mengirim kredensial atau data rahasia dalam teks narasi.
