@extends('layouts.app')

@section('title', 'Pengaturan Text-to-Speech')
@section('header', 'Pengaturan Text-to-Speech — Bahasa Indonesia')
@section('subheader', 'Pilih Google Gemini TTS (tanpa Google Cloud Billing) atau Google Cloud TTS melalui TextToSpeechInterface')

@section('content')
@php
    $gemMeta = $geminiTtsProfile->metadata ?? [];
    $gemModelId = $gemMeta['model_id'] ?? 'gemini-2.5-flash-preview-tts';
    $gemPace = $gemMeta['pace_preset'] ?? 'normal';
    $gemStyle = $gemMeta['style_instruction'] ?? 'Ucapkan secara natural, ramah, jelas, dan bertempo sedang dalam Bahasa Indonesia seperti praktisi tanaman hias.';
    $badgeFor = fn (string $st) => match($st) {
        'connected' => ['badge-ok', 'Terhubung'],
        'failed' => ['badge-err', 'Gagal'],
        'configured_untested' => ['badge-warn', 'Belum Diuji'],
        default => ['badge-muted', 'Belum Dikonfigurasi'],
    };
    [$gemBadgeClass, $gemBadgeText] = $badgeFor($geminiTtsProfile->connection_status);
    [$gcBadgeClass, $gcBadgeText] = $badgeFor($profile->connection_status);
@endphp

