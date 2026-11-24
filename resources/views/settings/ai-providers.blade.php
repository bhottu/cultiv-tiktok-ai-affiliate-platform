@extends('layouts.app')

@section('title', 'Pengaturan AI Provider')
@section('header', 'Pengaturan AI Provider — OpenRouter & Google Gemini')
@section('subheader', 'Kelola provider AI resmi, enkripsi API Key, model discovery, dan fallback terkontrol')

@section('content')
@php
    $orRec = $openRouter['record'];
    $gemRec = $gemini['record'];
    $statusBadge = fn (string $st) => match($st) {
        'connected' => ['badge-ok', 'Terhubung'],
        'failed' => ['badge-err', 'Gagal'],
        'configured_untested' => ['badge-warn', 'Belum Diuji'],
        default => ['badge-muted', 'Belum Dikonfigurasi'],
    };
    [$orBadgeClass, $orBadgeText] = $statusBadge($orRec->connection_status);
    [$gemBadgeClass, $gemBadgeText] = $statusBadge($gemRec->connection_status);
@endphp

<div x-data="{ tab: '{{ $activeTab }}' }" class="space-y-4">
    <!-- Compact Multi-Provider Routing & Fallback Bar -->
    <div class="panel p-4">
        <form method="POST" action="{{ route('settings.ai.routing') }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-wrap">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Default AI Provider</label>
                    <select name="default_provider" class="mt-1 rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                        <option value="openrouter" @selected($defaultProviderSlug === 'openrouter')>OpenRouter</option>
                        <option value="google_gemini" @selected($defaultProviderSlug === 'google_gemini')>Google Gemini</option>
                    </select>
                </div>
                <label class="inline-flex items-start gap-2 text-xs text-slate-700 sm:mt-4">
                    <input type="checkbox" name="fallback_enabled" value="1" @checked($fallbackEnabled)
                           class="mt-0.5 rounded border-slate-300 text-forest-700 focus:ring-forest-600">
                    <span>
                        <strong>Fallback Otomatis (Opsional)</strong><br>
                        <span class="text-slate-500">Jika provider utama gagal, coba 1x ke provider alternatif yang aktif &amp; catat alasan di Log Aktivitas.</span>
                    </span>
                </label>
            </div>
            <button type="submit" class="btn-primary flex-shrink-0">Simpan Routing AI</button>
        </form>
    </div>

    <!-- Compact Provider Switcher (2 Provider Jelas Terpisah) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button type="button" @click="tab = 'openrouter'"
                :class="tab === 'openrouter' ? 'border-forest-600 ring-1 ring-forest-600 bg-white' : 'border-slate-200 bg-white/70 hover:bg-white'"
                class="text-left rounded-xl border p-4 transition flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-900">1. OpenRouter</span>
                    @if ($defaultProviderSlug === 'openrouter')
                        <span class="badge-ok">Default</span>
                    @endif
                </div>
                <div class="text-xs text-slate-500 mt-1 truncate">
                    Model aktif: <span class="font-mono text-slate-700">{{ $openRouter['defaultModel']?->model_id ?? 'Belum dipilih' }}</span>
                </div>
            </div>
            <span class="{{ $orBadgeClass }} flex-shrink-0">{{ $orBadgeText }}</span>
        </button>

        <button type="button" @click="tab = 'google_gemini'"
                :class="tab === 'google_gemini' ? 'border-forest-600 ring-1 ring-forest-600 bg-white' : 'border-slate-200 bg-white/70 hover:bg-white'"
                class="text-left rounded-xl border p-4 transition flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-900">2. Google Gemini</span>
                    @if ($defaultProviderSlug === 'google_gemini')
                        <span class="badge-ok">Default</span>
                    @endif
                </div>
                <div class="text-xs text-slate-500 mt-1 truncate">
                    Model aktif: <span class="font-mono text-slate-700">{{ $gemini['defaultModel']?->model_id ?? 'Belum dipilih' }}</span>
                </div>
            </div>
            <span class="{{ $gemBadgeClass }} flex-shrink-0">{{ $gemBadgeText }}</span>
        </button>
    </div>

    <!-- ================= TAB 1: OPENROUTER ================= -->
    <div x-show="tab === 'openrouter'" x-cloak x-data="openRouterPanel()" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-5 panel p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Konfigurasi OpenRouter</h2>
                    <p class="text-xs text-slate-500">API key terenkripsi (AES-256-CBC) dan tidak pernah ditampilkan penuh.</p>
                </div>
                <span class="{{ $orBadgeClass }}">{{ $orBadgeText }}</span>
            </div>

            <form method="POST" action="{{ route('settings.openrouter.update') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">OpenRouter API Key</label>
                    <input type="password" name="api_key" autocomplete="new-password"
                           placeholder="{{ $openRouter['hasKey'] ? 'Tersimpan aman (' . ($openRouter['maskedKey'] ?? 'via .env') . ') — isi untuk mengganti' : 'sk-or-v1-...' }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                    @if ($openRouter['hasKey'])
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 mt-1.5">
                            <input type="checkbox" name="clear_api_key" value="1" class="rounded border-slate-300 text-rose-600">
                            Hapus API key tersimpan di database
                        </label>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Base URL</label>
                        <input type="url" name="base_url" value="{{ old('base_url', $orRec->base_url ?: 'https://openrouter.ai/api/v1') }}" required
                               class="w-full rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Timeout (dtk)</label>
                        <input type="number" name="timeout" min="5" max="120" value="{{ old('timeout', $orRec->settings['timeout'] ?? 30) }}" required
                               class="w-full rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600">
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="btn-primary text-xs">Simpan OpenRouter</button>
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

            <div class="border-t border-slate-100 pt-3 text-xs space-y-1.5">
                <div class="flex justify-between"><span class="text-slate-500">Model Default</span><span class="font-mono font-medium">{{ $openRouter['defaultModel']?->model_id ?? 'Belum dipilih' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Model Cadangan</span><span class="font-mono font-medium">{{ $openRouter['fallbackModel']?->model_id ?? 'Belum dipilih' }}</span></div>
            </div>
        </div>

        <div class="lg:col-span-7 panel p-5 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Daftar Model OpenRouter</h2>
                    <p class="text-xs text-slate-500">Cari &amp; filter model gratis/berbayar dari API resmi OpenRouter.</p>
                </div>
                <button type="button" @click="fetchModels(true)" :disabled="loadingModels" class="btn-secondary text-xs">
                    <span x-show="!loadingModels">Refresh Models</span>
                    <span x-show="loadingModels" x-cloak>Memuat...</span>
                </button>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="text" x-model="search" @input.debounce.300ms="fetchModels(false)" placeholder="Cari model OpenRouter..."
                       class="flex-1 rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600">
                <select x-model="tier" @change="fetchModels(false)" class="rounded-lg border-slate-300 text-xs">
                    <option value="all">Semua Model</option>
                    <option value="free">Gratis (:free / $0)</option>
                    <option value="paid">Berbayar</option>
                </select>
            </div>
            <template x-if="modelsError">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" x-text="modelsError"></div>
            </template>
            <template x-if="!loadingModels && models.length === 0 && !modelsError">
                <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                    Simpan API key OpenRouter lalu klik <strong>Refresh Models</strong> untuk memuat katalog model resmi.
                </div>
            </template>
            <div x-show="models.length > 0" x-cloak class="border border-slate-200 rounded-lg divide-y divide-slate-100 max-h-[380px] overflow-y-auto">
                <template x-for="m in models" :key="m.id">
                    <div class="p-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-mono text-xs font-semibold text-slate-900 truncate" x-text="m.id"></span>
                                <span x-show="m.is_free" class="badge-ok">Gratis</span>
                                <span x-show="!m.is_free" class="badge-muted">Berbayar</span>
                            </div>
                            <div class="text-[11px] text-slate-500 truncate" x-text="m.name"></div>
                        </div>
                        <div class="flex gap-1.5 flex-shrink-0">
                            <form method="POST" action="{{ route('settings.openrouter.select-model') }}">
                                @csrf
                                <input type="hidden" name="model_id" :value="m.id">
                                <input type="hidden" name="display_name" :value="m.name">
                                <input type="hidden" name="is_free" :value="m.is_free ? 1 : 0">
                                <input type="hidden" name="role_type" value="default">
                                <button type="submit" class="btn-primary !px-2.5 !py-1 text-xs">Default</button>
                            </form>
                            <form method="POST" action="{{ route('settings.openrouter.select-model') }}">
                                @csrf
                                <input type="hidden" name="model_id" :value="m.id">
                                <input type="hidden" name="display_name" :value="m.name">
                                <input type="hidden" name="is_free" :value="m.is_free ? 1 : 0">
                                <input type="hidden" name="role_type" value="fallback">
                                <button type="submit" class="btn-secondary !px-2.5 !py-1 text-xs">Fallback</button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ================= TAB 2: GOOGLE GEMINI ================= -->
    <div x-show="tab === 'google_gemini'" x-cloak x-data="geminiPanel()" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-5 space-y-4">
            <div class="panel p-5 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Konfigurasi Google Gemini Developer API</h2>
                        <p class="text-xs text-slate-500">
                            Dapatkan API Key resmi di
                            <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" class="text-forest-700 font-medium underline">Google AI Studio</a>.
                        </p>
                    </div>
                    <span class="{{ $gemBadgeClass }}">{{ $gemBadgeText }}</span>
                </div>

                <!-- Pemisahan tegas Antigravity vs Gemini API -->
                <div class="rounded-lg border border-sky-200 bg-sky-50/70 px-3 py-2.5 text-[11px] text-sky-900 leading-relaxed">
                    <strong>Catatan Penting:</strong> Langganan <strong>Google Antigravity</strong> terpisah dari <strong>Gemini Developer API</strong> dan tidak otomatis menyediakan kuota API Key. Gunakan API Key resmi dari <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" class="underline font-medium">aistudio.google.com/apikey</a>. Aplikasi tidak pernah meminta password Google atau token sesi Antigravity.
                </div>

                <form method="POST" action="{{ route('settings.gemini.update') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Gemini API Key (Terenkripsi)</label>
                        <input type="password" name="api_key" autocomplete="new-password"
                               placeholder="{{ $gemini['hasKey'] ? 'Tersimpan aman (' . ($gemini['maskedKey'] ?? 'via .env') . ') — isi untuk Update API Key' : 'AIza...' }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Base URL Resmi</label>
                        <input type="url" name="base_url" value="{{ old('base_url', $gemRec->base_url ?: 'https://generativelanguage.googleapis.com/v1beta') }}" required
                               class="w-full rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600">
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-700 mb-1">Temperature</label>
                            <input type="number" step="0.1" min="0" max="2" name="temperature" value="{{ old('temperature', $gemRec->settings['temperature'] ?? 0.7) }}" required
                                   class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-700 mb-1">Max Tokens</label>
                            <input type="number" min="128" max="65536" name="max_output_tokens" value="{{ old('max_output_tokens', $gemRec->settings['max_output_tokens'] ?? 2048) }}" required
                                   class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-700 mb-1">Timeout (dtk)</label>
                            <input type="number" min="5" max="120" name="timeout" value="{{ old('timeout', $gemRec->settings['timeout'] ?? 30) }}" required
                                   class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-700 mb-1">Max Retry</label>
                            <input type="number" min="0" max="2" name="max_retries" value="{{ old('max_retries', $gemRec->settings['max_retries'] ?? 1) }}" required
                                   class="w-full rounded-lg border-slate-300 text-xs">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="submit" class="btn-primary text-xs">{{ $gemini['hasKey'] ? 'Update API Key & Pengaturan' : 'Simpan Gemini API Key' }}</button>
                        <button type="button" @click="testConnection()" :disabled="testing" class="btn-secondary text-xs">
                            <span x-show="!testing">Test Connection</span>
                            <span x-show="testing" x-cloak>Menguji...</span>
                        </button>
                    </div>
                </form>

                @if ($gemini['hasKey'])
                    <form method="POST" action="{{ route('settings.gemini.remove-key') }}" onsubmit="return confirm('Hapus Gemini API Key yang tersimpan?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-700">Remove API Key</button>
                    </form>
                @endif

                <template x-if="feedbackMessage">
                    <div :class="feedbackOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
                         class="rounded-lg border px-3 py-2 text-xs" x-text="feedbackMessage"></div>
                </template>

                <div class="border-t border-slate-100 pt-3 text-xs flex justify-between">
                    <span class="text-slate-500">Model Default Gemini</span>
                    <span class="font-mono font-medium text-slate-900">{{ $gemini['defaultModel']?->model_id ?? 'Belum dipilih' }}</span>
                </div>
            </div>

            <!-- Riwayat Pengujian Koneksi Gemini -->
            <div class="panel p-4 space-y-2.5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Riwayat Pengujian Koneksi</h3>
                @if (empty($gemini['testHistory']))
                    <p class="text-xs text-slate-400">Belum ada riwayat pengujian koneksi Google Gemini.</p>
                @else
                    <ul class="divide-y divide-slate-100 text-xs">
                        @foreach (array_slice($gemini['testHistory'], 0, 5) as $item)
                            <li class="py-2 space-y-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="{{ ($item['status'] ?? '') === 'connected' ? 'badge-ok' : 'badge-err' }}">{{ $item['status'] ?? '-' }}</span>
                                    <span class="text-slate-400">{{ $item['tested_at_wib'] ?? '' }}</span>
                                </div>
                                <p class="text-slate-600">{{ $item['message'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <!-- Right: Gemini Official Model Discovery -->
        <div class="lg:col-span-7 panel p-5 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Daftar Model Resmi Google Gemini</h2>
                    <p class="text-xs text-slate-500">Hanya model dengan dukungan <code>generateContent</code> yang dapat dipilih untuk generasi teks.</p>
                </div>
                <button type="button" @click="fetchModels(true)" :disabled="loadingModels" class="btn-secondary text-xs">
                    <span x-show="!loadingModels">Refresh Models</span>
                    <span x-show="loadingModels" x-cloak>Memuat...</span>
                </button>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
                <input type="text" x-model="search" @input.debounce.300ms="fetchModels(false)"
                       placeholder="Cari model Gemini (mis. gemini-2.5-flash, gemini-2.5-pro)..."
                       class="flex-1 rounded-lg border-slate-300 text-xs focus:border-forest-600 focus:ring-forest-600">
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 px-2">
                    <input type="checkbox" x-model="onlyGenerate" @change="fetchModels(false)" class="rounded border-slate-300 text-forest-700">
                    Hanya generateContent
                </label>
            </div>

            <template x-if="modelsError">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800 flex items-center justify-between gap-2">
                    <span x-text="modelsError"></span>
                    <button type="button" @click="fetchModels(true)" class="underline font-semibold flex-shrink-0">Coba Refresh</button>
                </div>
            </template>

            <template x-if="!loadingModels && models.length === 0 && !modelsError">
                <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                    Simpan Gemini API Key lalu klik <strong>Refresh Models</strong> untuk mengambil daftar model dari Google Gemini Developer API.
                </div>
            </template>

            <div x-show="models.length > 0" x-cloak class="border border-slate-200 rounded-lg divide-y divide-slate-100 max-h-[430px] overflow-y-auto">
                <template x-for="m in models" :key="m.id">
                    <div class="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-mono text-xs font-semibold text-slate-900 truncate" x-text="m.id"></span>
                                <span x-show="m.supports_text_generation" class="badge-ok">generateContent</span>
                                <span x-show="!m.supports_text_generation" class="badge-muted">Non-Text</span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5 truncate"
                                 x-text="m.name + (m.input_token_limit ? ' · Input: ' + m.input_token_limit.toLocaleString() : '') + (m.output_token_limit ? ' · Output: ' + m.output_token_limit.toLocaleString() : '')"></div>
                        </div>
                        <div class="flex-shrink-0">
                            <form x-show="m.supports_text_generation" method="POST" action="{{ route('settings.gemini.select-model') }}">
                                @csrf
                                <input type="hidden" name="model_id" :value="m.id">
                                <input type="hidden" name="display_name" :value="m.name">
                                <input type="hidden" name="input_token_limit" :value="m.input_token_limit || ''">
                                <input type="hidden" name="output_token_limit" :value="m.output_token_limit || ''">
                                <input type="hidden" name="supported_methods" :value="(m.supported_generation_methods || []).join(',')">
                                <button type="submit" class="btn-primary !px-2.5 !py-1 text-xs">Jadikan Default</button>
                            </form>
                            <span x-show="!m.supports_text_generation" class="text-[11px] text-slate-400">Tidak didukung untuk teks</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function openRouterPanel() {
    return {
        testing: false, loadingModels: false, feedbackOk: false, feedbackMessage: '',
        models: [], modelsError: '', search: '', tier: 'all',
        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        async testConnection() {
            this.testing = true; this.feedbackMessage = '';
            try {
                const res = await fetch('{{ route('settings.openrouter.test') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                const data = await res.json();
                this.feedbackOk = !!data.ok; this.feedbackMessage = data.message || 'Pengujian selesai.';
                if (data.ok) this.fetchModels(false);
            } catch (e) { this.feedbackOk = false; this.feedbackMessage = 'Kesalahan jaringan saat menguji OpenRouter.'; }
            finally { this.testing = false; }
        },
        async fetchModels(refresh) {
            this.loadingModels = true; this.modelsError = '';
            try {
                const params = new URLSearchParams({ search: this.search, tier: this.tier, refresh: refresh ? '1' : '0' });
                const res = await fetch('{{ route('settings.openrouter.models') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.ok) { this.models = []; this.modelsError = data.message || 'Gagal memuat daftar model.'; }
                else { this.models = data.models || []; }
            } catch (e) { this.modelsError = 'Gagal menghubungi server lokal.'; }
            finally { this.loadingModels = false; }
        }
    };
}

function geminiPanel() {
    return {
        testing: false, loadingModels: false, feedbackOk: false, feedbackMessage: '',
        models: [], modelsError: '', search: '', onlyGenerate: true,
        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        async testConnection() {
            this.testing = true; this.feedbackMessage = '';
            try {
                const res = await fetch('{{ route('settings.gemini.test') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                const data = await res.json();
                this.feedbackOk = !!data.ok; this.feedbackMessage = data.message || 'Pengujian selesai.';
                if (data.ok) this.fetchModels(false);
            } catch (e) { this.feedbackOk = false; this.feedbackMessage = 'Kesalahan jaringan saat menguji Google Gemini.'; }
            finally { this.testing = false; }
        },
        async fetchModels(refresh) {
            this.loadingModels = true; this.modelsError = '';
            try {
                const params = new URLSearchParams({ search: this.search, only_generate_content: this.onlyGenerate ? '1' : '0', refresh: refresh ? '1' : '0' });
                const res = await fetch('{{ route('settings.gemini.models') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!data.ok) { this.models = []; this.modelsError = data.message || 'Gagal memuat daftar model Gemini.'; }
                else { this.models = data.models || []; }
            } catch (e) { this.modelsError = 'Gagal menghubungi server lokal.'; }
            finally { this.loadingModels = false; }
        }
    };
}
</script>
@endsection
