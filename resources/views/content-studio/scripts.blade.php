@extends('layouts.app')

@section('title', 'Universal AI Script Generator & Voiceover')
@section('header', 'Universal AI Script Generator & Voiceover')
@section('subheader', 'Generate naskah video Bahasa Indonesia lintas kategori (14 format, 10 angle, mode kejujuran review), edit per bagian, dan buat voiceover Gemini TTS.')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Kolom Kiri: Generator Baru + Daftar Naskah -->
        <div class="lg:col-span-4 space-y-4">
            <form method="POST" action="{{ route('content.scripts.generate') }}"
                  x-data="{ loading: false, expMode: '{{ old('experience_mode', $prefillIdea?->experience_mode ?? 'research_based') }}' }"
                  @submit="loading = true"
                  class="panel p-4 space-y-3">
                @csrf
                <h2 class="text-sm font-semibold text-slate-900">Generate Naskah Video Universal</h2>

                @if ($prefillIdea)
                    <input type="hidden" name="content_idea_id" value="{{ $prefillIdea->id }}">
                    <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-[11px] text-emerald-900">
                        Menggunakan ide riset: <strong>{{ $prefillIdea->title }}</strong>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Judul / Topik Video</label>
                    <input type="text" name="title" required
                           value="{{ old('title', $prefillIdea?->title ?? '') }}"
                           placeholder="Contoh: 3 Hal yang Perlu Dicek Sebelum Beli TWS ANC"
                           class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Kategori Produk</label>
                        <select name="product_category_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ($categoryTree as $root)
                                <option value="{{ $root->id }}" @selected((int) old('product_category_id', $prefillIdea?->product_category_id) === $root->id)>
                                    {{ $root->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Subkategori</label>
                        <input type="text" name="subcategory" value="{{ old('subcategory', $prefillIdea?->subcategory ?? '') }}"
                               placeholder="Opsional"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Format Konten (14 Pilihan)</label>
                        <select name="content_format" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ($contentFormats as $fk => $fl)
                                <option value="{{ $fk }}" @selected(old('content_format', $prefillIdea?->content_format ?? 'product_review') === $fk)>{{ $fl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Marketing Angle</label>
                        <select name="marketing_angle" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ($marketingAngles as $ak => $al)
                                <option value="{{ $ak }}" @selected(old('marketing_angle', $prefillIdea?->marketing_angle ?? 'auto') === $ak)>{{ $al }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Gaya Narasi</label>
                        <select name="narration_style" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ($styles as $key => $label)
                                <option value="{{ $key }}" @selected(old('narration_style', 'edukatif') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Durasi Target</label>
                        <select name="duration_seconds" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ($durations as $dur)
                                <option value="{{ $dur }}" @selected((int) old('duration_seconds', $prefillIdea?->estimated_duration_seconds ?? 30) === $dur)>
                                    {{ $dur }} detik
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Mode Kejujuran Review -->
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 space-y-2 text-xs">
                    <div class="font-semibold text-slate-700">Kejujuran Review</div>
                    <div class="flex gap-3">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="experience_mode" value="research_based" x-model="expMode" class="text-forest-600">
                            <span>Research-Based</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="experience_mode" value="first_hand" x-model="expMode" class="text-forest-600">
                            <span>First-Hand Experience</span>
                        </label>
                    </div>
                    <div x-show="expMode === 'first_hand'" x-cloak class="space-y-1.5">
                        <textarea name="experience_usage" rows="2" placeholder="Pengalaman pemakaian nyata yang boleh diceritakan AI..."
                                  class="w-full rounded border border-slate-300 px-2 py-1 text-xs bg-white">{{ old('experience_usage', data_get($prefillIdea?->experience_notes, 'usage_experience')) }}</textarea>
                        <input type="text" name="experience_pros" value="{{ old('experience_pros', data_get($prefillIdea?->experience_notes, 'felt_pros')) }}"
                               placeholder="Kelebihan yang dirasakan" class="w-full rounded border border-slate-300 px-2 py-1 text-xs bg-white">
                        <input type="text" name="experience_cons" value="{{ old('experience_cons', data_get($prefillIdea?->experience_notes, 'found_cons')) }}"
                               placeholder="Kekurangan yang ditemukan" class="w-full rounded border border-slate-300 px-2 py-1 text-xs bg-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Target Audiens</label>
                    <input type="text" name="target_audience" required
                           value="{{ old('target_audience', $prefillIdea?->target_audience ?? 'Audiens TikTok Indonesia sesuai kategori produk') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Produk Affiliate</label>
                        <select name="affiliate_product_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="">Tanpa produk</option>
                            @foreach ($approvedProducts as $prod)
                                <option value="{{ $prod->id }}" @selected((int) old('affiliate_product_id', $prefillIdea?->affiliate_product_id) === $prod->id)>
                                    {{ $prod->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">AI Provider</label>
                        <select name="preferred_provider" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="">Auto + Fallback</option>
                            <option value="google_gemini">Google Gemini</option>
                            <option value="openrouter">OpenRouter</option>
                        </select>
                    </div>
                </div>

                <button type="submit" :disabled="loading || !@json($hasAiProvider)" class="btn-primary w-full text-xs">
                    <span x-show="!loading">Generate Naskah AI</span>
                    <span x-show="loading" x-cloak>Menyusun naskah Bahasa Indonesia...</span>
                </button>
            </form>

            <!-- Daftar Naskah Tersimpan -->
            <div class="panel p-3.5 space-y-2">
                <div class="text-xs font-semibold text-slate-700">Daftar Naskah Terbaru ({{ $scripts->count() }})</div>
                <div class="space-y-1.5 max-h-[340px] overflow-y-auto pr-1">
                    @forelse ($scripts as $sc)
                        <a href="{{ route('content.scripts', ['content_id' => $sc->id]) }}"
                           class="block rounded-lg border px-3 py-2 text-xs transition {{ $activeScript && $activeScript->id === $sc->id ? 'border-forest-500 bg-emerald-50/70' : 'border-slate-200 hover:bg-slate-50' }}">
                            <div class="font-medium text-slate-900 truncate">{{ $sc->title }}</div>
                            <div class="flex items-center justify-between text-[11px] text-slate-500 mt-0.5">
                                <span class="truncate">{{ $sc->target_duration_seconds }}d · {{ $sc->category }}</span>
                                <span class="uppercase text-[10px]">{{ str_replace('_', ' ', $sc->status) }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="text-xs text-slate-400">Belum ada naskah yang dibuat.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Editor Naskah Aktif + Regenerate Bagian + Voiceover Gemini TTS -->
        <div class="lg:col-span-8 space-y-4">
            @if (! $activeScript)
                <div class="panel p-10 text-center text-xs text-slate-500">
                    Pilih naskah di daftar sebelah kiri atau generate naskah baru terlebih dahulu.
                </div>
            @else
                <!-- Header & Next Step CTA -->
                <div class="panel p-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="badge-ok">{{ $activeScript->category ?: 'General' }}</span>
                            @if ($activeScript->content_format)
                                <span class="badge-muted">{{ str_replace('_', ' ', $activeScript->content_format) }}</span>
                            @endif
                            <span class="{{ $activeScript->experience_mode === 'first_hand' ? 'badge-ok' : 'badge-muted' }}">
                                {{ $activeScript->experience_mode === 'first_hand' ? 'First-Hand' : 'Research-Based' }}
                            </span>
                            <span class="badge-muted">{{ $activeScript->target_duration_seconds }} detik · Revisi #{{ $activeScript->revision_count }}</span>
                            <span class="badge-warn">{{ str_replace('_', ' ', ucfirst($activeScript->status)) }}</span>
                        </div>
                        <h2 class="text-base font-semibold text-slate-900 mt-1">{{ $activeScript->title }}</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('content.assets', ['content_id' => $activeScript->id]) }}" class="btn-secondary text-xs">Kelola Aset Visual</a>
                        <a href="{{ route('content.scripts.continue-editor', $activeScript) }}" class="btn-primary text-xs">
                            Lanjutkan ke Video Editor →
                        </a>
                    </div>
                </div>

                <!-- Panel Voiceover (Gemini TTS Default) -->
                <div class="panel p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Voiceover Bahasa Indonesia (TTS)</h3>
                            <p class="text-[11px] text-slate-500">Provider aktif: <strong>{{ $activeTtsName }}</strong> {{ $activeTtsConfigured ? '(Siap)' : '(Belum dikonfigurasi)' }}</p>
                        </div>
                        @if ($activeScript->videoProject?->voiceoverAsset)
                            <span class="badge-ok">Voiceover Siap · {{ $activeScript->videoProject->voiceoverAsset->duration_seconds }} detik</span>
                        @endif
                    </div>

                    @if ($activeScript->videoProject?->voiceoverAsset)
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 flex flex-wrap items-center justify-between gap-3">
                            <audio controls class="w-full max-w-md h-9"
                                   src="{{ route('content.assets.preview', $activeScript->videoProject->voiceoverAsset) }}?v={{ $activeScript->videoProject->voiceoverAsset->updated_at?->timestamp }}"></audio>
                            <div class="text-[11px] text-slate-500">
                                Model: {{ data_get($activeScript->videoProject->voiceoverAsset->metadata, 'model_id', 'TTS') }} ·
                                Suara: {{ data_get($activeScript->videoProject->voiceoverAsset->metadata, 'voice_id', 'Default') }}
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('content.scripts.voiceover', $activeScript) }}" x-data="{ busy: false }" @submit="busy = true"
                          class="grid grid-cols-1 md:grid-cols-12 gap-2.5 items-end">
                        @csrf
                        <div class="md:col-span-3">
                            <label class="block text-[11px] font-medium text-slate-600 mb-1">Provider TTS</label>
                            <select name="tts_provider" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                                <option value="">Default ({{ $activeTtsName }})</option>
                                <option value="google_gemini_tts">Google Gemini TTS</option>
                                <option value="google_cloud_tts">Google Cloud TTS</option>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[11px] font-medium text-slate-600 mb-1">Suara Gemini (Opsional)</label>
                            <select name="voice_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                                <option value="">Default Pengaturan</option>
                                <option value="Kore">Kore (Natural &amp; Jernih)</option>
                                <option value="Aoede">Aoede (Hangat &amp; Ramah)</option>
                                <option value="Puck">Puck (Ceria &amp; Enerjik)</option>
                                <option value="Charon">Charon (Informatif &amp; Tenang)</option>
                                <option value="Fenrir">Fenrir (Tegas &amp; Berwibawa)</option>
                            </select>
                        </div>
                        <div class="md:col-span-4">
                            <label class="block text-[11px] font-medium text-slate-600 mb-1">Arahan Gaya Baca (Opsional)</label>
                            <input type="text" name="style_prompt" placeholder="Contoh: Jelas, santai, informatif seperti reviewer TikTok"
                                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" :disabled="busy" class="btn-primary w-full text-xs">
                                <span x-show="!busy">{{ $activeScript->videoProject?->voiceoverAsset ? 'Regenerate VO' : 'Generate VO' }}</span>
                                <span x-show="busy" x-cloak>Membuat...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Regenerate Satu Bagian & Rewrite Script -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <form method="POST" action="{{ route('content.scripts.regenerate-section', $activeScript) }}" class="panel p-3.5 space-y-2">
                        @csrf
                        <div class="text-xs font-semibold text-slate-800">Regenerate Bagian Tertentu (Tanpa Ubah Bagian Lain)</div>
                        <div class="flex gap-2">
                            <select name="section" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs flex-1">
                                @foreach ($regenerableSections as $secKey => $secLabel)
                                    <option value="{{ $secKey }}">{{ $secLabel }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Regenerate</button>
                        </div>
                        <input type="text" name="instruction" placeholder="Instruksi opsional (misal: buat hook lebih tajam)"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    </form>

                    <form method="POST" action="{{ route('content.scripts.rewrite', $activeScript) }}" class="panel p-3.5 space-y-2">
                        @csrf
                        <div class="text-xs font-semibold text-slate-800">Tulis Ulang Seluruh Naskah (Rewrite)</div>
                        <div class="flex gap-2">
                            <select name="narration_style" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs flex-1">
                                @foreach ($styles as $key => $label)
                                    <option value="{{ $key }}" @selected($activeScript->narration_style === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="duration_seconds" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs w-20">
                                @foreach ($durations as $dur)
                                    <option value="{{ $dur }}" @selected((int) $activeScript->target_duration_seconds === $dur)>{{ $dur }}d</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Rewrite</button>
                        </div>
                        <select name="content_format" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ($contentFormats as $fk => $fl)
                                <option value="{{ $fk }}" @selected($activeScript->content_format === $fk)>Format: {{ $fl }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <!-- Form Edit Manual Naskah Lengkap -->
                <form method="POST" action="{{ route('content.scripts.update', $activeScript) }}" class="panel p-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Edit Manual Naskah &amp; Metadata TikTok</h3>
                        <button type="submit" class="btn-primary text-xs">Simpan Draft Naskah</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Judul Konten</label>
                            <input type="text" name="title" value="{{ old('title', $activeScript->title) }}" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Durasi Target</label>
                            <select name="target_duration_seconds" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                                @foreach ($durations as $dur)
                                    <option value="{{ $dur }}" @selected((int) $activeScript->target_duration_seconds === $dur)>{{ $dur }} detik</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-forest-800 mb-1">1. Hook (3 Detik Pertama)</label>
                            <textarea name="hook" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">{{ old('hook', $activeScript->hook) }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">2. Opening</label>
                            <textarea name="opening" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">{{ old('opening', $activeScript->opening) }}</textarea>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">3. Main Narration (Inti Review / Edukasi / Cerita)</label>
                        <textarea name="main_narration" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">{{ old('main_narration', $activeScript->main_narration) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">4. Supporting Explanation (Fakta, Spesifikasi, atau Keterbatasan)</label>
                        <textarea name="supporting_explanation" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">{{ old('supporting_explanation', $activeScript->supporting_explanation) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">5. Closing</label>
                            <textarea name="closing" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">{{ old('closing', $activeScript->closing) }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">6. CTA (Ajakan Natural)</label>
                            <textarea name="cta" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">{{ old('cta', $activeScript->cta) }}</textarea>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Caption TikTok</label>
                            <textarea name="caption" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">{{ old('caption', $activeScript->caption) }}</textarea>
                        </div>
                        <div class="space-y-2.5">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Hashtags (pisahkan spasi / koma)</label>
                                <input type="text" name="hashtags_text" value="{{ old('hashtags_text', implode(' ', (array) $activeScript->hashtags)) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Affiliate Disclosure</label>
                                <input type="text" name="disclosure_text" value="{{ old('disclosure_text', $activeScript->disclosure_text) }}"
                                       placeholder="Contoh: Tautan produk tersedia di keranjang kuning (affiliate)."
                                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