<div x-data="{ tab: '{{ $activeTab }}' }" class="space-y-4">
    <!-- Active TTS Provider Selection Bar -->
    <div class="panel p-4">
        <form method="POST" action="{{ route('settings.tts.provider') }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Provider TTS Utama (Default)</label>
                    <select name="default_tts_provider" class="mt-1 rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                        <option value="google_gemini_tts" @selected($defaultTtsSlug === 'google_gemini_tts')>Google Gemini TTS (Gemini Developer API)</option>
                        <option value="google_cloud_tts" @selected($defaultTtsSlug === 'google_cloud_tts')>Google Cloud Text-to-Speech</option>
                    </select>
                </div>
                <p class="text-xs text-slate-500 sm:mt-4 max-w-xl">
                    Pipeline video &amp; antrean voiceover otomatis memakai provider utama ini melalui <code>TextToSpeechInterface</code> tanpa fallback diam-diam ke provider yang belum dikonfigurasi.
                </p>
            </div>
            <button type="submit" class="btn-primary text-xs flex-shrink-0">Simpan Provider Utama</button>
        </form>
    </div>

    <!-- 2-Provider Switcher -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="button" @click="tab = 'google_gemini_tts'"
                :class="tab === 'google_gemini_tts' ? 'border-forest-600 ring-1 ring-forest-600 bg-white' : 'border-slate-200 bg-white/70 hover:bg-white'"
                class="text-left rounded-xl border p-4 transition flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-900">1. Google Gemini TTS</span>
                    @if ($defaultTtsSlug === 'google_gemini_tts')
                        <span class="badge-ok">Default</span>
                    @endif
                </div>
                <div class="text-xs text-slate-500 mt-1 truncate">
                    Model: <span class="font-mono text-slate-700">{{ $gemModelId }}</span> · Voice: <span class="font-semibold text-slate-700">{{ $geminiTtsProfile->voice_id ?? 'Kore' }}</span>
                </div>
            </div>
            <span class="{{ $gemBadgeClass }} flex-shrink-0">{{ $gemBadgeText }}</span>
        </button>

        <button type="button" @click="tab = 'google_cloud_tts'"
                :class="tab === 'google_cloud_tts' ? 'border-forest-600 ring-1 ring-forest-600 bg-white' : 'border-slate-200 bg-white/70 hover:bg-white'"
                class="text-left rounded-xl border p-4 transition flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-900">2. Google Cloud TTS</span>
                    @if ($defaultTtsSlug === 'google_cloud_tts')
                        <span class="badge-ok">Default</span>
                    @endif
                </div>
                <div class="text-xs text-slate-500 mt-1 truncate">
                    Voice: <span class="font-mono text-slate-700">{{ $profile->voice_id ?? 'Belum dipilih' }}</span> · {{ $profile->language_code }}
                </div>
            </div>
            <span class="{{ $gcBadgeClass }} flex-shrink-0">{{ $gcBadgeText }}</span>
        </button>
    </div>

    <!-- ================= TAB 1: GOOGLE GEMINI TTS ================= -->
    <div x-show="tab === 'google_gemini_tts'" x-cloak x-data="geminiTtsPanel()" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-7 panel p-5 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Google Gemini TTS (Gemini Developer API)</h2>
                    <p class="text-xs text-slate-500">Menggunakan ulang Gemini API Key terenkripsi dari menu AI Provider.</p>
                </div>
                <span class="{{ $gemBadgeClass }}">{{ $gemBadgeText }}</span>
            </div>

            <!-- Layanan & Free Tier Info -->
            <div class="rounded-lg border border-sky-200 bg-sky-50/70 px-3.5 py-2.5 text-[11px] text-sky-900 space-y-1 leading-relaxed">
                <div><strong>Perbedaan Layanan:</strong> Gemini Developer API TTS berbeda dari Google Cloud Text-to-Speech. Gemini TTS menggunakan API Key Google AI Studio dan <strong>tidak mewajibkan Google Cloud Billing Account</strong>.</div>
                <div><strong>Free Tier &amp; Kuota:</strong> Ketersediaan Free Tier, batas RPM/RPD, serta akses model TTS bergantung pada model, wilayah, dan ketentuan Google terbaru (bukan tanpa batas). Output audio PCM 24 kHz dikonversi otomatis oleh FFmpeg ke WAV (master Remotion) &amp; MP3 (preview).</div>
            </div>

            <!-- Shared API Key Status -->
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div>
                    <span class="text-slate-500">Status Gemini API Key (Shared):</span>
                    @if ($geminiTtsHasKey)
                        <span class="font-semibold text-emerald-700 ml-1">Tersedia &amp; Terenkripsi ({{ $geminiTtsMaskedKey ?? 'via .env' }})</span>
                    @else
                        <span class="font-semibold text-amber-700 ml-1">Belum dikonfigurasi</span>
                    @endif
                </div>
                <a href="{{ route('settings.ai.index', ['tab' => 'google_gemini']) }}" class="text-forest-700 font-medium underline">
                    {{ $geminiTtsHasKey ? 'Kelola di AI Provider' : 'Hubungkan di AI Provider →' }}
                </a>
            </div>

            <form method="POST" action="{{ route('settings.tts.gemini.update') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Model Gemini TTS Resmi</label>
                        <select name="model_id" x-model="modelId" class="w-full rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600">
                            @foreach ($geminiTtsModels as $m)
                                <option value="{{ $m['id'] }}" @selected($gemModelId === $m['id'])>{{ $m['name'] }} ({{ $m['id'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Bahasa</label>
                        <select name="language_code" class="w-full rounded-lg border-slate-300 text-xs">
                            <option value="id-ID" selected>Bahasa Indonesia (id-ID)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Voice Prebuilt Resmi</label>
                        <select name="voice_id" x-model="voiceId" class="w-full rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600">
                            @foreach ($geminiTtsVoices as $v)
                                <option value="{{ $v['name'] }}" @selected(($geminiTtsProfile->voice_id ?? 'Kore') === $v['name'])>
                                    {{ $v['name'] }} — {{ $v['trait'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Tempo Bicara (Prompt Cue)</label>
                        <select name="pace_preset" x-model="pacePreset" class="w-full rounded-lg border-slate-300 text-xs">
                            <option value="slow" @selected($gemPace === 'slow')>Pelan &amp; Tenang</option>
                            <option value="normal" @selected($gemPace === 'normal')>Normal / Sedang</option>
                            <option value="fast" @selected($gemPace === 'fast')>Sedikit Cepat &amp; Dinamis</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Instruksi Gaya Bicara / Tone (Style Prompt)</label>
                    <textarea name="style_instruction" x-model="styleInstruction" rows="2"
                              class="w-full rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600"
                              placeholder="Contoh: Ucapkan dengan ramah, jelas, dan informatif seperti praktisi tanaman hias."></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Catatan: Pengaturan numerik <code>pitch</code> dan <code>volumeGainDb</code> tidak didukung oleh Gemini TTS API; kontrol ekspresi dilakukan melalui instruksi gaya di atas.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="btn-primary text-xs">Simpan Pengaturan Gemini TTS</button>
                    <button type="button" @click="testConnection()" :disabled="testing" class="btn-secondary text-xs">
                        <span x-show="!testing">Test Connection</span>
                        <span x-show="testing" x-cloak>Menguji koneksi...</span>
                    </button>
                </div>
            </form>

            <template x-if="feedbackMessage">
                <div :class="feedbackOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
                     class="rounded-lg border px-3 py-2 text-xs" x-text="feedbackMessage"></div>
            </template>
        </div>

        <!-- Right: Voice Preview & Official Voice Guide -->
        <div class="lg:col-span-5 space-y-4">
            <div class="panel p-5 space-y-3">
                <h2 class="text-sm font-semibold text-slate-900">Preview Suara — Google Gemini TTS</h2>
                <p class="text-xs text-slate-500">Audio dihasilkan langsung dari Gemini TTS API dan dikonversi via FFmpeg (bukan audio statis).</p>
                <textarea x-model="previewText" rows="3" maxlength="600"
                          class="w-full rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600"></textarea>
                <button type="button" @click="generatePreview()" :disabled="previewing" class="btn-primary w-full text-xs">
                    <span x-show="!previewing">Generate Preview Voice</span>
                    <span x-show="previewing" x-cloak>Membuat &amp; mengonversi audio...</span>
                </button>

                <template x-if="previewError">
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800" x-text="previewError"></div>
                </template>

                <template x-if="audioSrc">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 space-y-2">
                        <audio controls :src="audioSrc" class="w-full"></audio>
                        <div class="flex items-center justify-between text-[11px] text-slate-600">
                            <span x-text="'Durasi: ' + durationSec + ' detik · ' + sampleRate + ' Hz'"></span>
                            <a :href="downloadUrl" class="text-forest-700 font-semibold underline">Unduh MP3</a>
                        </div>
                    </div>
                </template>
            </div>

            <div class="panel p-4 space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Panduan Karakter Voice Resmi</h3>
                <div class="max-h-48 overflow-y-auto divide-y divide-slate-100 text-[11px]">
                    @foreach ($geminiTtsVoices as $v)
                        <div class="py-1.5 flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">{{ $v['name'] }}</span>
                            <span class="text-slate-500 text-right">{{ $v['trait'] }} · {{ $v['recommended_for'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- ================= TAB 2: GOOGLE CLOUD TTS (DIPERTAHANKAN UTUH) ================= -->
    <div x-show="tab === 'google_cloud_tts'" x-cloak x-data="googleCloudTtsPanel()" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-7 panel p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Google Cloud Text-to-Speech (Alternatif)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Memerlukan proyek Google Cloud dengan Text-to-Speech API aktif. Kredensial terenkripsi.</p>
                </div>
                <span class="{{ $gcBadgeClass }}">{{ $gcBadgeText }}</span>
            </div>

            <form method="POST" action="{{ route('settings.tts.update') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Google Cloud TTS API Key</label>
                    <input type="password" name="api_key" autocomplete="new-password"
                           placeholder="{{ $hasCredential ? 'Tersimpan aman (' . ($maskedCredential ?? 'via .env') . ') — isi untuk mengganti' : 'AIza...' }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                    @if ($hasCredential)
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 mt-1.5">
                            <input type="checkbox" name="clear_credential" value="1" class="rounded border-slate-300 text-rose-600">
                            Hapus kredensial TTS tersimpan di database
                        </label>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Bahasa Dubbing</label>
                        <select name="language_code" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="id-ID" selected>Bahasa Indonesia (id-ID)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Voice ID (Google Cloud)</label>
                        <div class="flex gap-2">
                            <input type="text" name="voice_id" x-model="voiceId" placeholder="Klik Muat..." class="flex-1 rounded-lg border-slate-300 text-sm">
                            <button type="button" @click="loadVoices()" :disabled="loadingVoices" class="btn-secondary !px-2.5 text-xs">
                                <span x-show="!loadingVoices">Muat</span>
                                <span x-show="loadingVoices" x-cloak>...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <template x-if="voices.length > 0">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                        <div class="text-xs font-medium text-slate-700">Pilih Suara (<span x-text="voices.length"></span>):</div>
                        <select @change="voiceId = $event.target.value" class="w-full rounded-lg border-slate-300 text-xs">
                            <option value="">-- Pilih voice dari Google Cloud API --</option>
                            <template x-for="v in voices" :key="v.name">
                                <option :value="v.name" :selected="v.name === voiceId" x-text="v.name + ' (' + v.gender + ')'"></option>
                            </template>
                        </select>
                    </div>
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Speaking Rate (<span x-text="speakingRate"></span>x)</label>
                        <input type="range" name="speaking_rate" min="0.5" max="2.0" step="0.05" x-model="speakingRate" class="w-full accent-forest-700">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Pitch (<span x-text="pitch"></span>)</label>
                        <input type="range" name="pitch" min="-10" max="10" step="0.5" x-model="pitch" class="w-full accent-forest-700">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Volume (<span x-text="volumeGain"></span> dB)</label>
                        <input type="range" name="volume_gain_db" min="-12" max="12" step="1" x-model="volumeGain" class="w-full accent-forest-700">
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="btn-primary text-xs">Simpan Google Cloud TTS</button>
                    <button type="button" @click="testConnection()" :disabled="testing" class="btn-secondary text-xs">
                        <span x-show="!testing">Test Connection</span>
                        <span x-show="testing" x-cloak>Menguji...</span>
                    </button>
                </div>
            </form>

            <template x-if="feedbackMessage">
                <div :class="feedbackOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
                     class="rounded-lg border px-3 py-2 text-xs" x-text="feedbackMessage"></div>
            </template>
        </div>

        <div class="lg:col-span-5 panel p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-900">Preview Audio — Google Cloud TTS</h2>
            <textarea x-model="previewText" rows="3" class="w-full rounded-lg border-slate-300 text-xs"></textarea>
            <button type="button" @click="generatePreview()" :disabled="previewing" class="btn-secondary w-full text-xs">
                <span x-show="!previewing">Putar Preview Audio</span>
                <span x-show="previewing" x-cloak>Menghasilkan audio...</span>
            </button>
            <template x-if="previewError">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" x-text="previewError"></div>
            </template>
            <template x-if="audioSrc">
                <audio controls :src="audioSrc" class="w-full mt-2"></audio>
            </template>
        </div>
    </div>
</div>

<script>
function geminiTtsPanel() {
    return {
        modelId: @json($gemModelId),
        voiceId: @json($geminiTtsProfile->voice_id ?? 'Kore'),
        pacePreset: @json($gemPace),
        styleInstruction: @json($gemStyle),
        previewText: 'Halo, selamat datang di Plant Coaction. Hari ini kita akan membahas cara merawat tanaman hias agar tumbuh sehat dan subur.',
        testing: false, previewing: false, feedbackOk: false, feedbackMessage: '',
        previewError: '', audioSrc: '', downloadUrl: '', durationSec: 0, sampleRate: 24000,
        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),

        async testConnection() {
            if (this.testing) return;
            this.testing = true; this.feedbackMessage = '';
            try {
                const res = await fetch('{{ route('settings.tts.gemini.test') }}', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.feedbackOk = !!data.ok; this.feedbackMessage = data.message || 'Pengujian selesai.';
            } catch (e) { this.feedbackOk = false; this.feedbackMessage = 'Kesalahan jaringan saat menguji Google Gemini TTS.'; }
            finally { this.testing = false; }
        },

        async generatePreview() {
            if (this.previewing) return;
            this.previewing = true; this.previewError = ''; this.audioSrc = ''; this.downloadUrl = '';
            try {
                const res = await fetch('{{ route('settings.tts.gemini.preview') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        text: this.previewText,
                        model_id: this.modelId,
                        voice_id: this.voiceId,
                        pace_preset: this.pacePreset,
                        style_instruction: this.styleInstruction
                    })
                });
                const data = await res.json();
                if (!data.ok) {
                    this.previewError = data.message || 'Gagal menghasilkan preview suara Gemini TTS.';
                } else {
                    this.audioSrc = 'data:' + (data.mime_type || 'audio/mpeg') + ';base64,' + data.audio_base64;
                    this.downloadUrl = data.download_url || '';
                    this.durationSec = data.duration_seconds || 0;
                    this.sampleRate = data.sample_rate || 24000;
                }
            } catch (e) { this.previewError = 'Kesalahan jaringan saat memanggil preview Gemini TTS.'; }
            finally { this.previewing = false; }
        }
    };
}

function googleCloudTtsPanel() {
    return {
        voiceId: @json($profile->voice_id ?? ''),
        speakingRate: {{ (float) ($profile->speaking_rate ?? 1.0) }},
        pitch: {{ (float) ($profile->pitch ?? 0.0) }},
        volumeGain: {{ (float) ($profile->volume_gain_db ?? 0.0) }},
        testing: false, loadingVoices: false, previewing: false,
        feedbackOk: false, feedbackMessage: '', voices: [],
        previewText: 'Halo, selamat datang di Plant Coaction. Hari ini kita akan membahas cara merawat tanaman hias agar tumbuh sehat dan subur.',
        previewError: '', audioSrc: '',
        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),

        async testConnection() {
            this.testing = true; this.feedbackMessage = '';
            try {
                const res = await fetch('{{ route('settings.tts.test') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                const data = await res.json();
                this.feedbackOk = !!data.ok; this.feedbackMessage = data.message || 'Pengujian selesai.';
                if (data.ok) this.loadVoices();
            } catch (e) { this.feedbackOk = false; this.feedbackMessage = 'Kesalahan saat menguji koneksi Google Cloud TTS.'; }
            finally { this.testing = false; }
        },
        async loadVoices() {
            this.loadingVoices = true;
            try {
                const res = await fetch('{{ route('settings.tts.voices') }}', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.ok) this.voices = data.voices || [];
                else { this.feedbackOk = false; this.feedbackMessage = data.message || 'Gagal mengambil daftar suara.'; }
            } finally { this.loadingVoices = false; }
        },
        async generatePreview() {
            this.previewing = true; this.previewError = ''; this.audioSrc = '';
            try {
                const res = await fetch('{{ route('settings.tts.preview') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ text: this.previewText, voice_id: this.voiceId || null, speaking_rate: Number(this.speakingRate), pitch: Number(this.pitch), volume_gain_db: Number(this.volumeGain) })
                });
                const data = await res.json();
                if (!data.ok) this.previewError = data.message || 'Preview gagal dibuat.';
                else this.audioSrc = 'data:' + (data.mime_type || 'audio/mpeg') + ';base64,' + data.audio_base64;
            } catch (e) { this.previewError = 'Kesalahan jaringan saat membuat preview audio.'; }
            finally { this.previewing = false; }
        }
    };
}
</script>
@endsection
