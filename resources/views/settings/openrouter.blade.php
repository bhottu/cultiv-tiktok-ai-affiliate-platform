@extends('layouts.app')

@section('title', 'Pengaturan OpenRouter')
@section('header', 'Pengaturan AI Provider — OpenRouter')
@section('subheader', 'Kelola API key terenkripsi, uji koneksi, dan pilih model AI tanpa mengubah kode sumber')

@section('content')
<div x-data="openRouterPanel()" class="grid grid-cols-1 lg:grid-cols-12 gap-5">
    <!-- Left: Credential & Test Connection -->
    <div class="lg:col-span-5 space-y-4">
        <div class="panel p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Kredensial &amp; Endpoint OpenRouter</h2>
                    <p class="text-xs text-slate-500 mt-0.5">API key dienkripsi (AES-256-CBC) dan tidak pernah ditampilkan penuh.</p>
                </div>
                <span class="{{ $provider->connection_status === 'connected' ? 'badge-ok' : ($provider->connection_status === 'failed' ? 'badge-err' : 'badge-muted') }}">
                    {{ match($provider->connection_status) {
                        'connected' => 'Terhubung',
                        'failed' => 'Gagal',
                        'configured_untested' => 'Belum Diuji',
                        default => 'Belum Dikonfigurasi'
                    } }}
                </span>
            </div>

            <form method="POST" action="{{ route('settings.openrouter.update') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">OpenRouter API Key</label>
                    <input type="password" name="api_key" autocomplete="new-password"
                           placeholder="{{ $hasKey ? 'Tersimpan aman (' . ($maskedKey ?? 'via .env') . ') — isi untuk mengganti' : 'sk-or-v1-...' }}"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                    @if ($hasKey)
                        <label class="inline-flex items-center gap-2 text-xs text-slate-500 mt-1.5">
                            <input type="checkbox" name="clear_api_key" value="1" class="rounded border-slate-300 text-rose-600">
                            Hapus API key tersimpan di database
                        </label>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Base URL API</label>
                    <input type="url" name="base_url" value="{{ old('base_url', $provider->base_url ?: 'https://openrouter.ai/api/v1') }}" required
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Timeout (detik)</label>
                    <input type="number" name="timeout" min="5" max="120" value="{{ old('timeout', $provider->settings['timeout'] ?? 30) }}" required
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                    <button type="button" @click="testConnection()" :disabled="testing" class="btn-secondary">
                        <span x-show="!testing">Test Connection</span>
                        <span x-show="testing" x-cloak>Menguji koneksi...</span>
                    </button>
                </div>
            </form>

            <!-- Live Test Result Feedback -->
            <template x-if="feedbackMessage">
                <div :class="feedbackOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
                     class="rounded-lg border px-3.5 py-2.5 text-xs">
                    <span x-text="feedbackMessage"></span>
                </div>
            </template>

            @if ($provider->last_error)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-xs text-amber-800">
                    <span class="font-semibold">Catatan terakhir:</span> {{ $provider->last_error }}
                </div>
            @endif
        </div>

        <!-- Active Model Summary -->
        <div class="panel p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-900">Model Aktif Saat Ini</h2>
            <div class="text-xs space-y-2">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">Model Utama (Default)</span>
                    <span class="font-mono font-medium text-slate-900">{{ $defaultModel?->model_id ?? 'Belum dipilih' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-slate-500">Model Cadangan (Fallback)</span>
                    <span class="font-mono font-medium text-slate-900">{{ $fallbackModel?->model_id ?? 'Belum dipilih' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Official OpenRouter Models Catalog -->
    <div class="lg:col-span-7 panel p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Daftar Model Resmi OpenRouter</h2>
                <p class="text-xs text-slate-500">Dimuat langsung dari API OpenRouter (tidak mengasumsikan semua model gratis/selalu tersedia).</p>
            </div>
            <button type="button" @click="fetchModels(true)" :disabled="loadingModels" class="btn-secondary text-xs">
                <span x-show="!loadingModels">Muat Daftar Model</span>
                <span x-show="loadingModels" x-cloak>Memuat...</span>
            </button>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" x-model="search" @input.debounce.300ms="fetchModels(false)"
                   placeholder="Cari nama/ID model (mis. gemini, llama, claude, deepseek)..."
                   class="flex-1 rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
            <select x-model="tier" @change="fetchModels(false)"
                    class="rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
                <option value="all">Semua Model</option>
                <option value="free">Gratis (:free / $0)</option>
                <option value="paid">Berbayar (Kredit)</option>
            </select>
        </div>

        <template x-if="modelsError">
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs text-amber-800" x-text="modelsError"></div>
        </template>

        <template x-if="!loadingModels && models.length === 0 && !modelsError">
            <div class="rounded-lg border border-dashed border-slate-200 p-8 text-center text-xs text-slate-500">
                Simpan API key OpenRouter lalu klik <strong>Muat Daftar Model</strong> untuk melihat model yang tersedia dari API resmi.
            </div>
        </template>

        <div x-show="models.length > 0" x-cloak class="border border-slate-200 rounded-lg divide-y divide-slate-100 max-h-[460px] overflow-y-auto">
            <template x-for="m in models" :key="m.id">
                <div class="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-slate-50">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-xs font-semibold text-slate-900 truncate" x-text="m.id"></span>
                            <span x-show="m.is_free" class="badge-ok">Gratis</span>
                            <span x-show="!m.is_free" class="badge-muted">Berbayar</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5 truncate" x-text="m.name + (m.context_length ? ' · Konteks: ' + m.context_length.toLocaleString() : '')"></div>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <form method="POST" action="{{ route('settings.openrouter.select-model') }}">
                            @csrf
                            <input type="hidden" name="model_id" :value="m.id">
                            <input type="hidden" name="display_name" :value="m.name">
                            <input type="hidden" name="is_free" :value="m.is_free ? 1 : 0">
                            <input type="hidden" name="role_type" value="default">
                            <button type="submit" class="btn-primary !px-2.5 !py-1 text-xs">Jadikan Default</button>
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

<script>
function openRouterPanel() {
    return {
        testing: false,
        loadingModels: false,
        feedbackOk: false,
        feedbackMessage: '',
        models: [],
        modelsError: '',
        search: '',
        tier: 'all',
        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),

        async testConnection() {
            this.testing = true;
            this.feedbackMessage = '';
            try {
                const res = await fetch('{{ route('settings.openrouter.test') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.feedbackOk = !!data.ok;
                this.feedbackMessage = data.message || 'Pengujian selesai.';
                if (data.ok) this.fetchModels(false);
            } catch (e) {
                this.feedbackOk = false;
                this.feedbackMessage = 'Terjadi kesalahan saat menguji koneksi.';
            } finally {
                this.testing = false;
            }
        },

        async fetchModels(refresh) {
            this.loadingModels = true;
            this.modelsError = '';
            try {
                const params = new URLSearchParams({
                    search: this.search,
                    tier: this.tier,
                    refresh: refresh ? '1' : '0'
                });
                const res = await fetch('{{ route('settings.openrouter.models') }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!data.ok) {
                    this.models = [];
                    this.modelsError = data.message || 'Gagal memuat daftar model.';
                } else {
                    this.models = data.models || [];
                }
            } catch (e) {
                this.modelsError = 'Gagal terhubung ke server lokal untuk mengambil model.';
            } finally {
                this.loadingModels = false;
            }
        }
    };
}
</script>
@endsection
