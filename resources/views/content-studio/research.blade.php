@extends('layouts.app')

@section('title', 'Universal AI Product Research')
@section('header', 'Universal AI Product Research')
@section('subheader', 'Analisis produk lintas kategori TikTok Shop, bedakan konten berbasis riset vs pengalaman pribadi, dan hasilkan ide konten tanpa mengarang data.')

@section('content')
    @if (! $hasAiProvider)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900 flex items-center justify-between">
            <span>Belum ada AI Provider (OpenRouter atau Google Gemini) yang dikonfigurasi. Aktifkan minimal satu provider terlebih dahulu.</span>
            <a href="{{ route('settings.ai.index') }}" class="btn-secondary text-xs">Buka AI Provider</a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Form Universal AI Product Research -->
        <div class="lg:col-span-5 space-y-4">
            <form method="POST" action="{{ route('content.research.generate') }}" enctype="multipart/form-data"
                  x-data="{ loading: false, expMode: '{{ old('experience_mode', 'research_based') }}', showMore: false }"
                  @submit="loading = true"
                  class="panel p-4 space-y-3">
                @csrf
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Riset Produk &amp; Ide Konten AI</h2>
                    <span class="badge-muted text-[11px]">Default: {{ $activeProvider }}</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Pilih Produk Tersimpan (Opsional)</label>
                    <select name="affiliate_product_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                        <option value="">— Isi Informasi Produk Manual di Bawah —</option>
                        @foreach ($approvedProducts as $prod)
                            <option value="{{ $prod->id }}" @selected((int) old('affiliate_product_id', request('product_id')) === $prod->id)>
                                [{{ strtoupper($prod->approval_status) }}] {{ $prod->name }} ({{ $prod->category }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Nama Produk</label>
                        <input type="text" name="product_name" value="{{ old('product_name') }}"
                               placeholder="Contoh: Mini Vacuum Portable / Sunscreen SPF 50"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Kategori Produk</label>
                        <select name="product_category_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ($categoryTree as $root)
                                <option value="{{ $root->id }}" @selected((int) old('product_category_id') === $root->id)>{{ $root->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Subkategori (Opsional)</label>
                        <input type="text" name="subcategory" value="{{ old('subcategory') }}"
                               placeholder="Contoh: Audio & TWS, Kitchen, Outerwear"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Harga (Rp) &amp; Komisi (Opsional)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="100" min="0" name="price" value="{{ old('price') }}" placeholder="Harga"
                                   class="w-1/2 rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <input type="text" name="commission_info" value="{{ old('commission_info') }}" placeholder="Komisi 10%"
                                   class="w-1/2 rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Deskripsi &amp; Keunggulan Produk (Fakta yang Diketahui)</label>
                    <textarea name="product_description" rows="2" placeholder="Tuliskan fungsi, fitur, atau spesifikasi yang benar-benar diketahui. Biarkan kosong jika tidak tersedia."
                              class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">{{ old('product_description') }}</textarea>
                    <input type="text" name="product_advantages" value="{{ old('product_advantages') }}"
                           placeholder="Keunggulan utama (opsional, pisahkan koma)"
                           class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs mt-1.5">
                </div>

                <!-- Kejujuran Konten: Research-Based vs First-Hand -->
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2">
                    <div class="text-xs font-semibold text-slate-800">Mode Kejujuran Konten</div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <label class="cursor-pointer rounded-lg border p-2 flex items-start gap-2"
                               :class="expMode === 'research_based' ? 'border-forest-600 bg-emerald-50/70' : 'border-slate-200 bg-white'">
                            <input type="radio" name="experience_mode" value="research_based" x-model="expMode" class="mt-0.5 text-forest-600">
                            <div>
                                <div class="font-semibold text-slate-900">Research-Based</div>
                                <div class="text-[10px] text-slate-500">Belum mencoba produk. AI dilarang mengklaim pengalaman pribadi.</div>
                            </div>
                        </label>
                        <label class="cursor-pointer rounded-lg border p-2 flex items-start gap-2"
                               :class="expMode === 'first_hand' ? 'border-forest-600 bg-emerald-50/70' : 'border-slate-200 bg-white'">
                            <input type="radio" name="experience_mode" value="first_hand" x-model="expMode" class="mt-0.5 text-forest-600">
                            <div>
                                <div class="font-semibold text-slate-900">First-Hand Experience</div>
                                <div class="text-[10px] text-slate-500">Sudah pernah mencoba. Isi fakta pengalaman nyata di bawah.</div>
                            </div>
                        </label>
                    </div>

                    <div x-show="expMode === 'first_hand'" x-cloak class="space-y-1.5 pt-1">
                        <textarea name="experience_usage" rows="2" placeholder="Apa pengalaman nyata setelah menggunakan produk ini?"
                                  class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white">{{ old('experience_usage') }}</textarea>
                        <input type="text" name="experience_pros" value="{{ old('experience_pros') }}" placeholder="Kelebihan yang benar-benar dirasakan"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white">
                        <input type="text" name="experience_cons" value="{{ old('experience_cons') }}" placeholder="Kekurangan yang ditemukan saat pemakaian"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white">
                        <input type="text" name="experience_results" value="{{ old('experience_results') }}" placeholder="Hasil penggunaan nyata yang bisa ditampilkan (opsional)"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs bg-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Target Audiens</label>
                    <input type="text" name="target_audience" required
                           value="{{ old('target_audience', 'Pengguna TikTok Indonesia yang mencari solusi praktis sesuai kategori produk') }}"
                           class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Tujuan Konten</label>
                    <input type="text" name="content_goal" required
                           value="{{ old('content_goal', 'Memberikan informasi objektif, membantu keputusan beli, dan mendorong interaksi natural') }}"
                           class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                </div>

                <!-- Optional collapsible fields (URL, Foto Produk, Catatan Tambahan) -->
                <div>
                    <button type="button" @click="showMore = !showMore" class="text-[11px] font-medium text-forest-700 hover:underline">
                        <span x-text="showMore ? '− Sembunyikan input tambahan (URL, foto, catatan)' : '+ Tambah URL produk, foto produk, atau catatan manual (opsional)'"></span>
                    </button>
                    <div x-show="showMore" x-cloak class="mt-2 space-y-2 rounded-lg border border-slate-200 p-2.5 bg-slate-50/70">
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-0.5">URL Produk (referensi — tidak di-scrape otomatis)</label>
                            <input type="url" name="product_url" value="{{ old('product_url') }}" placeholder="https://..."
                                   class="w-full rounded border border-slate-300 px-2 py-1 text-xs bg-white">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-0.5">Unggah Foto Produk (JPG/PNG/WEBP, maks 15 MB)</label>
                            <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp" class="w-full text-[11px]">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-600 mb-0.5">Informasi Tambahan Manual</label>
                            <textarea name="manual_notes" rows="2" placeholder="Catatan penting, promo, atau batasan produk..."
                                      class="w-full rounded border border-slate-300 px-2 py-1 text-xs bg-white">{{ old('manual_notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Jumlah Ide</label>
                        <select name="count" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            @foreach ([2, 3, 4, 5, 6] as $num)
                                <option value="{{ $num }}" @selected((int) old('count', 4) === $num)>{{ $num }} ide</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">AI Provider</label>
                        <select name="preferred_provider" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="">Otomatis (Default + Fallback)</option>
                            <option value="google_gemini">Google Gemini</option>
                            <option value="openrouter">OpenRouter</option>
                        </select>
                    </div>
                </div>

                <button type="submit" :disabled="loading" class="btn-primary w-full text-xs">
                    <span x-show="!loading">Analisis Produk &amp; Generate Ide AI</span>
                    <span x-show="loading" x-cloak>Sedang menganalisis produk dengan AI...</span>
                </button>
            </form>

            <!-- Tambah Kategori Baru Dinamis -->
            <form method="POST" action="{{ route('content.categories.store') }}" class="panel p-3.5 space-y-2 text-xs">
                @csrf
                <div class="font-semibold text-slate-800">Tambah Kategori / Subkategori Baru (Tanpa Ubah Kode)</div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="name" required placeholder="Nama kategori/subkategori baru"
                           class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    <select name="parent_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                        <option value="">Kategori Utama (Root)</option>
                        @foreach ($categoryTree as $root)
                            <option value="{{ $root->id }}">Sub dari: {{ $root->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-secondary w-full !py-1.5 text-xs">+ Tambah Kategori</button>
            </form>
        </div>

        <!-- Hasil Analisis 15 Poin + Daftar Ide -->
        <div class="lg:col-span-7 space-y-3.5">
            @if (! empty($latestAnalysis))
                <div class="panel p-4 space-y-3 border-emerald-200 bg-emerald-50/30">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">Ringkasan Analisis Produk AI Terbaru</h3>
                        <span class="badge-warn text-[10px]">Hasil Analisis AI (Verifikasi Ulang Fakta)</span>
                    </div>
                    <p class="text-xs text-slate-700">{{ $latestAnalysis['product_summary'] ?? '-' }}</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 text-xs">
                        <div class="rounded-lg bg-white p-2.5 border border-slate-200/80">
                            <div class="font-semibold text-slate-800 mb-1">Fungsi &amp; Masalah Diselesaikan</div>
                            <div class="text-slate-600">{{ implode(' · ', (array) ($latestAnalysis['functions_and_uses'] ?? [])) ?: '-' }}</div>
                            @if (! empty($latestAnalysis['problems_solved']))
                                <div class="text-slate-500 mt-1">Masalah: {{ implode(', ', (array) $latestAnalysis['problems_solved']) }}</div>
                            @endif
                        </div>
                        <div class="rounded-lg bg-white p-2.5 border border-slate-200/80">
                            <div class="font-semibold text-slate-800 mb-1">USP &amp; Target Pengguna</div>
                            <div class="text-slate-700">{{ $latestAnalysis['unique_selling_point'] ?? '-' }}</div>
                            <div class="text-slate-500 mt-1">Target: {{ implode(', ', (array) ($latestAnalysis['target_users'] ?? [])) ?: '-' }}</div>
                        </div>
                        <div class="rounded-lg bg-white p-2.5 border border-slate-200/80">
                            <div class="font-semibold text-emerald-800 mb-1">Keunggulan Utama</div>
                            <div class="text-slate-600">{{ implode('; ', (array) ($latestAnalysis['main_advantages'] ?? [])) ?: '-' }}</div>
                        </div>
                        <div class="rounded-lg bg-white p-2.5 border border-slate-200/80">
                            <div class="font-semibold text-amber-800 mb-1">Kekurangan / Keterbatasan</div>
                            <div class="text-slate-600">{{ implode('; ', (array) ($latestAnalysis['limitations_to_note'] ?? [])) ?: '-' }}</div>
                        </div>
                        <div class="rounded-lg bg-white p-2.5 border border-slate-200/80">
                            <div class="font-semibold text-slate-800 mb-1">Angle, Format &amp; Gaya Disarankan</div>
                            <div class="text-slate-600">
                                Angle: {{ implode(', ', (array) ($latestAnalysis['selling_angles'] ?? [])) ?: '-' }}<br>
                                Format: {{ implode(', ', (array) ($latestAnalysis['recommended_video_formats'] ?? [])) ?: '-' }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-amber-50/70 p-2.5 border border-amber-200">
                            <div class="font-semibold text-amber-900 mb-1">Info Belum Diketahui &amp; Perlu Verifikasi</div>
                            <div class="text-amber-900/90">
                                Belum diketahui: {{ implode(', ', (array) ($latestAnalysis['unknown_information'] ?? [])) ?: 'Tidak ada catatan' }}<br>
                                Perlu verifikasi: {{ implode(', ', (array) ($latestAnalysis['claims_needing_verification'] ?? [])) ?: 'Tidak ada catatan' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filter Bar -->
            <div class="panel p-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <a href="{{ route('content.research') }}" class="px-2.5 py-1 rounded-md {{ ! $savedOnly && $categoryIdFilter === 0 ? 'bg-forest-700 text-white' : 'bg-slate-100 text-slate-700' }}">Semua</a>
                    <a href="{{ route('content.research', ['saved' => 1]) }}" class="px-2.5 py-1 rounded-md {{ $savedOnly ? 'bg-forest-700 text-white' : 'bg-slate-100 text-slate-700' }}">Tersimpan</a>
                </div>
                <form method="GET" action="{{ route('content.research') }}" class="flex items-center gap-2">
                    <select name="category_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs">
                        <option value="">Semua Kategori Produk</option>
                        @foreach ($categoryTree as $root)
                            <option value="{{ $root->id }}" @selected($categoryIdFilter === $root->id)>{{ $root->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if ($ideas->isEmpty())
                <div class="panel p-8 text-center text-xs text-slate-500">
                    Belum ada ide konten. Masukkan informasi produk dari kategori apa pun di sebelah kiri lalu klik <strong>Analisis Produk &amp; Generate Ide AI</strong>.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($ideas as $idea)
                        <div class="panel p-4 space-y-2.5">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="badge-ok">{{ $idea->category }}{{ $idea->subcategory ? ' · ' . $idea->subcategory : '' }}</span>
                                        <span class="badge-muted">{{ $idea->estimated_duration_seconds }} detik</span>
                                        @if ($idea->content_format)
                                            <span class="badge-muted">{{ str_replace('_', ' ', $idea->content_format) }}</span>
                                        @endif
                                        <span class="{{ $idea->experience_mode === 'first_hand' ? 'badge-ok' : 'badge-muted' }}">
                                            {{ $idea->experience_mode === 'first_hand' ? 'First-Hand' : 'Research-Based' }}
                                        </span>
                                        @if ($idea->needs_fact_verification)
                                            <span class="badge-warn">Perlu Verifikasi Fakta</span>
                                        @endif
                                    </div>
                                    <h3 class="text-sm font-semibold text-slate-900 mt-1.5">{{ $idea->title }}</h3>
                                    @if ($idea->product_name)
                                        <div class="text-[11px] text-slate-500">Produk: <strong>{{ $idea->product_name }}</strong></div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <form method="POST" action="{{ route('content.research.save', $idea) }}">
                                        @csrf
                                        <button type="submit" class="btn-secondary !px-2.5 !py-1 text-xs">
                                            {{ $idea->saved_to_library ? 'Lepas Simpan' : 'Simpan' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('content.scripts', ['idea_id' => $idea->id]) }}" class="btn-primary !px-2.5 !py-1 text-xs">
                                        Buat Naskah →
                                    </a>
                                    <form method="POST" action="{{ route('content.research.destroy', $idea) }}" onsubmit="return confirm('Hapus ide ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 px-1.5 py-1">Hapus</button>
                                    </form>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 text-xs bg-slate-50 rounded-lg p-3 border border-slate-200/70">
                                <div>
                                    <div class="text-[11px] font-semibold text-forest-800">Hook 3 Detik Pertama</div>
                                    <p class="text-slate-700 mt-0.5">"{{ $idea->hook_concept }}"</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-semibold text-forest-800">Ringkasan Konsep</div>
                                    <p class="text-slate-700 mt-0.5">{{ $idea->concept_summary }}</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-semibold text-slate-600">Rekomendasi Visual</div>
                                    <p class="text-slate-600 mt-0.5">{{ $idea->visual_recommendation ?: '—' }}</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-semibold text-slate-600">Rekomendasi CTA</div>
                                    <p class="text-slate-600 mt-0.5">{{ $idea->cta_recommendation ?: '—' }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div>{{ $ideas->links() }}</div>
            @endif
        </div>
    </div>
@endsection
