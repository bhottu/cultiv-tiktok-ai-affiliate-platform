@extends('layouts.app')

@section('title', 'Video Preview & Editor')
@section('header', 'Video Preview & Remotion Editor (Universal)')
@section('subheader', 'Pilih 1 dari 10 template universal, sesuaikan tema warna kategori produk, aset visual, subtitle, dan audio sebelum merender MP4 9:16.')

@section('content')
    @php
        $latestJob = $project->latestRenderJob;
        $isRenderingActive = $latestJob && in_array($latestJob->status, ['queued', 'preparing', 'rendering', 'finalizing'], true);
        $subStyle = $project->subtitle_style ?? [];
        $scenePreviewData = $project->scenes->sortBy('scene_order')->values()->map(fn ($s) => [
            'id' => $s->id,
            'order' => $s->scene_order,
            'title' => $s->title,
            'overlay' => $s->overlay_text,
            'duration' => (float) $s->duration_seconds,
            'imageUrl' => ($s->mediaAsset && $s->mediaAsset->asset_type === 'image') ? route('content.assets.preview', $s->mediaAsset) : null,
            'mediaName' => $s->mediaAsset?->original_name ?? 'Studio Placeholder Kategori',
        ])->values()->all();
        $firstSubText = $project->subtitles->first()?->text ?? ($project->scenes->first()?->overlay_text ?? $project->title);
    @endphp

    <!-- Top Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('content.library') }}" class="text-slate-500 hover:text-slate-800">← Content Library</a>
            <span class="text-slate-300">/</span>
            @if ($project->content)
                <a href="{{ route('content.scripts', ['content_id' => $project->content->id]) }}" class="text-slate-500 hover:text-slate-800">Edit Naskah</a>
                <span class="text-slate-300">/</span>
            @endif
            <span class="font-semibold text-slate-800">{{ $project->title }}</span>
            <span class="badge-muted">{{ $project->content?->category ?: 'General' }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($project->output_video_path)
                <a href="{{ route('content.video.export', $project) }}" class="btn-secondary text-xs">Download MP4</a>
            @endif
            @if ($project->content)
                <a href="{{ route('content.library', ['inspect' => $project->content->id]) }}" class="btn-secondary text-xs">Review &amp; Approval</a>
            @endif
            <form method="POST" action="{{ route('content.editor.render', $project) }}">
                @csrf
                <button type="submit" class="btn-primary text-xs">
                    {{ $project->output_video_path ? 'Render Ulang Video (Remotion)' : 'Render Video (Remotion)' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5"
         x-data="videoEditorStudio({
            statusUrl: @js(route('content.editor.render-status', $project)),
            initialActive: @js($isRenderingActive),
            initialStatus: @js($latestJob?->status ?? $project->status),
            initialProgress: @js((int) ($latestJob?->progress_percent ?? 0)),
            initialStage: @js($latestJob?->stage ?? ''),
            initialError: @js($latestJob?->error_message ?? ''),
            scenes: @js($scenePreviewData),
            templateSlug: @js($project->template_slug ?: 'product_showcase'),
            subtitleEnabled: @js((bool) $project->subtitle_enabled),
            subtitlePreview: @js($firstSubText),
         })">

        <!-- Kolom Kiri (Sticky 9:16 Stage + Render Monitor + Audio) -->
        <div class="lg:col-span-4 space-y-4">
            <div class="panel p-4 space-y-3 lg:sticky lg:top-20">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-800">Preview Vertikal 9:16</span>
                    <span class="badge-muted text-[11px]">{{ $project->width }}×{{ $project->height }} · {{ $project->fps }} FPS</span>
                </div>

                @if ($project->output_video_path)
                    <div class="mx-auto w-full max-w-[260px] aspect-[9/16] rounded-xl overflow-hidden bg-slate-950 border border-slate-800 shadow">
                        <video controls playsinline class="w-full h-full object-contain"
                               src="{{ route('content.video.stream', $project) }}?v={{ $project->updated_at?->timestamp }}">
                            Browser tidak mendukung tag video.
                        </video>
                    </div>
                    <div class="text-center text-[11px] text-emerald-700 font-medium">
                        MP4 Hasil Render Tersedia · {{ number_format((float) $project->duration_seconds, 1) }} detik
                    </div>
                @else
                    <!-- Category-Aware Preview Stage (no forced botanical green for non-plant products) -->
                    <div class="mx-auto w-full max-w-[260px] aspect-[9/16] rounded-xl overflow-hidden border border-slate-800 shadow relative flex flex-col justify-between p-4 text-white"
                         style="background: linear-gradient(160deg, {{ $themeTokens['bgPrimary'] }} 0%, {{ $themeTokens['bgSecondary'] }} 55%, {{ $themeTokens['bgPrimary'] }} 100%);">
                        <template x-if="activeScene && activeScene.imageUrl">
                            <img :src="activeScene.imageUrl" alt="Scene preview" class="absolute inset-0 w-full h-full object-cover opacity-55">
                        </template>
                        <div class="relative z-10 flex items-center justify-between text-[10px]">
                            <span class="px-2 py-0.5 rounded-full font-semibold"
                                  style="background: {{ $themeTokens['badgeBg'] }}; color: {{ $themeTokens['badgeText'] }};"
                                  x-text="templateSlug.replace('_', ' ').toUpperCase()"></span>
                            <span class="px-2 py-0.5 rounded-full bg-black/50 text-slate-200" x-text="'Scene ' + (activeSceneIndex + 1) + '/' + scenes.length"></span>
                        </div>
                        <div class="relative z-10 my-auto bg-black/50 backdrop-blur-sm rounded-lg p-3 border border-white/15">
                            <div class="text-[10px] font-semibold" style="color: {{ $themeTokens['accentColor'] }};" x-text="activeScene ? activeScene.title : 'Scene'"></div>
                            <div class="text-xs font-bold leading-snug mt-1" x-text="activeScene ? activeScene.overlay : ''"></div>
                        </div>
                        <div class="relative z-10 space-y-2 pb-4">
                            <div x-show="subtitleEnabled" class="bg-black/75 rounded-lg px-2.5 py-1.5 text-center text-[11px] font-semibold text-white border border-white/15" x-text="subtitlePreview"></div>
                            <div class="text-[9px] text-slate-300/80 text-center">{{ $project->content?->category ?: 'General Product' }} · Safe-Area TikTok 9:16</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-center gap-1">
                        <template x-for="(sc, idx) in scenes" :key="sc.id">
                            <button type="button" @click="activeSceneIndex = idx"
                                    :class="activeSceneIndex === idx ? 'bg-forest-700 text-white' : 'bg-slate-100 text-slate-600'"
                                    class="px-2 py-0.5 rounded text-[10px] font-medium"
                                    x-text="'S' + (idx + 1) + ' (' + sc.duration + 's)'"></button>
                        </template>
                    </div>
                @endif

                <!-- Live Render Status Box -->
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-slate-700">Status Render Remotion</span>
                        <span class="uppercase font-semibold text-[11px]"
                              :class="{
                                  'text-emerald-700': renderStatus === 'completed',
                                  'text-amber-700': ['queued','preparing','rendering','finalizing'].includes(renderStatus),
                                  'text-rose-700': renderStatus === 'failed',
                                  'text-slate-500': !renderStatus || renderStatus === 'draft' || renderStatus === 'cancelled',
                              }"
                              x-text="renderStatus || 'draft'"></span>
                    </div>
                    <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                        <div class="h-full bg-forest-600 transition-all duration-300" :style="'width: ' + renderProgress + '%'"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        <span x-text="renderStage || 'Siap dirender'"></span>
                        <span x-text="renderProgress + '%'"></span>
                    </div>
                    <template x-if="renderError">
                        <div class="rounded bg-rose-50 border border-rose-200 p-2 text-[11px] text-rose-700" x-text="renderError"></div>
                    </template>

                    @if ($latestJob)
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @if ($latestJob->status === 'failed' || $latestJob->status === 'cancelled')
                                <form method="POST" action="{{ route('content.editor.render.retry', [$project, $latestJob]) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary !px-2.5 !py-1 text-[11px]">Retry Render #{{ $latestJob->id }}</button>
                                </form>
                            @elseif (in_array($latestJob->status, ['queued', 'preparing', 'rendering'], true))
                                <form method="POST" action="{{ route('content.editor.render.cancel', [$project, $latestJob]) }}">
                                    @csrf
                                    <button type="submit" class="text-[11px] text-rose-600 hover:underline px-2 py-1">Batalkan Render</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Voiceover Preview & Quick Regen -->
                <div class="rounded-lg border border-slate-200 p-3 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-slate-700">Voiceover Audio</span>
                        <span class="text-[11px] text-slate-500">{{ $activeTtsName }}</span>
                    </div>
                    @if ($project->voiceoverAsset)
                        <audio controls class="w-full h-8" src="{{ route('content.assets.preview', $project->voiceoverAsset) }}?v={{ $project->voiceoverAsset->updated_at?->timestamp }}"></audio>
                    @else
                        <p class="text-[11px] text-amber-700">Belum ada voiceover. Anda tetap bisa merender video visual atau generate voiceover Gemini TTS.</p>
                    @endif
                    @if ($project->content)
                        <form method="POST" action="{{ route('content.scripts.voiceover', $project->content) }}">
                            @csrf
                            <button type="submit" class="btn-secondary w-full text-[11px] !py-1.5">
                                {{ $project->voiceoverAsset ? 'Regenerate Voiceover (Gemini TTS)' : 'Generate Voiceover (Gemini TTS)' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Form Konfigurasi Template, Tema Visual, Scene, Subtitle, Audio -->
        <div class="lg:col-span-8 space-y-4">
            <form method="POST" action="{{ route('content.editor.save', $project) }}" class="space-y-4">
                @csrf

                <!-- 1. Template & Visual Theme -->
                <div class="panel p-4 space-y-3.5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">1. Template Remotion &amp; Tema Visual Kategori</h2>
                        <button type="submit" class="btn-primary text-xs">Simpan Perubahan Editor</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Judul Video</label>
                            <input type="text" name="title" value="{{ old('title', $project->title) }}" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Tema Visual Kategori</label>
                            <select name="visual_theme_preset" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                                @foreach ($themePresets as $pk => $pl)
                                    <option value="{{ $pk }}" @selected($activeThemePreset === $pk)>{{ $pl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Teks CTA Penutup</label>
                            <input type="text" name="cta_text" value="{{ old('cta_text', $project->cta_text) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Template Video Universal (10 Gaya Visual)</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
                            @foreach ($templates as $tpl)
                                <label class="cursor-pointer rounded-lg border p-2.5 text-xs transition block"
                                       :class="templateSlug === '{{ $tpl->value }}' ? 'border-forest-600 bg-emerald-50/70 ring-1 ring-forest-500' : 'border-slate-200 hover:bg-slate-50'">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="template_slug" value="{{ $tpl->value }}"
                                               x-model="templateSlug"
                                               @checked(($project->template_slug ?: 'product_showcase') === $tpl->value)
                                               class="text-forest-600 focus:ring-forest-500">
                                        <span class="font-semibold text-slate-900">{{ $tpl->compositionId() }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-600 mt-1 leading-snug">{{ $tpl->description() }}</div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- 2. Scene Timeline & Visual Picker -->
                <div class="panel p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">2. Daftar Scene, Aset Visual &amp; Teks Overlay</h2>
                            <p class="text-[11px] text-slate-500">Pilih foto/video produk, teks layar, efek gerak kamera, dan durasi tiap scene. Jika kosong, sistem menampilkan placeholder studio bersih sesuai kategori.</p>
                        </div>
                        <a href="{{ route('content.assets', ['content_id' => $project->generated_content_id]) }}" class="btn-secondary !px-2.5 !py-1 text-[11px]">
                            + Unggah Aset Visual
                        </a>
                    </div>

                    <div class="space-y-3">
                        @foreach ($project->scenes->sortBy('scene_order')->values() as $idx => $scene)
                            <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3 space-y-2.5">
                                <input type="hidden" name="scenes[{{ $idx }}][id]" value="{{ $scene->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded bg-slate-800 text-white text-[11px] font-semibold">Scene {{ $idx + 1 }}</span>
                                        <input type="text" name="scenes[{{ $idx }}][title]" value="{{ $scene->title }}"
                                               class="rounded border border-slate-300 px-2 py-1 text-xs font-medium w-44">
                                    </div>
                                    <div class="flex items-center gap-2 text-xs">
                                        <label class="text-[11px] text-slate-500">Urutan:</label>
                                        <input type="number" name="scenes[{{ $idx }}][scene_order]" min="1" value="{{ $scene->scene_order }}"
                                               class="w-14 rounded border border-slate-300 px-2 py-1 text-xs">
                                        <label class="text-[11px] text-slate-500">Durasi (d):</label>
                                        <input type="number" step="0.5" min="1" max="45" name="scenes[{{ $idx }}][duration_seconds]" value="{{ $scene->duration_seconds }}"
                                               class="w-16 rounded border border-slate-300 px-2 py-1 text-xs">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Aset Visual (Foto/Video)</label>
                                        <select name="scenes[{{ $idx }}][media_asset_id]" class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs bg-white">
                                            <option value="">— Placeholder Studio Sesuai Kategori —</option>
                                            @foreach ($visualAssets as $va)
                                                <option value="{{ $va->id }}" @selected($scene->media_asset_id === $va->id)>
                                                    [{{ strtoupper($va->asset_type) }}] {{ $va->original_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Gerak Kamera (Ken Burns)</label>
                                        <select name="scenes[{{ $idx }}][motion_effect]" class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs bg-white">
                                            @foreach (['zoom_in' => 'Zoom In Halus', 'zoom_out' => 'Zoom Out Halus', 'pan_left' => 'Pan Kiri', 'pan_right' => 'Pan Kanan', 'static' => 'Statis'] as $mk => $ml)
                                                <option value="{{ $mk }}" @selected($scene->motion_effect === $mk)>{{ $ml }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Transisi</label>
                                        <select name="scenes[{{ $idx }}][transition_type]" class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs bg-white">
                                            @foreach (['fade' => 'Fade', 'slide' => 'Slide', 'zoom' => 'Zoom', 'cut' => 'Cut'] as $tk => $tl)
                                                <option value="{{ $tk }}" @selected($scene->transition_type === $tk)>{{ $tl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-medium text-slate-600 mb-1">Teks Overlay Layar (Singkat &amp; Jelas)</label>
                                    <input type="text" name="scenes[{{ $idx }}][overlay_text]" value="{{ $scene->overlay_text }}"
                                           class="w-full rounded border border-slate-300 px-2.5 py-1.5 text-xs bg-white">
                                </div>
                                <input type="hidden" name="scenes[{{ $idx }}][narration_segment]" value="{{ $scene->narration_segment }}">
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 3. Subtitle Editor -->
                <div class="panel p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">3. Subtitle Otomatis &amp; Editor Waktu</h2>
                            <p class="text-[11px] text-slate-500">Timing otomatis berstatus <strong>estimasi</strong> (proporsional kata). Edit manual untuk sinkronisasi presisi.</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                            <input type="checkbox" name="subtitle_enabled" value="1" x-model="subtitleEnabled" @checked($project->subtitle_enabled)
                                   class="rounded border-slate-300 text-forest-600 focus:ring-forest-500">
                            Aktifkan Subtitle
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-1">Posisi (TikTok Safe-Area)</label>
                            <select name="subtitle_position" class="w-full rounded border border-slate-300 px-2.5 py-1.5 text-xs">
                                <option value="bottom" @selected(($subStyle['position'] ?? 'bottom') === 'bottom')>Bawah (Aman UI TikTok)</option>
                                <option value="center" @selected(($subStyle['position'] ?? '') === 'center')>Tengah</option>
                                <option value="top" @selected(($subStyle['position'] ?? '') === 'top')>Atas</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-1">Ukuran Huruf (px)</label>
                            <input type="number" name="subtitle_font_size" min="28" max="64" value="{{ $subStyle['fontSize'] ?? 44 }}"
                                   class="w-full rounded border border-slate-300 px-2.5 py-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-1">Warna Highlight Kata Kunci</label>
                            <input type="text" name="subtitle_highlight_color" value="{{ $subStyle['highlightColor'] ?? $themeTokens['accentColor'] }}"
                                   class="w-full rounded border border-slate-300 px-2.5 py-1.5 text-xs">
                        </div>
                    </div>

                    <div class="space-y-1.5 max-h-[260px] overflow-y-auto pr-1">
                        @foreach ($project->subtitles->sortBy('segment_order')->values() as $sIdx => $seg)
                            <div class="grid grid-cols-12 gap-2 items-center bg-slate-50 rounded px-2.5 py-1.5 border border-slate-200/80 text-xs">
                                <input type="hidden" name="subtitles[{{ $sIdx }}][id]" value="{{ $seg->id }}">
                                <div class="col-span-2">
                                    <input type="number" step="0.1" min="0" name="subtitles[{{ $sIdx }}][start_seconds]" value="{{ $seg->start_seconds }}"
                                           class="w-full rounded border border-slate-300 px-1.5 py-1 text-[11px]">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.1" min="0.2" name="subtitles[{{ $sIdx }}][end_seconds]" value="{{ $seg->end_seconds }}"
                                           class="w-full rounded border border-slate-300 px-1.5 py-1 text-[11px]">
                                </div>
                                <div class="col-span-6">
                                    <input type="text" name="subtitles[{{ $sIdx }}][text]" value="{{ $seg->text }}"
                                           class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                </div>
                                <div class="col-span-2 text-right">
                                    <span class="{{ $seg->timing_source === 'manual' ? 'badge-ok' : 'badge-muted' }} !text-[10px]">
                                        {{ $seg->timing_source === 'manual' ? 'manual' : 'estimasi' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 4. Audio & Musik Latar -->
                <div class="panel p-4 space-y-3">
                    <h2 class="text-sm font-semibold text-slate-900">4. Mixing Audio &amp; Musik Latar Opsional</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-1">Volume Voiceover (0.1 – 1.5)</label>
                            <input type="number" step="0.05" min="0.1" max="1.5" name="voiceover_volume" value="{{ $project->voiceover_volume ?? 1.0 }}"
                                   class="w-full rounded border border-slate-300 px-2.5 py-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-1">Musik Latar Legal (Opsional)</label>
                            <select name="bg_music_asset_id" class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs">
                                <option value="">— Tanpa Musik Latar —</option>
                                @foreach ($bgMusicAssets as $bg)
                                    <option value="{{ $bg->id }}" @selected($project->bg_music_asset_id === $bg->id)>{{ $bg->original_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-1">Volume Musik (Ducking, 0.0 – 0.5)</label>
                            <input type="number" step="0.02" min="0" max="0.5" name="bg_music_volume" value="{{ $project->bg_music_volume ?? 0.15 }}"
                                   class="w-full rounded border border-slate-300 px-2.5 py-1.5 text-xs">
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-[11px] text-slate-500">Simpan perubahan sebelum menekan tombol Render Video.</span>
                        <button type="submit" class="btn-primary text-xs">Simpan Draft Editor</button>
                    </div>
                </div>
            </form>

            <div class="flex justify-end">
                <form method="POST" action="{{ route('content.editor.regenerate-subtitles', $project) }}">
                    @csrf
                    <button type="submit" class="btn-secondary text-xs">Regenerate Subtitle Otomatis dari Naskah</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function videoEditorStudio(cfg) {
            return {
                statusUrl: cfg.statusUrl,
                renderStatus: cfg.initialStatus || 'draft',
                renderProgress: cfg.initialProgress || 0,
                renderStage: cfg.initialStage || '',
                renderError: cfg.initialError || '',
                scenes: cfg.scenes || [],
                activeSceneIndex: 0,
                templateSlug: cfg.templateSlug || 'product_showcase',
                subtitleEnabled: !!cfg.subtitleEnabled,
                subtitlePreview: cfg.subtitlePreview || '',
                pollTimer: null,
                get activeScene() {
                    return this.scenes[this.activeSceneIndex] || this.scenes[0] || null;
                },
                init() {
                    if (cfg.initialActive) {
                        this.startPolling();
                    }
                },
                startPolling() {
                    this.pollTimer = setInterval(async () => {
                        try {
                            const res = await fetch(this.statusUrl, { headers: { 'Accept': 'application/json' } });
                            if (!res.ok) return;
                            const data = await res.json();
                            if (data.job) {
                                this.renderStatus = data.job.status;
                                this.renderProgress = data.job.progress_percent;
                                this.renderStage = data.job.stage || '';
                                this.renderError = data.job.error_message || '';
                                if (['completed', 'failed', 'cancelled'].includes(data.job.status)) {
                                    clearInterval(this.pollTimer);
                                    if (data.job.status === 'completed') {
                                        window.location.reload();
                                    }
                                }
                            }
                        } catch (e) {}
                    }, 3500);
                }
            };
        }
    </script>
@endsection
