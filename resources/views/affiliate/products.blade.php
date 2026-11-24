@extends('layouts.app')

@section('title', 'Riset Produk Affiliate')
@section('header', 'Riset Produk TikTok Shop Affiliate (Universal)')
@section('subheader', 'Kelola produk lintas kategori via API resmi TikTok Shop atau input manual terverifikasi, Analisis AI objektif, dan approval manual.')

@section('content')
<div class="space-y-4" x-data="{ showManualForm: false }">
    <!-- API Status & Official Search Bar -->
    <div class="panel p-5 space-y-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Sumber Data Produk — API Resmi &amp; Input Manual</h2>
                <p class="text-xs text-slate-500">Sistem membedakan secara tegas data dari <strong>API Resmi</strong>, <strong>Input Manual</strong>, <strong>Analisis AI</strong>, dan <strong>Belum Diverifikasi</strong> (tanpa scraping &amp; tanpa produk dummy).</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($apiConfigured)
                    <span class="badge-ok">TikTok Shop API Terkonfigurasi</span>
                @else
                    <span class="badge-warn">Menunggu akses TikTok Shop Affiliate API</span>
                @endif
                <button type="button" @click="showManualForm = !showManualForm" class="btn-primary text-xs">
                    <span x-text="showManualForm ? 'Tutup Form Manual' : '+ Tambah Produk Manual'"></span>
                </button>
            </div>
        </div>

        @if (! $apiConfigured)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-xs text-amber-900">
                <strong>Status: Menunggu akses TikTok Shop Affiliate API.</strong>
                Pencarian produk live membutuhkan persetujuan di TikTok Shop Partner Center. Sementara menunggu persetujuan resmi, Anda tetap dapat bekerja penuh menggunakan tombol <strong>+ Tambah Produk Manual</strong> untuk kategori produk apa pun.
            </div>
        @endif

        <form method="POST" action="{{ route('affiliate.products.search') }}" class="flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="text" name="keyword" required placeholder="Kata kunci produk lintas kategori (mis. TWS Bluetooth, sunscreen, rak dapur, jaket, pot tanaman)..."
                   class="flex-1 rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
            <select name="category" class="rounded-lg border-slate-300 text-sm">
                <option value="">Semua Kategori TikTok Shop</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary text-xs">Cari via API Resmi</button>
        </form>
    </div>

    <!-- Collapsible Manual Product Form -->
    <form method="POST" action="{{ route('affiliate.products.store-manual') }}" x-show="showManualForm" x-cloak
          class="panel p-5 space-y-3.5 border-forest-300 ring-1 ring-forest-200">
        @csrf
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Input Manual Produk Affiliate (Semua Kategori)</h3>
                <p class="text-[11px] text-slate-500">Hanya nama produk yang wajib. Kosongkan field yang tidak diketahui agar AI menandainya sebagai informasi belum tersedia.</p>
            </div>
            <span class="badge-muted">Sumber: Input Manual</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div class="md:col-span-2">
                <label class="block font-medium text-slate-700 mb-1">Nama Produk *</label>
                <input type="text" name="name" required placeholder="Contoh: Earbuds TWS Bluetooth 5.3 ANC Low Latency"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Brand (Opsional)</label>
                <input type="text" name="brand" placeholder="Contoh: SoundCore / Unbranded"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div>
                <label class="block font-medium text-slate-700 mb-1">Kategori Produk</label>
                <select name="product_category_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Subkategori (Opsional)</label>
                <input type="text" name="subcategory" placeholder="Contoh: Audio & TWS, Sunscreen, Storage"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Nama Toko / Seller (Opsional)</label>
                <input type="text" name="seller_name" placeholder="Nama toko di TikTok Shop"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
            <div>
                <label class="block font-medium text-slate-700 mb-1">Harga (Rp, Opsional)</label>
                <input type="number" step="100" min="0" name="price" placeholder="199000"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Komisi (%, Opsional)</label>
                <input type="number" step="0.1" min="0" max="100" name="commission_rate" placeholder="10"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Status Verifikasi Data</label>
                <select name="verification_status" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                    <option value="unverified">Belum Diverifikasi</option>
                    <option value="partially_verified">Terverifikasi Sebagian</option>
                    <option value="verified">Terverifikasi (Data Resmi Toko)</option>
                </select>
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Kelayakan Affiliate</label>
                <select name="affiliate_eligibility_status" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                    <option value="unverified">Belum Dicek</option>
                    <option value="eligible">Tersedia di Keranjang Affiliate</option>
                    <option value="not_eligible">Tidak Tersedia / Non-Affiliate</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div>
                <label class="block font-medium text-slate-700 mb-1">Deskripsi Produk (Opsional)</label>
                <textarea name="description" rows="2" placeholder="Ringkasan fungsi, kegunaan, dan keunggulan sesuai deskripsi produk."
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5"></textarea>
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Target Audiens &amp; Catatan Riset (Opsional)</label>
                <input type="text" name="target_audience" placeholder="Target: pekerja remote, mahasiswa, ibu rumah tangga..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5 mb-1.5">
                <input type="text" name="research_notes" placeholder="Catatan riset / hal yang perlu dicek ulang"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div>
                <label class="block font-medium text-slate-700 mb-1">Fitur Utama (1 baris per fitur, Opsional)</label>
                <textarea name="features_text" rows="2" placeholder="Active Noise Cancellation&#10;Baterai hingga 24 jam&#10;Charging USB-C"
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5"></textarea>
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">Spesifikasi Penting (Format Key: Value, Opsional)</label>
                <textarea name="specifications_text" rows="2" placeholder="Berat: 45 gram&#10;Bluetooth: 5.3&#10;Garansi: 12 bulan"
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5"></textarea>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div>
                <label class="block font-medium text-slate-700 mb-1">URL Produk (Opsional — tidak di-scrape)</label>
                <input type="url" name="product_url" placeholder="https://shop.tiktok.com/..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
            <div>
                <label class="block font-medium text-slate-700 mb-1">URL Gambar Produk Resmi (Opsional)</label>
                <input type="url" name="image_url" placeholder="https://..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showManualForm = false" class="btn-secondary text-xs">Batal</button>
            <button type="submit" class="btn-primary text-xs">Simpan Produk Manual</button>
        </div>
    </form>

    <!-- Status & Category Filter Pills -->
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
            <a href="{{ route('affiliate.products') }}" class="px-3 py-1.5 rounded-lg border {{ ! $activeStatus ? 'bg-forest-700 text-white border-forest-700' : 'bg-white text-slate-600 border-slate-200' }}">
                Semua ({{ $products->count() }})
            </a>
            @foreach ($statuses as $st)
                <a href="{{ route('affiliate.products', ['status' => $st->value, 'category_id' => $activeCategoryId ?: null]) }}"
                   class="px-3 py-1.5 rounded-lg border {{ $activeStatus === $st->value ? 'bg-forest-700 text-white border-forest-700' : 'bg-white text-slate-600 border-slate-200' }}">
                    {{ $st->label() }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('affiliate.products') }}" class="flex items-center gap-1.5">
            @if ($activeStatus)
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif
            <select name="category_id" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1.5">
                <option value="">Filter Kategori: Semua</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected($activeCategoryId === $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <!-- Product List + AI Analysis + Approval Workflow -->
    @if ($products->isEmpty())
        <div class="panel p-8 text-center space-y-2">
            <div class="badge-muted">Menunggu akses TikTok Shop Affiliate API / Input Manual</div>
            <h3 class="text-sm font-semibold text-slate-900">Belum Ada Produk Affiliate Tersimpan</h3>
            <p class="text-xs text-slate-500 max-w-lg mx-auto">
                Katalog produk tidak berisi produk dummy. Klik <strong>+ Tambah Produk Manual</strong> untuk memasukkan produk dari kategori apa pun (Electronics, Fashion, Beauty, Home, Plants, dll.) atau sinkronkan setelah TikTok Shop API aktif.
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($products as $product)
                @php
                    $analysis = $product->analysis_result ?? [];
                    $breakdown = $product->analysis_score_breakdown ?? [];
                    $sourceLabel = match ($product->data_source_type ?: $product->source_platform) {
                        'tiktok_shop_api', 'official_api' => ['API Resmi TikTok Shop', 'badge-ok'],
                        'manual_input' => ['Input Manual', 'badge-muted'],
                        'ai_analysis' => ['Analisis AI', 'badge-warn'],
                        default => ['Belum Diverifikasi', 'badge-warn'],
                    };
                @endphp
                <div class="panel p-4 space-y-3">
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-semibold text-slate-900">{{ $product->name }}</span>
                                <span class="badge-muted">{{ $product->category }}{{ $product->subcategory ? ' · ' . $product->subcategory : '' }}</span>
                                <span class="{{ $sourceLabel[1] }}">{{ $sourceLabel[0] }}</span>
                                <span class="{{ $product->approval_status === 'approved' ? 'badge-ok' : ($product->approval_status === 'rejected' ? 'badge-err' : 'badge-warn') }}">
                                    {{ $product->approval_status }}
                                </span>
                                @if ($product->quality_score !== null)
                                    <span class="badge-ok">Skor Kurasi AI: {{ $product->quality_score }}/100</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 flex flex-wrap gap-x-4 gap-y-1">
                                <span>Brand: <strong>{{ $product->brand ?? 'Tidak tersedia' }}</strong></span>
                                <span>Seller: <strong>{{ $product->seller_name ?? 'Tidak tersedia' }}</strong></span>
                                <span>Harga: <strong>{{ $product->price !== null ? 'Rp' . number_format((float) $product->price, 0, ',', '.') : 'Tidak tersedia' }}</strong></span>
                                <span>Komisi: <strong>{{ $product->commission_rate !== null ? $product->commission_rate . '%' : 'Tidak tersedia' }}</strong></span>
                                <span>Verifikasi: <strong>{{ str_replace('_', ' ', $product->verification_status ?: 'unverified') }}</strong></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 flex-shrink-0 flex-wrap">
                            <a href="{{ route('content.research', ['product_id' => $product->id]) }}" class="btn-secondary !py-1.5 text-xs">Riset Konten →</a>
                            <form method="POST" action="{{ route('affiliate.products.analyze', $product) }}" class="flex items-center gap-1.5">
                                @csrf
                                <select name="provider" class="rounded-lg border-slate-300 text-xs py-1.5">
                                    <option value="">Default AI</option>
                                    @foreach ($aiProviders as $p)
                                        <option value="{{ $p['slug'] }}">{{ $p['name'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-secondary !py-1.5 text-xs">Analisis AI</button>
                            </form>
                        </div>
                    </div>

                    @if (! empty($analysis))
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs space-y-2">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <span class="font-semibold text-slate-800">Hasil Analisis AI ({{ $breakdown['provider_used'] ?? 'AI' }} · Rekomendasi: {{ $analysis['rekomendasi_kelayakan'] ?? '-' }})</span>
                                <span class="text-[11px] text-slate-500">{{ $breakdown['disclaimer'] ?? 'Estimasi kurasi AI — bukan data resmi TikTok Shop.' }}</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-slate-600">
                                <div><strong>Relevansi Kategori:</strong> {{ $analysis['relevansi_niche'] ?? '-' }}</div>
                                <div><strong>Sudut Edukasi:</strong> {{ $analysis['sudut_edukasi'] ?? '-' }}</div>
                                <div><strong>Ide Demonstrasi:</strong> {{ $analysis['ide_demonstrasi'] ?? '-' }}</div>
                                <div><strong>Informasi Belum Tersedia:</strong> {{ is_array($analysis['informasi_belum_tersedia'] ?? null) ? implode(', ', $analysis['informasi_belum_tersedia']) : '-' }}</div>
                            </div>
                        </div>
                    @endif

                    <!-- Product Approval Workflow -->
                    <form method="POST" action="{{ route('affiliate.products.status', $product) }}" class="flex flex-col sm:flex-row gap-2 pt-2 border-t border-slate-100">
                        @csrf
                        <select name="approval_status" class="rounded-lg border-slate-300 text-xs">
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($product->approval_status === $st->value)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="approval_notes" value="{{ $product->approval_notes }}" placeholder="Catatan persetujuan / alasan keputusan..."
                               class="flex-1 rounded-lg border-slate-300 text-xs">
                        <button type="submit" class="btn-primary !py-1.5 text-xs">Simpan Status</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
