@extends('layouts.app')

@section('title', 'Universal Visual Asset Manager')
@section('header', 'Visual Asset Manager (Universal)')
@section('subheader', 'Unggah dan kelompokkan foto produk, klip demo, unboxing, lifestyle, atau musik latar legal berdasarkan Produk, Campaign, Kategori, dan Jenis Media.')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Upload Form -->
        <div class="lg:col-span-4">
            <form method="POST" action="{{ route('content.assets.upload') }}" enctype="multipart/form-data"
                  x-data="{ uploading: false }" @submit="uploading = true"
                  class="panel p-4 space-y-3 sticky top-20">
                @csrf
                <h2 class="text-sm font-semibold text-slate-900">Unggah Aset Visual / Audio Legal</h2>
                <p class="text-[11px] text-slate-500">
                    Sistem tidak mengambil gambar produk sembarangan dari internet. Unggah aset milik Anda sendiri atau aset berlisensi legal.
                </p>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Pilih File</label>
                    <input type="file" name="asset_file" required accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,.mp3,.wav"
                           class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-forest-50 file:text-forest-800 hover:file:bg-forest-100">
                    <p class="text-[11px] text-slate-400 mt-1">
                        Gambar: JPG/JPEG/PNG/WEBP (maks 15 MB) · Video: MP4/MOV (maks 100 MB) · Audio: MP3/WAV (maks 25 MB).
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Jenis / Label Visual</label>
                    <select name="category_tag" required class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                        @foreach ($categoryTags as $k => $lbl)
                            <option value="{{ $k }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Kategori Produk (Opsional)</label>
                    <select name="product_category_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                        <option value="">— Pilih Kategori —</option>
                        @foreach ($categoryTree as $root)
                            <option value="{{ $root->id }}" @selected($categoryFilter === $root->id)>{{ $root->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Tautkan ke Produk (Opsional)</label>
                    <select name="affiliate_product_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                        <option value="">— Tanpa Produk Tertentu —</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected($productFilter === $p->id)>{{ $p->name }} ({{ $p->category }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Campaign (Opsional)</label>
                        <select name="campaign_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="">— Umum —</option>
                            @foreach ($campaigns as $camp)
                                <option value="{{ $camp->id }}" @selected($campaignFilter === $camp->id)>{{ $camp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Proyek Konten</label>
                        <select name="generated_content_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="">— Galeri Umum —</option>
                            @foreach ($contents as $c)
                                <option value="{{ $c->id }}" @selected($contentFilter === $c->id)>{{ $c->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="submit" :disabled="uploading" class="btn-primary w-full text-xs">
                    <span x-show="!uploading">Unggah Aset</span>
                    <span x-show="uploading" x-cloak>Mengunggah &amp; memverifikasi MIME...</span>
                </button>
            </form>
        </div>

        <!-- Asset Filters & Grid -->
        <div class="lg:col-span-8 space-y-3.5">
            <form method="GET" action="{{ route('content.assets') }}" class="panel p-3 flex flex-wrap items-center gap-2 text-xs">
                <select name="type" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    <option value="">Jenis Media: Semua</option>
                    <option value="image" @selected($typeFilter === 'image')>Gambar</option>
                    <option value="video" @selected($typeFilter === 'video')>Video</option>
                    <option value="bg_music" @selected($typeFilter === 'bg_music')>Musik Latar</option>
                </select>
                <select name="category_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                    <option value="">Kategori: Semua</option>
                    @foreach ($categoryTree as $root)
                        <option value="{{ $root->id }}" @selected($categoryFilter === $root->id)>{{ $root->name }}</option>
                    @endforeach
                </select>
                <select name="product_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs max-w-[200px]">
                    <option value="">Produk: Semua</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($productFilter === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                <select name="campaign_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs max-w-[180px]">
                    <option value="">Campaign: Semua</option>
                    @foreach ($campaigns as $camp)
                        <option value="{{ $camp->id }}" @selected($campaignFilter === $camp->id)>{{ $camp->name }}</option>
                    @endforeach
                </select>
                <a href="{{ route('content.assets') }}" class="text-slate-500 hover:text-slate-800 ml-auto">Reset ({{ $assets->total() }})</a>
            </form>

            @if ($assets->isEmpty())
                <div class="panel p-10 text-center text-xs text-slate-500">
                    Belum ada aset yang cocok dengan filter. Unggah foto produk, video demo, atau musik latar legal di sebelah kiri.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3.5">
                    @foreach ($assets as $asset)
                        <div class="panel overflow-hidden flex flex-col">
                            <div class="h-44 bg-slate-900 flex items-center justify-center relative overflow-hidden">
                                @if ($asset->asset_type === 'image')
                                    <img src="{{ route('content.assets.preview', $asset) }}" alt="{{ $asset->original_name }}" class="w-full h-full object-cover">
                                @elseif ($asset->asset_type === 'video')
                                    <video muted playsinline preload="metadata" controls class="w-full h-full object-cover"
                                           src="{{ route('content.assets.preview', $asset) }}"></video>
                                @else
                                    <div class="p-3 w-full text-center space-y-2">
                                        <span class="badge-ok">Musik Latar</span>
                                        <audio controls class="w-full h-8" src="{{ route('content.assets.preview', $asset) }}"></audio>
                                    </div>
                                @endif
                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded bg-slate-950/75 text-white text-[10px] font-medium uppercase">
                                    {{ $asset->asset_type }}
                                </span>
                                @if ($asset->productCategory)
                                    <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-slate-950/75 text-slate-100 text-[10px]">
                                        {{ $asset->productCategory->name }}
                                    </span>
                                @endif
                            </div>

                            <div class="p-3 space-y-2 text-xs flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="font-semibold text-slate-800 truncate" title="{{ $asset->original_name }}">{{ $asset->original_name }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        @if ($asset->width && $asset->height)
                                            {{ $asset->width }}×{{ $asset->height }} ·
                                        @endif
                                        @if ($asset->duration_seconds)
                                            {{ $asset->duration_seconds }}s ·
                                        @endif
                                        {{ number_format(($asset->file_size_bytes ?? 0) / 1024, 0) }} KB
                                    </div>
                                    @if ($asset->product)
                                        <div class="text-[10px] text-forest-700 truncate mt-0.5">Produk: {{ $asset->product->name }}</div>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('content.assets.update', $asset) }}" class="space-y-1.5 pt-1 border-t border-slate-100">
                                    @csrf
                                    @method('PATCH')
                                    <select name="product_category_id" class="w-full rounded border border-slate-300 px-2 py-1 text-[11px]">
                                        <option value="">Kategori: Umum</option>
                                        @foreach ($categoryTree as $root)
                                            <option value="{{ $root->id }}" @selected($asset->product_category_id === $root->id)>{{ $root->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="affiliate_product_id" class="w-full rounded border border-slate-300 px-2 py-1 text-[11px]">
                                        <option value="">Produk: Umum</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}" @selected($asset->affiliate_product_id === $p->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="generated_content_id" class="w-full rounded border border-slate-300 px-2 py-1 text-[11px]">
                                        <option value="">Konten: Galeri Umum</option>
                                        @foreach ($contents as $c)
                                            <option value="{{ $c->id }}" @selected($asset->generated_content_id === $c->id)>{{ $c->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex items-center gap-1.5">
                                        <input type="number" name="display_order" min="1" max="999" value="{{ $asset->display_order }}"
                                               class="w-16 rounded border border-slate-300 px-2 py-1 text-[11px]" title="Urutan tampil">
                                        <button type="submit" class="btn-secondary !px-2 !py-1 text-[11px] flex-1">Simpan</button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('content.assets.destroy', $asset) }}" onsubmit="return confirm('Hapus aset ini secara permanen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[11px] text-rose-600 hover:text-rose-800 w-full text-right">Hapus Aset</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div>{{ $assets->links() }}</div>
            @endif
        </div>
    </div>
@endsection
