@extends('layouts.app')

@section('title', 'Universal Content Library & Approval')
@section('header', 'Universal Content Library & Approval')
@section('subheader', 'Kelola seluruh konten affiliate lintas kategori TikTok Shop, edit caption/hashtag/disclosure, review MP4, dan ekspor siap unggah.')

@section('content')
    <!-- Quick Actions & Pipeline Summary -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('content.library') }}"
               class="px-2.5 py-1 rounded-full text-xs font-medium border {{ $statusFilter === '' ? 'bg-forest-700 text-white border-forest-700' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                Semua ({{ array_sum($statusCounts) }})
            </a>
            @foreach ($statuses as $st)
                @php $cnt = $statusCounts[$st->value] ?? 0; @endphp
                <a href="{{ route('content.library', ['status' => $st->value]) }}"
                   class="px-2.5 py-1 rounded-full text-xs font-medium border {{ $statusFilter === $st->value ? 'bg-forest-700 text-white border-forest-700' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    {{ $st->label() }} ({{ $cnt }})
                </a>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('content.research') }}" class="btn-secondary text-xs">+ Riset Produk AI</a>
            <a href="{{ route('content.scripts') }}" class="btn-primary text-xs">+ Buat Naskah &amp; Video</a>
        </div>
    </div>

    <!-- Universal Multi-Filter Bar -->
    <form method="GET" action="{{ route('content.library') }}" class="panel p-3.5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 text-xs">
        <div>
            <label class="block text-[11px] font-medium text-slate-600 mb-1">Cari Judul / Hook / Caption</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Kata kunci produk atau judul..."
                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-600 mb-1">Kategori Produk</label>
            <select name="category_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                <option value="">Semua Kategori</option>
                @foreach ($categoryTree as $root)
                    <option value="{{ $root->id }}" @selected($categoryIdFilter === $root->id)>{{ $root->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-600 mb-1">Produk Affiliate</label>
            <select name="product_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                <option value="">Semua Produk</option>
                @foreach ($products as $p)
                    <option value="{{ $p->id }}" @selected($productFilter === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-600 mb-1">Format Konten</label>
            <select name="content_format" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                <option value="">Semua Format</option>
                @foreach ($contentFormats as $fk => $fl)
                    <option value="{{ $fk }}" @selected($formatFilter === $fk)>{{ $fl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-600 mb-1">Template Video</label>
            <select name="template_slug" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                <option value="">Semua Template</option>
                @foreach ($videoTemplates as $vt)
                    <option value="{{ $vt->value }}" @selected($templateFilter === $vt->value)>{{ $vt->compositionId() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-600 mb-1">Campaign</label>
            <select name="campaign_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                <option value="">Semua Campaign</option>
                @foreach ($campaigns as $camp)
                    <option value="{{ $camp->id }}" @selected($campaignFilter === $camp->id)>{{ $camp->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-medium text-slate-600 mb-1">Status Approval</label>
            <select name="approval_status" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                <option value="">Semua Status</option>
                <option value="draft" @selected($approvalFilter === 'draft')>Draft / Produksi</option>
                <option value="pending_approval" @selected($approvalFilter === 'pending_approval')>Pending Approval</option>
                <option value="approved" @selected($approvalFilter === 'approved')>Approved</option>
                <option value="rejected" @selected($approvalFilter === 'rejected')>Rejected</option>
                <option value="archived" @selected($approvalFilter === 'archived')>Archived</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary text-xs flex-1">Terapkan Filter</button>
            <a href="{{ route('content.library') }}" class="btn-secondary text-xs">Reset</a>
        </div>
    </form>

    <!-- Inspection, Inline Caption/Hashtag/Disclosure Edit & Approval Panel -->
    @if ($selectedContent)
        @php $proj = $selectedContent->videoProject; @endphp
        <div class="panel p-5 border-forest-300 ring-1 ring-forest-200 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                <div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="badge-ok">{{ $selectedContent->category ?: 'General' }}</span>
                        @if ($selectedContent->content_format)
                            <span class="badge-muted">{{ str_replace('_', ' ', $selectedContent->content_format) }}</span>
                        @endif
                        <span class="{{ $selectedContent->experience_mode === 'first_hand' ? 'badge-ok' : 'badge-muted' }}">
                            {{ $selectedContent->experience_mode === 'first_hand' ? 'First-Hand' : 'Research-Based' }}
                        </span>
                        <span class="badge-muted">{{ strtoupper(str_replace('_', ' ', $selectedContent->status)) }}</span>
                    </div>
                    <h2 class="text-base font-semibold text-slate-900 mt-1.5">{{ $selectedContent->title }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $proj ? route('content.editor.show', $proj) : route('content.scripts.continue-editor', $selectedContent) }}" class="btn-secondary text-xs">Buka Video Editor</a>
                    <a href="{{ route('content.library') }}" class="text-xs text-slate-500 hover:text-slate-800 px-2 py-1">Tutup ✕</a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- Video Player 9:16 -->
                <div class="lg:col-span-4 flex flex-col items-center">
                    @if ($proj && $proj->output_video_path)
                        <div class="w-full max-w-[270px] aspect-[9/16] bg-slate-950 rounded-xl overflow-hidden border border-slate-800 shadow">
                            <video controls playsinline class="w-full h-full object-contain"
                                   src="{{ route('content.video.stream', $proj) }}?v={{ $proj->updated_at?->timestamp }}">
                                Browser Anda tidak mendukung pemutar video HTML5.
                            </video>
                        </div>
                        <div class="mt-3 flex flex-wrap justify-center gap-2">
                            <a href="{{ route('content.video.export', $proj) }}" class="btn-primary text-xs">
                                Download / Export MP4
                            </a>
                            <span class="text-[11px] text-slate-500 self-center">
                                {{ $proj->width }}×{{ $proj->height }} · {{ $proj->fps }} FPS · {{ number_format((float) $proj->duration_seconds, 1) }}s
                            </span>
                        </div>
                    @else
                        <div class="w-full max-w-[270px] aspect-[9/16] rounded-xl border border-dashed border-slate-300 bg-slate-50 flex flex-col items-center justify-center p-5 text-center">
                            <div class="text-xs font-semibold text-slate-700">Video MP4 Belum Dirender</div>
                            <p class="text-[11px] text-slate-500 mt-1">Buka Video Editor untuk memilih template, visual, subtitle, dan merender MP4.</p>
                            <a href="{{ $proj ? route('content.editor.show', $proj) : route('content.scripts.continue-editor', $selectedContent) }}"
                               class="btn-primary text-xs mt-3">Ke Video Editor</a>
                        </div>
                    @endif
                </div>

                <!-- Inline Edit Caption/Hashtag/Disclosure + Approval Decision -->
                <div class="lg:col-span-8 space-y-4">
                    <form method="POST" action="{{ route('content.metadata.update', $selectedContent) }}" class="rounded-lg border border-slate-200 p-3.5 space-y-2.5 bg-slate-50/70 text-xs">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-800">Edit Caption, Hashtag &amp; Affiliate Disclosure</span>
                            <button type="submit" class="btn-secondary !py-1 text-xs">Simpan Metadata</button>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-0.5">Caption TikTok</label>
                            <textarea name="caption" rows="2" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white">{{ $selectedContent->caption }}</textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[11px] text-slate-600 mb-0.5">Hashtags</label>
                                <input type="text" name="hashtags_text" value="{{ implode(' ', (array) $selectedContent->hashtags) }}"
                                       class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-600 mb-0.5">Affiliate Disclosure</label>
                                <input type="text" name="disclosure_text" value="{{ $selectedContent->disclosure_text }}"
                                       placeholder="Tautan produk tersedia di keranjang kuning (affiliate)."
                                       class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white">
                            </div>
                        </div>
                    </form>

                    <!-- Approval Form -->
                    <form method="POST" action="{{ route('content.approval', $selectedContent) }}" class="rounded-lg border border-slate-200 p-3.5 space-y-3 bg-white">
                        @csrf
                        <div class="text-xs font-semibold text-slate-800">Keputusan Persetujuan (Human-in-the-Loop)</div>
                        <p class="text-[11px] text-slate-500">Tidak ada auto-publish otomatis ke TikTok. Setelah disetujui, ekspor MP4 untuk diunggah manual ke akun @plant.coaction.</p>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Catatan Review / Revisi (Opsional)</label>
                            <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                                      placeholder="Catatan kualitas visual, akurasi klaim, atau arahan revisi...">{{ old('notes', $selectedContent->approval_notes) }}</textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="approve" class="btn-primary text-xs">Setujui (Approve)</button>
                            <button type="submit" name="decision" value="submit" class="btn-secondary text-xs">Tandai Pending Approval</button>
                            <button type="submit" name="decision" value="reject" class="inline-flex items-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                Tolak / Minta Revisi
                            </button>
                            <button type="submit" name="decision" value="archive" class="btn-secondary text-xs text-slate-500">Arsipkan</button>
                        </div>
                    </form>

                    @if ($selectedContent->approvalLogs->isNotEmpty())
                        <div class="text-xs">
                            <div class="font-semibold text-slate-700 mb-1.5">Riwayat Keputusan</div>
                            <div class="space-y-1">
                                @foreach ($selectedContent->approvalLogs->take(5) as $log)
                                    <div class="flex items-center justify-between bg-slate-50 rounded px-2.5 py-1.5 border border-slate-200/70">
                                        <div>
                                            <span class="font-medium text-slate-800">{{ strtoupper($log->decision) }}</span>
                                            <span class="text-slate-500">({{ $log->from_status }} → {{ $log->to_status }})</span>
                                            @if ($log->notes)
                                                <span class="text-slate-600">— "{{ $log->notes }}"</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-slate-400">{{ $log->created_at?->timezone('Asia/Jakarta')->format('d M H:i') }} WIB</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Content List -->
    <div class="panel overflow-hidden">
        @if ($contents->isEmpty())
            <div class="p-10 text-center space-y-2">
                <div class="text-sm font-semibold text-slate-700">Belum ada konten yang sesuai filter</div>
                <p class="text-xs text-slate-500 max-w-md mx-auto">Mulai riset produk lintas kategori melalui Universal AI Product Research atau buat naskah video di Script Generator.</p>
                <div class="pt-2 flex justify-center gap-2">
                    <a href="{{ route('content.research') }}" class="btn-secondary text-xs">Mulai Riset Produk</a>
                    <a href="{{ route('content.scripts') }}" class="btn-primary text-xs">Buat Naskah Pertama</a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-2.5">Konten &amp; Thumbnail</th>
                            <th class="px-3 py-2.5">Kategori, Format &amp; Template</th>
                            <th class="px-3 py-2.5">Status Pipeline</th>
                            <th class="px-3 py-2.5">Voiceover &amp; Render</th>
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($contents as $item)
                            @php
                                $vp = $item->videoProject;
                                $badgeClass = match ($item->status) {
                                    'approved', 'video_ready' => 'badge-ok',
                                    'rendering', 'pending_approval', 'ready_to_render', 'voiceover_ready' => 'badge-warn',
                                    'rejected' => 'badge-err',
                                    default => 'badge-muted',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-16 rounded-md bg-slate-900 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center text-[9px] text-slate-200 font-semibold">
                                            @if ($vp && $vp->thumbnail_path)
                                                <img src="{{ route('content.video.thumbnail', $vp) }}" alt="Thumb" class="w-full h-full object-cover">
                                            @else
                                                9:16
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-slate-900 truncate max-w-xs">{{ $item->title }}</div>
                                            <div class="text-[11px] text-slate-500 truncate max-w-xs mt-0.5">{{ $item->hook ?: 'Draft naskah' }}</div>
                                            @if ($item->affiliateProduct)
                                                <span class="inline-block mt-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
                                                    Produk: {{ $item->affiliateProduct->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-slate-600">
                                    <div class="font-medium text-slate-800">{{ $item->category ?: 'General' }}</div>
                                    <div class="text-[11px] text-slate-500">
                                        {{ $item->content_format ? str_replace('_', ' ', $item->content_format) . ' · ' : '' }}
                                        {{ $vp?->template_slug ?? 'belum pilih template' }} ·
                                        {{ $vp ? number_format((float) $vp->duration_seconds, 1) : $item->target_duration_seconds }}d
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="{{ $badgeClass }}">{{ str_replace('_', ' ', ucfirst($item->status)) }}</span>
                                </td>
                                <td class="px-3 py-3 text-[11px] space-y-1">
                                    <div>
                                        Voiceover:
                                        @if ($vp && $vp->voiceoverAsset)
                                            <span class="text-emerald-700 font-medium">Siap ({{ $vp->voiceoverAsset->duration_seconds }}s)</span>
                                        @else
                                            <span class="text-slate-400">Belum ada</span>
                                        @endif
                                    </div>
                                    <div>
                                        Video MP4:
                                        @if ($vp && $vp->output_video_path)
                                            <span class="text-emerald-700 font-medium">Terrender</span>
                                        @elseif ($vp?->latestRenderJob)
                                            <span class="text-amber-700 font-medium">{{ $vp->latestRenderJob->status }} ({{ $vp->latestRenderJob->progress_percent }}%)</span>
                                        @else
                                            <span class="text-slate-400">Belum dirender</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1.5">
                                    <a href="{{ route('content.scripts', ['content_id' => $item->id]) }}" class="text-slate-600 hover:text-forest-800 font-medium">Naskah</a>
                                    <span class="text-slate-300">·</span>
                                    <a href="{{ $vp ? route('content.editor.show', $vp) : route('content.scripts.continue-editor', $item) }}" class="text-forest-700 hover:text-forest-900 font-medium">Editor</a>
                                    <span class="text-slate-300">·</span>
                                    <a href="{{ route('content.library', array_merge(request()->query(), ['inspect' => $item->id])) }}" class="text-emerald-700 hover:text-emerald-900 font-semibold">Review</a>
                                    @if ($vp && $vp->output_video_path)
                                        <span class="text-slate-300">·</span>
                                        <a href="{{ route('content.video.export', $vp) }}" class="inline-flex items-center px-2 py-1 rounded bg-forest-700 text-white hover:bg-forest-800 font-medium">MP4</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $contents->links() }}
            </div>
        @endif
    </div>
@endsection
